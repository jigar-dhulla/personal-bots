<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        <title>Log in — {{ config('app.name') }}</title>
        <link rel="icon" href="/logo.svg" type="image/svg+xml">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-cream text-ink antialiased">
        <div class="flex min-h-svh items-center justify-center px-6 py-10">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="flex items-center justify-center gap-2.5 font-display font-extrabold">
                    <x-brand chip-class="h-10 w-10" icon-class="h-5 w-5" name-class="text-xl" />
                </a>

                <div class="mt-8 rounded-3xl border border-ink/10 bg-white p-8 shadow-card">
                    <h1 class="font-display text-2xl font-extrabold tracking-tight">Log in</h1>

                    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="block text-sm font-bold">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                                class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            @error('email')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-bold">Password</label>
                            <input id="password" type="password" name="password" required autocomplete="current-password"
                                class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            @error('password')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="w-full rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                            Log in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
