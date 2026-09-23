@php($repositoryUrl = 'https://github.com/jigar-dhulla/personal-bots')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Instamart bot — {{ config('app.name') }}</title>

        <meta name="description" content="A private WhatsApp bot that shops on Swiggy Instamart for one person. Not open to the public; the code is open source, so fork it and run your own.">
        <meta name="robots" content="noindex">
        <link rel="icon" href="/logo.svg" type="image/svg+xml">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-cream text-ink antialiased">
        <div class="mx-auto flex min-h-screen max-w-2xl flex-col px-6 py-10">
            <nav>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 font-display font-extrabold">
                    <x-brand name-class="text-xl" />
                </a>
            </nav>

            <main class="mt-16 flex flex-col gap-14">
                <header class="flex flex-col gap-5">
                    <span class="self-start rounded-full border border-ink/15 px-3 py-1 text-xs font-bold uppercase tracking-wide opacity-70">
                        Private project
                    </span>

                    <h1 class="font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                        Instamart bot
                    </h1>

                    <p class="text-lg opacity-80">
                        A WhatsApp bot that shops on Swiggy Instamart: search, check stock, fill a cart and place an order,
                        all in plain language. It orders on <strong>one person's own Swiggy account</strong>.
                    </p>

                    <p class="text-lg opacity-80">
                        It is <strong>not open to anyone else</strong>. There is no sign-up, and it only answers in the
                        chats its owner has set up. Please don't message it expecting it to order for you.
                    </p>
                </header>

                <section class="flex flex-col gap-4 rounded-3xl border border-ink/10 bg-white p-6 shadow-card">
                    <h2 class="font-display text-xl font-extrabold tracking-tight">Want one of your own?</h2>

                    <p class="opacity-80">
                        The code is open source. Fork the project, log in with your own Swiggy account, and point it at
                        your own WhatsApp number. Your bot, your account, your orders.
                    </p>

                    <div class="flex flex-wrap items-center gap-4">
                        <a href="{{ $repositoryUrl }}" target="_blank" rel="noopener" class="rounded-full bg-emerald-500 px-5 py-2 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                            Fork on GitHub
                        </a>
                        <a href="{{ $repositoryUrl }}/blob/main/app/Bots/Instamart/RUNBOOK.md" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-bold text-emerald-700 transition hover:text-emerald-800">
                            Read the runbook
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </section>
            </main>

            <footer class="mt-auto pt-16 text-sm opacity-60">
                Not affiliated with or endorsed by Swiggy. Swiggy and Instamart are trademarks of their respective owner.
            </footer>
        </div>
    </body>
</html>
