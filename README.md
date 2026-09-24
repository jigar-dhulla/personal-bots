<img src="public/logo.svg" alt="" width="72" align="right">

# Jigar's Bots

A set of personal WhatsApp bots that share **one phone number**. You message the number, and each bot replies only in the chats you've set it up for.

| Bot | What it does | Try saying |
|---|---|---|
| **Yaarpool** | Carpooling in group chats. Post, find, join, edit or cancel rides | "driving Pune → Mumbai Sat 9am, 3 seats" · "need a lift Andheri → BKC tomorrow 8am" · "show rides" |
| **Instamart** | Grocery shopping on Swiggy Instamart for one owner account. Search, fill the cart, order (cash or UPI) | "is there brown bread?" · "add 2 of number 3" · "what do I need for veg pulav for 4?" · "place the order" |

Built with Laravel 13, the [Laravel AI SDK](https://github.com/laravel/ai) (Gemini by default) and [`laravel-whatsapp-ai-agent`](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent).

## How it works

```
wacli sync ──► ~/.wacli/wacli.db ──► php artisan wa:listen ──► bot agent ──► tools ──► WhatsApp reply
```

[`wacli`](https://github.com/openclaw/wacli) keeps a local copy of your WhatsApp messages. `wa:listen` reads new ones and hands each message to every bot configured for that chat.

## Getting started

### 1. Prerequisites

- PHP 8.4+, Composer, Node.js
- A [Gemini API key](https://aistudio.google.com/apikey)
- [`wacli`](https://github.com/openclaw/wacli), paired with the bot's WhatsApp number and syncing:

  ```bash
  wacli sync --follow --refresh-contacts --refresh-groups
  ```

Prefer not to install PHP or `wacli` locally? See [Run with Docker](#run-with-docker).

### 2. Install

```bash
git clone https://github.com/jigar-dhulla/personal-bots.git
cd personal-bots
composer run setup                # install, copy .env, generate key, migrate, build assets
php artisan user:register         # create a dashboard login (there is no public sign-up)
```

### 3. Configure `.env`

```dotenv
GEMINI_API_KEY=your-key
WA_PHONE_NUMBER=919800000000      # the bot's number, digits only (used for wa.me links)
```

Each bot listens only where you point it. Find chat and group JIDs:

```bash
php artisan wa:chats              # 1:1 chats
php artisan wa:groups             # groups
```

Then set each bot's block (the prefix is the bot's key, upper-cased):

```dotenv
# Yaarpool: answer every message in these groups
YAARPOOL_TRIGGERS=
YAARPOOL_CHATS=
YAARPOOL_GROUPS=120363000000000000@g.us

# Instamart: only reply in my own chat, when summoned
INSTAMART_TRIGGERS=instamart,grocery
INSTAMART_CHATS=919800000000@s.whatsapp.net
INSTAMART_GROUPS=
```

| Variable | Meaning |
|---|---|
| `<BOT>_TRIGGERS` | Comma-separated phrases that summon the bot (case-insensitive, anywhere in the message). Empty = every message in scope |
| `<BOT>_CHATS` | Comma-separated DM JIDs the bot listens to |
| `<BOT>_GROUPS` | Comma-separated group JIDs the bot listens to |
| `<BOT>_DOMAIN` | Optional hostname whose `/` opens this bot's landing page |

A bot with no chats and no groups stays off. Check the wiring with:

```bash
php artisan wa:status
```

### 4. Run

```bash
composer run dev                  # web server, queue worker, logs, Vite
php artisan wa:listen             # in a second terminal (add -vvv to see every message)
```

Open `http://localhost:8000/admin` for the dashboard, then message the bot from a chat you configured.

## Bot setup

### Yaarpool

Works as soon as its chats/groups are set. Optional extras:

```bash
# Default origin/destination for a group, so "need a lift at 9" is enough
php artisan group:settings 120363000000000000@g.us --from="Andheri" --to="BKC"
```

Members can also save their own commute: *"my usual route is Andheri to BKC, office 10–7, Mon/Wed/Fri"*.

### Instamart

Instamart orders on **one Swiggy account**, so it needs a Swiggy login. The login lasts **5 days**; log in again before it runs out (the dashboard counts down).

```bash
php artisan instamart:login           # prints a Swiggy link; log in with OTP, paste the redirected URL back
php artisan instamart:login --status  # is the login still valid?
php artisan instamart:logout          # revoke it
php artisan instamart:forget          # delete stored addresses, orders and caches
```

| Variable | Default | Meaning |
|---|---|---|
| `SWIGGY_REDIRECT_URI` | `http://localhost:8765/callback` | Keep the default for the terminal login above. An HTTPS `…/instamart/callback` URL (allowlisted by Swiggy) enables a **Swiggy login** button in the dashboard instead |

A typical chat:

```
you:  what do I need for paneer butter masala for 2?
bot:  1. Amul Fresh Paneer 200g — ₹95  2. Tomatoes 500g — ₹30 …
you:  add all
you:  place the order, cash
bot:  Order summary: 6 items, ₹412, to Home (…). Reply yes to confirm.
you:  yes
```

Orders are never placed without an explicit "yes". Orders can't be cancelled from WhatsApp; call Swiggy customer care. For operations and troubleshooting, see the [Instamart runbook](app/Bots/Instamart/RUNBOOK.md).

## Run with Docker

The image bundles PHP 8.4, Composer and `wacli`. Your `~/.wacli` is mounted in, so the existing WhatsApp pairing is reused. **Stop any `wacli sync` running on the host first.**

```bash
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate
docker compose up -d wacli wa-listen queue

docker compose run --rm app php artisan wa:status   # ad-hoc commands
docker compose logs -f wa-listen                    # follow the listener
```

## Development

```bash
php artisan test --compact            # run tests
vendor/bin/pint --dirty               # format changed PHP files
```

Each bot lives in `app/Bots/<Name>/` and is registered in `config/bots.php`. To add a bot or learn how the pieces fit, see [`CLAUDE.md`](CLAUDE.md).

## License

[AGPL-3.0-only](LICENSE).
