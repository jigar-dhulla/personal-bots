<img src="public/logo.svg" alt="" width="72" align="right">

# Jigar's Bots

One WhatsApp number, many bots. Inbound messages are pulled from a local `wacli` SQLite store, dispatched to whichever bots are in scope for that chat and matched by the message body, and their replies are sent back to WhatsApp.

Each bot owns a vertical slice under `app/Bots/<Name>/` — its agent, tools, models, admin screens and artisan commands — and is registered once in `config/bots.php`. Everything shared (WhatsApp transport, the admin dashboard, auth, the public hub) is bot-agnostic.

**Yaarpool**, a ridesharing bot, is the first bot in the repo. Members of a group post offers ("driving Pune → Mumbai Sat 9am, 3 seats") and requests ("need a lift Andheri → BKC tomorrow 8am") in natural language; it detects intent and persists, lists, edits, joins, or cancels rides on their behalf.

## How it works

```
wacli sync ──► ~/.wacli/wacli.db ──► wa:listen ──► AgentRouter ──► <Bot>Agent ──► tool ──► reply
                                                   (scope + triggers)
```

`AgentRouter` (from the transport package) dispatches a message to every agent whose scope contains the chat JID *and* whose triggers match the body. The agent table in `config/whatsapp-agent.php` is derived from `config/bots.php`, one entry per registered bot, each scoped by its own env prefix.

Agents are built on the Laravel AI SDK and backed by Google Gemini by default. They extend `App\Bots\BotAgent`, which supplies the parts every bot shares — binding the conversation to a chat, registering the sender as a user, and assembling the system prompt (current date/time, configured triggers, WhatsApp etiquette rules) around the bot's own persona, tool guidance, and extra rules.

WhatsApp transport is handled by [`jigar-dhulla/laravel-whatsapp-ai-agent`](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent), which in turn relies on the external `wacli` sync daemon to maintain `~/.wacli/wacli.db`.

## Adding a bot

1. Create the slice under `app/Bots/<Name>/`:
   - `<Name>Agent extends App\Bots\BotAgent` — implement `persona()`, `guidance()`, `tools()`, and optionally `rules()`.
   - `Tools/` — one class per tool, implementing `Laravel\Ai\Contracts\Tool`.
   - `Models/`, `Enums/`, `Http/Controllers/`, `Console/` as needed. Models outside `App\Models` need a `#[UseFactory(...)]` attribute to find their factory.
2. Write a manifest implementing `App\Bots\Bot` — its key, name, tagline, agent class, env prefix, route file, commands, admin nav links and dashboard cards.
3. Register the manifest class in `config/bots.php`.
4. Add `<PREFIX>_TRIGGERS`, `<PREFIX>_CHATS`, `<PREFIX>_GROUPS` to `.env`.

That's it — the agent table, public hub entry, routes, admin nav, dashboard cards and artisan commands all follow from the manifest. Discover JIDs with `php artisan wa:chats` / `wa:groups`; verify the wiring with `php artisan wa:status`.

## Yaarpool's tools

| Tool | Purpose | Owner-only |
|---|---|---|
| `ride_request` | Persist a passenger looking for a lift | — |
| `ride_create` | Persist a driver publishing a trip | — |
| `ride_list` | List upcoming rides in the current chat | — |
| `ride_join` | Reserve seat(s) on someone else's offer | — |
| `ride_update` | Edit a ride the user previously posted | ✓ |
| `ride_delete` | Cancel a ride the user previously posted | ✓ |
| `route_travellers` | List chat members whose saved personal route matches | — |
| `user_settings` | Save or show the sender's personal commute defaults | — |

Owner-only tools refuse the call unless both the chat JID and sender JID on the ride match the inbound WhatsApp message; rides in other chats are treated as not-found rather than surfaced.

## Requirements

- PHP 8.4+
- A Gemini API key (`GEMINI_API_KEY`)
- `wacli sync --follow --refresh-contacts --refresh-groups` running externally to keep `~/.wacli/wacli.db` populated

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Add `GEMINI_API_KEY` to `.env`, then wire each bot's chat / group JIDs into its `<PREFIX>_CHATS` / `<PREFIX>_GROUPS` env vars. Discover JIDs with:

```bash
php artisan wa:chats     # list 1:1 chats
php artisan wa:groups    # list groups
php artisan wa:status    # verify which agent is wired to which chat
```

## Running

Start the app stack (HTTP server, queue worker, log tail, Vite):

```bash
composer run dev
```

Start the WhatsApp listener daemon in a separate terminal:

```bash
php artisan wa:listen          # add -vvv to log every scanned message
```

## Run with Docker

A `Dockerfile` and `docker-compose.yml` are included if you'd rather not install PHP or `wacli` on the host. The image bundles PHP 8.4, Composer, and a Linux build of `wacli`; compose runs four services off the same image:

- `wacli` — the `wacli sync` daemon that keeps `~/.wacli/wacli.db` populated.
- `wa-listen` — `php artisan wa:listen`.
- `queue` — the database queue worker.
- `app` — on-demand shell for ad-hoc artisan / composer / tests (in the `cli` profile, so it doesn't start with `up`).

The project source is bind-mounted at `/app` and the host's `~/.wacli` is bind-mounted into each container so the existing WhatsApp pairing is reused. **Stop any host-side `wacli sync` first** to avoid two daemons writing to the same SQLite file.

```bash
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate
docker compose up -d wacli wa-listen queue
```

Ad-hoc artisan, composer, or tests go through the `app` service:

```bash
docker compose run --rm app php artisan test --compact
docker compose run --rm app php artisan wa:status
docker compose logs -f wa-listen
```

The bundled `wacli` version is pinned by the `WACLI_VERSION` arg in the `Dockerfile` — the single source of truth for both local and production builds. To upgrade, bump that one line and rebuild; committing it to `main` rolls the new version out to production via the publish-image workflow. For a throwaway local test of another release without editing the `Dockerfile`, pass `--build-arg WACLI_VERSION=<version>` to `docker compose build`.

## Tests

```bash
php artisan test --compact
```

## Project structure

```
app/
  Bots/
    Bot.php               # manifest contract: what a bot contributes to the app
    BotAgent.php          # shared agent base (conversation binding, prompt skeleton)
    BotRegistry.php       # resolves the roster from config/bots.php
    Yaarpool/             # one folder per bot
      YaarpoolAgent.php
      YaarpoolBot.php     # the manifest
      Tools/ Models/ Enums/ Http/Controllers/ Console/
  Http/Controllers/       # shared: auth, admin dashboard, failed jobs
  Models/User.php         # shared: dashboard login + WhatsApp sender registry
config/
  bots.php                # the bot roster — the one place a bot is registered
  whatsapp-agent.php      # transport config; agent table derived from bots.php
routes/
  web.php                 # hub + shared admin, then each bot's route file
  bots/yaarpool.php
resources/views/
  welcome.blade.php       # the public hub listing every bot
  yaarpool/               # one folder per bot's views
```

## License

Licensed under the [GNU Affero General Public License v3.0 (AGPL-3.0-only)](https://www.gnu.org/licenses/agpl-3.0.html). The full text is in [`LICENSE`](LICENSE).
