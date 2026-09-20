<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }} — one WhatsApp number, many bots</title>
        <meta name="description" content="A small collection of WhatsApp bots sharing a single number. Mention the one you need and it answers in the chat.">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-cream text-ink antialiased">
        <div class="mx-auto flex min-h-screen max-w-4xl flex-col px-6 py-10">
            <nav class="flex flex-wrap items-center gap-4">
                <span class="flex items-center gap-2.5 font-display font-extrabold">
                    <x-brand name-class="text-xl" />
                </span>

                @if ($whatsappInviteUrl)
                    <a href="{{ $whatsappInviteUrl }}" target="_blank" rel="noopener" class="ml-auto rounded-full bg-emerald-500 px-5 py-2 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                        Message on WhatsApp
                    </a>
                @endif
            </nav>

            <header class="mt-16">
                <h1 class="font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                    One WhatsApp number.<br><span class="text-emerald-700">Many bots.</span>
                </h1>
                <p class="mt-5 max-w-2xl text-lg opacity-80">
                    Each bot below listens in its own chats and answers when you mention it. Add the number to a group,
                    mention the bot you need, and it does the rest — no app, no forms, no menus.
                </p>
            </header>

            <section class="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @forelse ($bots as $bot)
                    @php($publicRoute = $bot->key().'.home')

                    <div class="flex flex-col rounded-3xl border border-ink/10 bg-white p-6 shadow-card">
                        <h2 class="font-display text-xl font-extrabold tracking-tight">{{ $bot->name() }}</h2>
                        <p class="mt-2 flex-1 text-sm opacity-75">{{ $bot->tagline() }}</p>

                        @if (Route::has($publicRoute))
                            <a href="{{ route($publicRoute) }}" class="mt-5 inline-flex items-center gap-1.5 self-start text-sm font-bold text-emerald-700 transition hover:text-emerald-800">
                                Learn more
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        @endif
                    </div>
                @empty
                    <p class="rounded-3xl border border-dashed border-ink/20 px-6 py-8 text-center text-sm opacity-70 sm:col-span-2">
                        No bots are registered yet.
                    </p>
                @endforelse
            </section>

            <footer class="mt-auto flex flex-wrap items-center gap-4 pt-16 text-sm opacity-70">
                <p>Built with Laravel &amp; the Laravel AI SDK.</p>
                <a href="https://github.com/jigar-dhulla/yaarpool-whatsapp-agent" target="_blank" rel="noopener" class="ml-auto font-bold transition hover:text-emerald-700">
                    Source on GitHub
                </a>
            </footer>
        </div>
    </body>
</html>
