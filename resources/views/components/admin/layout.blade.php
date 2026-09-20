@props(['title'])

@php($navLinkClasses = 'rounded-full px-4 py-2 text-sm font-bold transition')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title }} — {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-cream text-ink antialiased">
        <div class="mx-auto max-w-5xl px-6 py-10">
            <nav class="flex flex-wrap items-center gap-4 rounded-3xl border border-ink/10 bg-white px-5 py-3.5 shadow-card">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-display font-extrabold">
                    <x-brand />
                </a>

                <div class="flex flex-wrap items-center gap-1">
                    <a href="{{ route('admin.dashboard') }}" class="{{ $navLinkClasses }} {{ request()->routeIs('admin.dashboard') ? 'bg-emerald-500 text-white' : 'opacity-70 hover:bg-ink/5 hover:opacity-100' }}">Dashboard</a>
                    <a href="{{ route('failed-jobs.index') }}" class="{{ $navLinkClasses }} {{ request()->routeIs('failed-jobs.*') ? 'bg-emerald-500 text-white' : 'opacity-70 hover:bg-ink/5 hover:opacity-100' }}">Failed jobs</a>

                    @foreach ($bots as $bot)
                        @foreach ($bot->adminLinks() as $link)
                            <a href="{{ route($link['route']) }}"
                               title="{{ $bot->name() }}"
                               class="{{ $navLinkClasses }} {{ request()->routeIs($link['pattern']) ? 'bg-emerald-500 text-white' : 'opacity-70 hover:bg-ink/5 hover:opacity-100' }}">{{ $link['label'] }}</a>
                        @endforeach
                    @endforeach
                </div>

                <div class="ml-auto flex items-center gap-4">
                    <p class="text-sm font-medium opacity-70">{{ auth()->user()->name }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-ink/10 bg-white px-4 py-2 text-sm font-bold shadow-card transition hover:-translate-y-0.5 hover:shadow-card-lg">
                            Log out
                        </button>
                    </form>
                </div>
            </nav>

            @if (session('status'))
                <div class="mt-6 rounded-2xl border border-emerald-600/20 bg-emerald-100 px-5 py-3 text-sm font-medium text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <main class="mt-8">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
