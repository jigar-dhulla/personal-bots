<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <title>Swiggy login — {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-cream text-ink antialiased">
        <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-4 px-6 py-10">
            <h1 class="font-display text-2xl font-extrabold">{{ $succeeded ? 'You are logged in' : 'Login did not finish' }}</h1>
            <p>{{ $status }}</p>
            <p class="text-sm opacity-70">You can close this tab and go back to WhatsApp.</p>
        </main>
    </body>
</html>
