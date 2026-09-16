<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>How to use Yaarpool — Examples &amp; commands</title>
        <meta name="description" content="Learn how to use the Yaarpool WhatsApp bot. Real examples for offering a ride, asking for a lift, joining a seat, listing rides, and editing or cancelling your trips.">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-cream text-ink antialiased">

        {{-- Background flourish --}}
        <div aria-hidden="true" class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-amber-300/30 blur-3xl"></div>
            <div class="absolute top-1/3 -left-40 h-96 w-96 rounded-full bg-emerald-300/30 blur-3xl"></div>
        </div>

        @php
            $iconPaths = [
                'car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
                'bookmark' => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
                'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
                'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                'pencil' => '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
                'ban' => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
                'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 2v4"/><path d="M16 2v4"/><path d="m9 16 2 2 4-4"/>',
            ];

            $icon = fn (string $name, string $class = 'h-6 w-6'): string => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($iconPaths[$name] ?? '').'</svg>';

            // Each block: [icon, chip classes, title, one-line explainer, [user phrasings...]]
            $usage = [
                ['car', 'bg-emerald-100 text-emerald-700', 'Offer a ride', 'Driving somewhere anyway? Announce your trip and let people grab a seat.', [
                    'Driving from Andheri to BKC tomorrow at 9am, 2 seats free 🚙',
                    'Leaving for Pune early Saturday morning, room for 3.',
                    'Heading to the airport around 8 PM tonight, one seat going.',
                ]],
                ['bookmark', 'bg-amber-100 text-amber-700', 'Ask for a lift', 'Looking for a ride? Post where and when — the bot keeps your request visible to drivers.', [
                    'Anyone going towards BKC tomorrow morning?',
                    'Need a lift to the airport around 8 PM, happy to split the fare.',
                    'Looking for a ride to Pune this weekend.',
                ]],
                ['clipboard', 'bg-emerald-100 text-emerald-700', 'See what\'s posted', 'Fetch a clean list of the upcoming rides shared in your group.', [
                    'What rides are posted?',
                    'Show me lifts to BKC tomorrow.',
                    'Any trips to Pune this weekend?',
                ]],
                ['users', 'bg-amber-100 text-amber-700', 'Join a ride', 'Take a seat on someone else\'s offer. Mention the ride number when you have it.', [
                    'Count me in for ride #7',
                    'Book me 2 seats on the Andheri to BKC ride.',
                    'I\'ll join Riya\'s airport trip.',
                ]],
                ['pencil', 'bg-emerald-100 text-emerald-700', 'Edit your ride', 'Plans change. Update the time, seats, or price on a ride you posted.', [
                    'Change my BKC ride to 9:30 AM instead.',
                    'Update ride #7 — only 1 seat left now.',
                    'Make the fare ₹150 per person.',
                ]],
                ['ban', 'bg-amber-100 text-amber-700', 'Cancel your ride', 'Withdraw a trip you shared and the bot clears it from the board.', [
                    'Cancel my trip tomorrow.',
                    'Remove my Pune ride.',
                    'Withdraw ride #7.',
                ]],
                ['calendar', 'bg-emerald-100 text-emerald-700', 'Save your travel routine', 'Tell the bot your usual route, office hours, and which days you commute. It fills in your locations automatically and flags you when someone posts a matching ride.', [
                    'I usually travel from Wakad to Hinjewadi.',
                    'My office hours are 9am to 6pm, Mon/Wed/Fri.',
                    'I\'m only in office on Tuesday and Thursday.',
                    'What defaults have you saved for me?',
                ]],
            ];
        @endphp

        <div class="relative">
            {{-- Nav --}}
            <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <a href="{{ route('yaarpool.home') }}" class="flex items-center gap-2.5 font-display font-extrabold">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-ink/10 bg-saffron text-ink shadow-card">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
                    </span>
                    <span class="text-xl tracking-tight">Yaarpool</span>
                </a>
                <nav class="hidden items-center gap-8 text-sm font-medium sm:flex">
                    <a href="{{ route('yaarpool.home').'#how' }}" class="transition hover:text-emerald-700">How it works</a>
                    <a href="{{ route('yaarpool.home').'#features' }}" class="transition hover:text-emerald-700">Features</a>
                    <a href="{{ route('yaarpool.usage') }}" class="text-emerald-700 transition hover:text-emerald-800">How to use</a>
                </nav>
                <a href="{{ $whatsappInviteUrl ?? route('yaarpool.home').'#cta' }}" @if ($whatsappInviteUrl) target="_blank" rel="noopener" @endif class="rounded-full bg-emerald-500 px-5 py-2 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                    Get started
                </a>
            </header>

            {{-- Header --}}
            <section class="mx-auto max-w-3xl px-6 pt-12 pb-8 text-center">
                <span class="inline-flex animate-fade-up items-center gap-2 rounded-full border border-ink/10 bg-white px-4 py-1.5 text-xs font-bold shadow-card">
                    Just talk to it, naturally
                </span>
                <h1 class="mt-6 animate-fade-up font-display text-4xl font-extrabold tracking-tight [animation-delay:100ms] sm:text-5xl">
                    How to use <span class="text-emerald-700">Yaarpool</span>
                </h1>
                <p class="mx-auto mt-5 max-w-xl animate-fade-up text-lg leading-relaxed [animation-delay:200ms]">
                    There are no commands to memorise. Type into your group chat the way you'd text a friend — the bot
                    figures out the rest. Here are the things you can do, with real phrasings you can copy.
                </p>
            </section>

            {{-- Usage cards --}}
            <section class="mx-auto max-w-5xl px-6 py-10">
                <div class="grid gap-6 md:grid-cols-2">
                    @foreach ($usage as [$name, $chip, $title, $body, $examples])
                        <div class="flex flex-col rounded-2xl border border-ink/10 bg-white p-6 shadow-card transition hover:-translate-y-1 hover:shadow-card-lg">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-ink/10 {{ $chip }}">{!! $icon($name) !!}</span>
                                <h2 class="font-display text-lg font-bold">{{ $title }}</h2>
                            </div>
                            <p class="mt-3 text-sm leading-relaxed">{{ $body }}</p>
                            <div class="mt-4 space-y-2">
                                @foreach ($examples as $example)
                                    <p class="rounded-xl rounded-tl-sm bg-[#D9FDD3] px-3 py-2 text-sm text-[#111b21] shadow-sm">“{{ $example }}”</p>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Tip --}}
            <section class="mx-auto max-w-3xl px-6 pb-4">
                <div class="rounded-2xl border border-ink/10 bg-white p-6 text-sm leading-relaxed shadow-card">
                    <p class="font-display font-bold">A few things worth knowing</p>
                    <ul class="mt-3 space-y-2">
                        <li class="flex gap-2"><span class="text-emerald-600">•</span> You don't need exact wording. "Tomorrow 9ish", "sat morning", or "tonight" all work — the bot reads dates and times the way people write them.</li>
                        <li class="flex gap-2"><span class="text-emerald-600">•</span> Only the person who posted a ride can edit or cancel it. Everyone else just sees and joins.</li>
                        <li class="flex gap-2"><span class="text-emerald-600">•</span> Rides stay inside the group they were posted in — nothing leaks between chats.</li>
                        <li class="flex gap-2"><span class="text-emerald-600">•</span> Bot replies include a ride number (like <span class="font-medium">#7</span>) so you can refer to a specific ride when joining or editing.</li>
                    </ul>
                </div>
            </section>

            {{-- CTA --}}
            <section class="mx-auto max-w-6xl px-6 py-16">
                <div class="relative overflow-hidden rounded-3xl bg-ink px-8 py-14 text-center text-cream shadow-[0_24px_60px_-20px_rgb(245_158_11_/_0.55)]">
                    <h2 class="relative font-display text-3xl font-extrabold tracking-tight sm:text-4xl">Ready to try it?</h2>
                    <p class="relative mx-auto mt-4 max-w-md text-cream/80">
                        Add Yaarpool to your group chat and post your first ride in seconds.
                    </p>
                    <div class="relative mt-8 flex flex-col justify-center gap-4 sm:flex-row">
                        <a href="{{ $whatsappInviteUrl ?? '#' }}" @if ($whatsappInviteUrl) target="_blank" rel="noopener" @endif class="inline-flex items-center justify-center gap-2 rounded-full bg-saffron px-8 py-4 text-sm font-bold text-ink shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 1.67c2.2 0 4.27.86 5.82 2.41a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24Z"/></svg>
                            Add to WhatsApp group
                        </a>
                        <a href="{{ route('yaarpool.home') }}" class="inline-flex items-center justify-center rounded-full border border-cream/20 bg-white/5 px-8 py-4 text-sm font-bold text-cream transition hover:-translate-y-0.5 hover:bg-white/10">
                            Back to home
                        </a>
                    </div>
                </div>
            </section>

            {{-- Footer --}}
            <footer class="mx-auto max-w-6xl px-6 py-8">
                <div class="flex flex-col items-center justify-between gap-4 border-t border-ink/10 pt-8 text-sm sm:flex-row">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-ink/10 bg-saffron text-ink">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
                        </span>
                        <span class="font-display font-bold">Yaarpool</span>
                    </div>
                    <p>No strangers. Just yaars. © {{ date('Y') }}</p>
                </div>
            </footer>
        </div>
    </body>
</html>
