# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This repo hosts a set of personal WhatsApp bots sharing **one phone number**. Inbound messages are read from a local `wacli` SQLite store and dispatched to every bot whose scope (chats + groups) contains the chat JID and whose triggers match the message body.

Each bot owns a vertical slice under `app/Bots/<Name>/` — agent, tools, models, enums, controllers, commands — and is registered once in `config/bots.php`. Anything outside `app/Bots/<Name>/` is shared and must stay bot-agnostic.

**Yaarpool** (`app/Bots/Yaarpool/`) is the ridesharing bot and currently the only one. Do not add bot-specific code to shared namespaces (`App\Models`, `App\Http\Controllers`, `App\Enums`, top-level views) — put it in the bot's slice.

WhatsApp transport is provided by the `jigar-dhulla/laravel-whatsapp-ai-agent` package. Never edit the vendor source — instead, treat the package as a contract that must support:

- reading inbound WhatsApp messages and routing them to a registered agent,
- exposing the chat JID and sender JID to the agent (currently via the `RemembersWhatsAppConversations` trait) so tools can scope by chat and enforce ownership,
- injecting recent chat history into the LLM context,
- sending the agent's reply back to WhatsApp.

If this app needs behaviour the package doesn't support, raise it as a feature request against the package rather than patching `vendor/`.

## Bot Architecture

