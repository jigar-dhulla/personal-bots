# Instamart bot runbook

Operating notes for the Instamart bot: routine tasks, what to watch, and what to do when something breaks. For how the code is built, see the Instamart section of `CLAUDE.md`.

## At a glance

| | |
|---|---|
| What it does | Shops on Swiggy Instamart from WhatsApp: search, cart, checkout (cash on delivery or UPI link), order status |
| Account | **One** Swiggy account (the owner's). Everyone in `INSTAMART_CHATS` / `INSTAMART_GROUPS` orders on it |
| Upstream | Swiggy Instamart MCP server `https://mcp.swiggy.com/im` (`services.swiggy.instamart_url`), OAuth at `https://mcp.swiggy.com` |
| Model | The app's default AI provider (`config/ai.php`, currently Gemini) |
| Runs in | The `queue` container (`ProcessWhatsAppMessage`, `ConfirmUpiPayment`); inbound messages come from `wa-listen` ← `wacli` |
| Data | `instamart_connections` (encrypted token), `instamart_chat_addresses`, `instamart_orders`; cache keys `instamart:picks:<chat>`, `instamart:pending-order:<chat>` |
| Swiggy escalation | builders@swiggy.in. Include the `request_id` and timestamp from the `swiggy.mcp.call` log line |
| Cancelling an order | Not possible through MCP. Swiggy customer care: **080-67466729** |

## Commands

On the prod VPS, run commands from `/opt/yaarpool`. The examples use:

```sh
cd /opt/yaarpool
alias dc='docker compose -f docker-compose.prod.yml'
```

| Task | Command |
|---|---|
| Is the Swiggy login still valid? | `dc exec queue php artisan instamart:login --status` |
| Log in (every ≤5 days) | Dashboard → **Swiggy login** (HTTPS callback), or `dc exec queue php artisan instamart:login` (localhost paste flow) |
| Revoke the login and clear chat addresses | `dc exec queue php artisan instamart:logout` |
| Delete stored chat data | `dc exec queue php artisan instamart:forget [--chat=<jid>] [--force]` |
| Follow Swiggy calls live | `dc exec queue tail -f storage/logs/laravel.log \| grep --line-buffered 'swiggy\.'` |
| Watch job results | `dc logs -f queue \| grep -E 'DONE\|FAIL'` |
| List failed jobs | `dc exec queue php artisan queue:failed` |
| Retry failed jobs | `dc exec queue php artisan queue:retry <id> [<id>…]` (see [Retrying failed jobs](#retrying-failed-jobs)) |
| Check wiring (triggers, chats, groups) | `dc exec queue php artisan wa:status` |

Locally, drop the `dc exec …` prefix and run `php artisan …` directly.

## Routine: log in again every 5 days

Swiggy issues **no refresh tokens**. The access token lasts 5 days (`expires_in` 432000). Swiggy's session lasts 30 days from last use, so a re-login within that window usually doesn't need a new OTP. The dashboard's **Swiggy login** card counts down the days left.

Which flow you use depends on `SWIGGY_REDIRECT_URI`:

| `SWIGGY_REDIRECT_URI` | Flow |
|---|---|
| `http://localhost:8765/callback` (default) | Terminal paste flow, `instamart:login` |
| `https://<your domain>/instamart/callback`, allowlisted by builders@swiggy.in | Browser flow from the dashboard. `instamart:login` refuses and points to it |

### Browser flow (HTTPS callback)

1. Log in to the dashboard (`/admin`), then click **Swiggy login** in the nav. The link only shows when the redirect URI is HTTPS.
2. Log in at Swiggy with phone + OTP. Swiggy sends you back to `/instamart/callback`, and the dashboard shows "Logged in to Swiggy…".
3. If it says the login "did not start from this dashboard session", you finished a login started elsewhere or opened the callback twice. Click **Swiggy login** again.

The callback needs the dashboard login, and it only accepts the state this session started, once. A forged or replayed callback can't replace the token. Changing `SWIGGY_REDIRECT_URI` registers a new OAuth client on the next login, because a client only works with the redirect it was registered with.

### Terminal paste flow (localhost)

1. `dc exec queue php artisan instamart:login`
2. Open the printed `https://mcp.swiggy.com/auth/authorize?…` link on any device and log in with phone + OTP.
3. The browser then goes to `http://localhost:8765/callback?code=…&state=…` (`SWIGGY_REDIRECT_URI`), which won't load. That's expected. Copy the **full URL from the address bar** and paste it into the prompt **within ~2 minutes**, because the code is single-use and expires after 120 s.
4. Check with `instamart:login --status`.

Notes:
- The first login registers an OAuth client (dynamic client registration). Later logins reuse its `client_id`.
- `state mismatch` means you pasted a URL from an earlier attempt. Run the command again.
- `Swiggy token exchange failed` usually means the code expired or was already used. Run the command again and paste faster.
- An HTTPS redirect URI must be allowlisted by builders@swiggy.in before you can use it in `SWIGGY_REDIRECT_URI`. See the browser flow above.

## Monitoring

All Swiggy traffic goes to `storage/logs/laravel.log`. On prod it is **not** in `docker compose logs`, because `LOG_STACK=single`.

| Log line | Level | Meaning |
|---|---|---|
| `swiggy.mcp.call` | info (warning when the call fails) | One per HTTP call: `method`, `tool`, `request_id` (our JSON-RPC id), `session_id` / `response_session_id` (Swiggy currently issues none), `arguments`, `status`, `duration_ms`, `rate_limit_remaining`, `at`. The token is never logged |
| `swiggy.mcp.failed` | warning | A call that failed after any retries. `kind` is one of `not_connected`, `unauthenticated`, `rate_limited`, `transient`, `domain`, `invalid` |
| `swiggy.mcp.deprecation` | warning | Swiggy flagged a tool or parameter in `_meta.swiggy.deprecation`. Plan the change before the removal date |
| `swiggy.checkout.reconciled` | warning | A checkout failed upstream and was checked against `get_orders`. `placed_order_ids` says whether it went through |

Healthy traffic is `swiggy.mcp.call` lines with `status: 200` and `ProcessWhatsAppMessage … DONE` in the queue logs. Ignore the Docker health status of `queue`, `wa-listen` and `wacli`; it shows *unhealthy* even when they work.

## Incidents

### The bot says "My Swiggy login has expired"

- **Cause:** the token passed its 5 days, or Swiggy answered 401 / 419 / JSON-RPC `-32001`. In that case `Connection::expire()` has already marked it unusable.
- **Fix:** [log in again](#routine-log-in-again-every-5-days) (dashboard **Swiggy login**, or `instamart:login`). Nothing needs retrying; ask in WhatsApp again afterwards.

### Every Instamart message fails fast (`ProcessWhatsAppMessage … FAIL` in under 100 ms)

1. Read the exception: `dc exec queue php artisan queue:failed`, then look at the `exception` column of the newest row in `failed_jobs`.
2. `Invalid JSON payload received. Unknown name "additionalProperties"` from Gemini means a tool schema has a nested object. laravel/ai adds `additionalProperties` to nested objects, and Gemini rejects the whole request. Keep tool schemas flat. `tests/Feature/GeminiToolSchemaTest.php` catches this in CI.
3. A 401/403 from the AI provider points to `GEMINI_API_KEY` (or the key for whichever provider is the default).
4. After the fix is deployed, [retry the failed jobs](#retrying-failed-jobs).

### The bot says "Swiggy is not responding right now"

- **Cause:** a transient upstream failure (5xx, timeout, network, or JSON-RPC `-32603`). Reads and cart changes were already retried 3 times with backoff.
- **Check:** find the `swiggy.mcp.call` lines for the time of the failure (their `status` and `duration_ms`).
- **If it persists for more than a few minutes,** email builders@swiggy.in with the `request_id`s and timestamps.

### The bot says "Swiggy is asking me to slow down"

- **Cause:** a 429. The documented limits are 70 requests/min overall and 30/min for write tools, per user per server. The bot never retries a 429.
- Wait a minute. If it keeps happening, look for a loop, i.e. lots of `swiggy.mcp.call` lines in a short burst.

### A checkout failed and nobody knows whether the order went through

The bot handles this by itself. It **never retries `checkout`**. Before checking out it saves the recent `get_orders` ids, and after a failure it waits 3 s and compares. Its reply in the chat says which outcome it saw.

- "order #… was placed": it's recorded in `instamart_orders`. For UPI, the payment link was lost, so pay in the Swiggy app.
- "the order was not placed": it's safe to say "order it" again, which shows a fresh summary to confirm.
- "I could not confirm whether the order went through": the order list couldn't be read either. **Don't re-order yet.** Ask the bot for order status, or check the Swiggy app, first.

The matching `swiggy.checkout.reconciled` log line records what was found.

### A UPI order is stuck on "waiting for payment"

- `ConfirmUpiPayment` calls `check_payment_status` roughly every `pollingIntervalInMs`, re-queuing itself until `maxTimeToPollForInMs` runs out (5 minutes if Swiggy doesn't say). Then it calls `confirm_order` once, which settles the order either way, and posts the result in the chat.
- **Check:** `instamart_orders.status` for that `order_id`. It should be `pending_payment`, then `placed` or `failed`. The queue worker must be running for the follow-up to happen.
- Each payment check holds the queue worker for about 19 s. Other WhatsApp messages wait behind it.
- The prod worker runs `queue:work --tries=1 --timeout=120`. A job that runs past 120 s is killed and is not retried.
- If the chat never got a result, ask the bot for order status and check the Swiggy app. Don't place the order again until you know.

### "Stream replaced" loops in the `wacli` logs; messages missed or late

Two wacli clients are using the same WhatsApp session, typically the local Docker stack and prod. Each connection kicks the other off. Stop one of them. Don't run the local stack against a copy of prod's wacli store.

### Someone wants to cancel an order

MCP has no cancel tool. Call Swiggy customer care on **080-67466729**. The bot already tells users this.

### Wrong delivery address

Carts belong to one address. Ask the bot to change the delivery address (`delivery_address` with the list number). Switching **empties the cart**, as Swiggy requires.

## Retrying failed jobs

`queue:work` runs with `--tries=1`, so a failed `ProcessWhatsAppMessage` stays in `failed_jobs` until you retry it.

- Retry only after the cause is fixed and deployed.
- A retry re-runs the **original message** and posts the reply into the chat. That's harmless for searches and cart views. For a message that confirmed an order ("yes"), check `instamart_orders` and the Swiggy app first, because the confirmation window is 10 minutes and it could place an order late.
- `ResolveWhatsAppMessage` failures (placeholder bodies that never resolved) are a separate, low-severity wacli issue. They don't need retrying.

## Data and privacy

What the bot stores, and how to remove it:

| Data | Where | Removed by |
|---|---|---|
| Swiggy access token (encrypted with `APP_KEY`) + OAuth `client_id` and the redirect URI it was registered for | `instamart_connections` | `instamart:logout` (also revokes it at Swiggy) |
| Delivery address id + address line, per chat | `instamart_chat_addresses` | `instamart:logout`, `instamart:forget` |
| Orders it placed (id, chat, sender, payment method, status, total) | `instamart_orders` | `instamart:forget` |
| Last search results (3 h) and a pending order confirmation (10 min), per chat | cache | expiry, `instamart:forget` |
| Call logs (tool arguments such as address ids and search terms; never the token) | `storage/logs/laravel.log` | log rotation / manual cleanup |

Orders stay on the Swiggy account whatever is deleted here. Swiggy remains the data fiduciary for account data.

**Access control is the chat list.** Anyone who can trigger the bot in `INSTAMART_CHATS` or `INSTAMART_GROUPS` can search, fill the cart and order on the owner's account. Only the person who saw a summary can confirm that order. Keep those lists to chats you trust.

## Known Swiggy doc discrepancies

The live server differs from the docs here, and the code follows the live server:

- Tool results come back as raw data in `structuredContent`, not the documented `{success, data, message}` envelope. `InstamartClient::unwrap()` accepts both.
- The server doesn't issue an `Mcp-Session-Id`, and hasn't sent rate-limit headers so far.
- `ship-to-production` says rate limits aren't enforced in v1.0, while the rate-limits page gives numbers.
- The `/auth/register` request format isn't documented. We send a standard RFC 7591 body.
- `track_order` needs `lat`/`lng`, which no documented tool returns, so `order_status` uses `get_orders`.