- `App\Bots\Bot` — the manifest contract. Declares a bot's key, name, tagline, agent class, artisan commands, admin nav links and dashboard cards. Everything else is derived from `key()` by convention: env keys (`<KEY>_TRIGGERS` / `_CHATS` / `_GROUPS`, upper-cased), route file (`routes/bots/<key>.php`, loaded when it exists), landing page (the `<key>.home` route, linked from the hub when defined), URLs (`/<key>`, `/admin/<key>/…`) and views (`resources/views/<key>/`). Must be constructible with no arguments (`config/whatsapp-agent.php` instantiates it before the container boots).
- `App\Bots\BotAgent` — abstract agent base. Uses `RemembersWhatsAppConversations` (which sets `$chatJid` / `$senderJid` before `tools()` is called and injects chat history), registers the sender via `User::registerFromWhatsApp()`, and assembles `instructions()` as: persona → context (current date/time + configured triggers) → guidance → `Rules:` block (shared rules then the bot's own). Subclasses implement `persona()`, `guidance()`, `tools()`, and optionally `rules()`.
- `App\Bots\BotRegistry` — resolves the roster from `config('bots.registered')`. Read by the public hub, the admin nav composer (`AppServiceProvider`), the dashboard, and `routes/web.php`.
- `config/bots.php` — the single registration point. `config/whatsapp-agent.php` derives its `agents` table from it (one entry per bot, scoped by `<KEY>_TRIGGERS` / `_CHATS` / `_GROUPS`), so adding a bot needs no edit there.

To add a bot: create `app/Bots/<Name>/` with an agent extending `BotAgent`, its `Tools/`, a `<Name>Bot` manifest, `routes/bots/<name>.php` and `resources/views/<name>/` (both named after the manifest's `key()`); register the manifest in `config/bots.php`; add the three env vars. Discover JIDs with `php artisan wa:chats` / `wa:groups`; verify wiring with `wa:status`.

Models outside `App\Models` do not get automatic factory resolution — give them `#[UseFactory(SomeFactory::class)]` and set `protected $model` on the factory (see `app/Bots/Yaarpool/Models/`).

Route and view names are namespaced per bot (`yaarpool.rides.index`, `view('yaarpool.rides.index')`) so two bots can both have "settings" screens. Admin URLs live under `/admin/<bot>/…`, public pages under `/<bot>`.

## Yaarpool

- `App\Bots\Yaarpool\YaarpoolAgent` is the agent. Tools live under `app/Bots/Yaarpool/Tools/`, each implementing `Laravel\Ai\Contracts\Tool` with `name()`, `description()`, `schema(JsonSchema)`, and `handle(Request)`. The string returned from `handle()` becomes the WhatsApp reply.

| Tool | Purpose | Owner-only |
|---|---|---|
| `ride_request` | Persist a passenger looking for a lift | — |
| `ride_create` | Persist a driver publishing a trip | — |
| `ride_list` | List upcoming rides in the current chat | — |
| `ride_join` | Reserve seat(s) on someone else's offer (stored in `ride_passengers`; availability = seats − joined) | — |
| `ride_update` | Edit a ride the user previously posted | ✓ |
| `ride_delete` | Cancel a ride the user previously posted | ✓ |
| `route_travellers` | List chat members whose saved personal route matches (works with no ride posted) | — |
| `user_settings` | Save or show the sender's personal defaults — usual route, office hours, and commute days | — |

Owner-only tools refuse the call unless both `chat_jid` and `sender_jid` on the ride match the inbound message; rides in other chats are treated as not-found rather than surfaced.

Group defaults: each chat can have admin-configured defaults in the `group_settings` table (`App\Bots\Yaarpool\Models\GroupSetting`, keyed by `chat_jid`) — a `default_from_location` (required) and an optional `default_to_location`. `ride_create` / `ride_request` make `from`/`to` optional in their schema and fall back to these defaults via `GroupSetting::forChat()`, asking the user only when a location is neither stated nor defaulted. Admins manage them with `php artisan group:settings`.

Personal defaults: each sender can save their own commute profile in the `user_settings` table (`App\Bots\Yaarpool\Models\UserSetting`, keyed by `sender_jid`) — a `default_from_location`, optional `default_to_location`, `office_start_time` / `office_end_time`, and `office_days` (an array of weekdays, or `["daily"]`, normalized via `UserSetting::normalizeOfficeDays()` — handy for hybrid schedules). The `user_settings` tool reads/writes these via `UserSetting::forSender()`; calling it with no fields shows the saved defaults. Beyond filling in a sender's locations, a saved routine powers proactive matching: when a new ride is posted, `Ride::dailyTravellerSuggestion()` nudges the poster with chat members whose saved route and office days line up. Users manage their own defaults conversationally (there is no artisan command); admins can also view them in the dashboard (`App\Bots\Yaarpool\Http\Controllers\UserSettingsController`).

Datetime convention: the LLM emits `when_text` (verbatim user phrasing, kept for manual verification) plus a parsed `departs_at` (ISO-8601, NOT NULL). The current date is injected into the agent's instructions so relative phrases like "tomorrow" resolve correctly. Schemas use `->format('date-time')`; handlers read via `$request->date('departs_at')` and catch `Carbon\Exceptions\InvalidFormatException` to return a clarifying message.

To add a tool: create the class under `app/Bots/Yaarpool/Tools/` and register it in `YaarpoolAgent::tools()` (pass `chatJid` / `senderJid` if it needs scoping).

## Key Commands

| Task | Command |
|---|---|
| Full dev stack (serve + queue + pail + vite) | `composer run dev` |
| Run tests | `php artisan test --compact` |
| Run a single test | `php artisan test --compact --filter=testName` |
| Create a test | `php artisan make:test --pest SomeFeatureTest` |
| Create an agent | `php artisan make:agent <Name>` (scaffolds into `app/Ai/Agents/`; move it into the bot's slice under `app/Bots/<Name>/` and extend `App\Bots\BotAgent`) |
| Format PHP (required before finalizing) | `vendor/bin/pint --dirty --format agent` |
| WhatsApp listener daemon | `php artisan wa:listen` (`-vvv` shows scanned messages, `--once` for a single iteration) |
| WhatsApp status / JID discovery | `php artisan wa:status` / `wa:chats` / `wa:groups` |
| View/set a group's default origin & destination (Yaarpool) | `php artisan group:settings [chat] [--from=] [--to=] [--clear]` |
| Register a dashboard user (no public sign-up) | `php artisan user:register [name] [email]` (prompts for password) |
| Tail logs | `php artisan pail` |

## Datastores

- **App DB**: SQLite at `database/database.sqlite` — also backs sessions, cache, and the queue (`QUEUE_CONNECTION=database`).
- **wacli DB**: SQLite at `~/.wacli/wacli.db` (`WA_WACLI_DATABASE`). Read-only from this app; populated externally by `wacli sync`.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
