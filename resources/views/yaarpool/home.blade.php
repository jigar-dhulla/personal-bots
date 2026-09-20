<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Yaarpool — Carpool with your group, right inside WhatsApp</title>
        <meta name="description" content="Yaarpool is the carpool that lives in your group chat. Post a lift or ask for one in plain language on WhatsApp — no app to install.">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-cream text-ink antialiased">

        {{-- Background flourish --}}
        <div aria-hidden="true" class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-amber-300/30 blur-3xl"></div>
            <div class="absolute top-1/3 -left-40 h-96 w-96 rounded-full bg-emerald-300/30 blur-3xl"></div>
        </div>

        {{-- Reusable inline SVGs: a filled map-pin for the route motif, plus line icons for section chips --}}
        @php
            $pin = '<svg class="%s" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>';

            $iconPaths = [
                'bookmark' => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
                'car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
                'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
                'pencil' => '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
                'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
                'ban' => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
                'building' => '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
                'plane' => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 5.3 5.3c.4.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
                'mountain' => '<path d="M3 20 12 5l9 15z"/>',
                'refresh' => '<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/>',
                'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            ];

            $icon = fn (string $name, string $class = 'h-6 w-6'): string => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($iconPaths[$name] ?? '').'</svg>';
        @endphp

        <div class="relative">
            {{-- Nav --}}
            <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <a href="#top" class="flex items-center gap-2.5 font-display font-extrabold">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-ink/10 bg-saffron text-ink shadow-card">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
                    </span>
                    <span class="text-xl tracking-tight">Yaarpool</span>
                </a>
                <nav class="hidden items-center gap-8 text-sm font-medium sm:flex">
                    <a href="#how" class="transition hover:text-emerald-700">How it works</a>
                    <a href="#features" class="transition hover:text-emerald-700">Features</a>
                    <a href="#examples" class="transition hover:text-emerald-700">Examples</a>
                    <a href="{{ route('yaarpool.usage') }}" class="transition hover:text-emerald-700">How to use</a>
                </nav>
                <a href="{{ $whatsappInviteUrl ?? '#cta' }}" @if ($whatsappInviteUrl) target="_blank" rel="noopener" @endif class="rounded-full bg-emerald-500 px-5 py-2 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                    Get started
                </a>
            </header>

            {{-- Hero --}}
            <section class="mx-auto grid min-h-[calc(100svh-5rem)] max-w-6xl content-center items-center gap-14 px-6 py-12 lg:grid-cols-[1.1fr_1fr] lg:gap-8">
                <div>
                    <span class="inline-flex animate-fade-up items-center gap-2.5 rounded-full border border-ink/10 bg-white px-4 py-1.5 text-xs font-bold shadow-card">
                        <span class="flex items-center gap-1" aria-hidden="true">
                            <span class="h-2 w-2 rounded-full bg-saffron"></span>
                            <span class="w-4 border-t-2 border-dashed border-road"></span>
                            <span class="text-emerald-600">{!! sprintf($pin, 'h-3.5 w-3.5') !!}</span>
                        </span>
                        The carpool that lives in your group chat.
                    </span>

                    <h1 class="mt-7 animate-fade-up font-display text-5xl font-extrabold tracking-tight [animation-delay:100ms] sm:text-6xl lg:text-7xl">
                        Share rides
                        <span class="text-emerald-700">in your
                            <span class="relative inline-block whitespace-nowrap">
                            group chat
                            <svg class="absolute -bottom-4 left-0 w-full overflow-visible" viewBox="0 0 200 14" fill="none" preserveAspectRatio="none" aria-hidden="true">
                                <path d="M3 10c30-7 60 5 95-4 35-8 70 6 99-3" pathLength="100" stroke="currentColor" stroke-width="5" stroke-linecap="round" class="animate-draw text-saffron"/>
                            </svg>
                            </span>
                        </span>
                    </h1>

                    <p class="mt-7 max-w-md animate-fade-up text-lg leading-relaxed [animation-delay:200ms]">
                        Offer an empty seat or grab one in plain language. Yaarpool matches you with people you already know — and splits the fare.
                    </p>

                    <div class="mt-9 flex animate-fade-up flex-col gap-4 [animation-delay:300ms] sm:flex-row">
                        <a href="{{ $whatsappInviteUrl ?? '#cta' }}" @if ($whatsappInviteUrl) target="_blank" rel="noopener" @endif class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-500 px-7 py-3.5 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 1.67c2.2 0 4.27.86 5.82 2.41a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24Z"/></svg>
                            Add to WhatsApp group
                        </a>
                        <a href="#how" class="inline-flex items-center justify-center rounded-full border border-ink/10 bg-white px-7 py-3.5 text-sm font-bold shadow-card transition hover:-translate-y-0.5 hover:shadow-card-lg">
                            See how it works
                        </a>
                    </div>

                    <p class="mt-6 animate-fade-up text-xs font-medium opacity-70 [animation-delay:400ms]">Free to start · Works in any group · Add it in 30 seconds</p>
                </div>

                {{-- Chat screenshot --}}
                <div class="relative mx-auto w-full max-w-sm animate-fade-up [animation-delay:300ms]">
                    <div class="overflow-hidden rounded-3xl border border-ink/10 bg-[#ECE5DD] shadow-card-lg">
                        <div class="flex items-center gap-3 bg-emerald-600 px-4 py-3 text-white">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
                            </span>
                            <div class="leading-tight">
                                <p class="text-sm font-semibold">Office ride group</p>
                                <p class="text-xs text-emerald-100">You, Yaarpool, Riya +21</p>
                            </div>
                        </div>
                        <div class="space-y-3 px-4 py-5 text-sm">
                            <div class="ml-auto max-w-[80%] rounded-2xl rounded-tr-sm bg-[#D9FDD3] px-3 py-2 text-[#111b21] shadow-sm">
                                Driving from Andheri to BKC tomorrow at 9am, 2 seats free 🚙
                                <span class="mt-0.5 block text-right text-[10px] text-[#667781]">08:14</span>
                            </div>
                            <div class="max-w-[85%] rounded-2xl rounded-tl-sm bg-white px-3 py-2 text-[#111b21] shadow-sm">
                                <span class="text-xs font-semibold text-emerald-700">Yaarpool</span><br>
                                Got it! Posted your ride — Andheri → BKC, tomorrow 9:00 AM, 2 seats. I'll let people know. ✅
                                <span class="mt-0.5 block text-right text-[10px] text-[#667781]">08:14</span>
                            </div>
                            <div class="max-w-[85%] rounded-2xl rounded-tl-sm bg-white px-3 py-2 text-[#111b21] shadow-sm">
                                <span class="text-xs font-semibold text-rose-500">Riya</span><br>
                                anyone going towards BKC tmrw morning?
                                <span class="mt-0.5 block text-right text-[10px] text-[#667781]">08:31</span>
                            </div>
                            <div class="max-w-[85%] rounded-2xl rounded-tl-sm bg-white px-3 py-2 text-[#111b21] shadow-sm">
                                <span class="text-xs font-semibold text-emerald-700">Yaarpool</span><br>
                                Yes! There's a ride: <span class="font-medium">Andheri → BKC, 9:00 AM, 2 seats.</span> Want me to connect you? 🙌
                                <span class="mt-0.5 block text-right text-[10px] text-[#667781]">08:31</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- How it works --}}
            <section id="how" class="mx-auto max-w-6xl px-6 py-20">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-display text-4xl font-extrabold tracking-tight sm:text-5xl">As easy as texting a friend</h2>
                    <p class="mt-4 text-lg">No forms, no menus. Yaarpool understands what you mean.</p>
                </div>
                <div class="relative mt-16">
                    {{-- Route line connecting the stops (desktop) --}}
                    <div aria-hidden="true" class="absolute inset-x-[16.666%] top-7 hidden border-t-2 border-dashed border-road/50 md:block"></div>
                    <div class="grid gap-12 md:grid-cols-3 md:gap-6">
                        @php
                            $steps = [
                                ['1', 'bg-saffron', 'Add the bot to your group chat', 'Invite Yaarpool like any other contact. Takes about 30 seconds.'],
                                ['2', 'bg-emerald-400', 'Type your route and time', 'Text it naturally — "Driving to BKC tomorrow at 9 AM, 2 seats free."'],
                                ['3', 'bg-saffron', 'Match with a friend instantly', 'The bot connects drivers and passengers already in your group.'],
                            ];
                        @endphp
                        @foreach ($steps as [$num, $chip, $title, $body])
                            <div class="flex flex-col items-center text-center">
                                <span class="relative z-10 flex h-14 w-14 items-center justify-center rounded-full font-display text-xl font-extrabold text-ink shadow-card ring-4 ring-cream {{ $chip }}">{{ $num }}</span>
                                <div class="mt-6 w-full rounded-2xl border border-ink/10 bg-white p-6 shadow-card transition hover:-translate-y-1 hover:shadow-card-lg">
                                    <h3 class="font-display text-lg font-bold">{{ $title }}</h3>
                                    <p class="mt-2 text-sm leading-relaxed">{{ $body }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Features --}}
            <section id="features" class="mx-auto max-w-6xl px-6 py-20">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-display text-4xl font-extrabold tracking-tight sm:text-5xl">Everything a ride needs</h2>
                    <p class="mt-4 text-lg">One bot, every part of the carpool covered.</p>
                </div>
                <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $features = [
                            ['bookmark', 'bg-amber-100 text-amber-700', 'Call dibs on a seat', 'Drop your destination once. The bot keeps your request front-and-center so active drivers see it.'],
                            ['car', 'bg-emerald-100 text-emerald-700', 'Offer empty slots', 'Driving anyway? Tell the group your route and let people hop in to split costs.'],
                            ['clipboard', 'bg-amber-100 text-amber-700', 'Instant group boards', 'Type a quick question to fetch a clean, synced roster of all upcoming trips.'],
                            ['pencil', 'bg-emerald-100 text-emerald-700', 'No-fuss edits', 'Plans change. Update your timing or cancel entirely in plain text — the bot cleans up the board instantly.'],
                            ['shield', 'bg-amber-100 text-amber-700', 'Strict group isolation', 'What happens in your group stays there. Rides logged in Chat A never leak to Chat B.'],
                            ['ban', 'bg-emerald-100 text-emerald-700', 'Zero app footprint', 'No registrations, no passwords, no storage taken. It just works where you already talk.'],
                        ];
                    @endphp
                    @foreach ($features as [$name, $chip, $title, $body])
                        <div class="rounded-2xl border border-ink/10 bg-white p-6 shadow-card transition hover:-translate-y-1 hover:shadow-card-lg">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl border border-ink/10 {{ $chip }}">{!! $icon($name) !!}</span>
                            <h3 class="mt-4 font-display text-lg font-bold">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Privacy & Trust --}}
            <section class="mx-auto max-w-4xl px-6 py-12">
                <div class="flex flex-col items-center gap-6 rounded-3xl border border-ink/10 bg-white p-8 shadow-card sm:flex-row">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700" aria-hidden="true">{!! $icon('lock', 'h-7 w-7') !!}</span>
                    <div>
                        <h2 class="font-display text-lg font-bold">Is it safe? What about our privacy?</h2>
                        <p class="mt-2 text-sm leading-relaxed">
                            Yaarpool belongs in the closed groups you already trust — your office crew, college batch, or
                            society circle. It reads recent chat history to understand context, but <span class="font-bold">it only ever logs structural
                            ride data</span> — routes, times, and seats. Your casual banter stays yours. Zero cross-group leakage, zero public feeds.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Examples --}}
            <section id="examples" class="mx-auto max-w-4xl px-6 py-20">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-display text-4xl font-extrabold tracking-tight sm:text-5xl">Built for every kind of group</h2>
                    <p class="mt-4 text-lg">Whether it's the daily hustle or a weekend getaway.</p>
                </div>
                <div class="mt-12 grid gap-6 text-left sm:grid-cols-2">
                    @php
                        $useCases = [
                            ['building', 'Office Commutes', 'Driving from Andheri to BKC tomorrow at 9am, 2 seats free 🚙'],
                            ['plane', 'Airport Runs', 'Flight at midnight. Anyone heading towards the airport around 8 PM? Let\'s split the fare!'],
                            ['mountain', 'Weekend Trips', 'Heading down to Pune early Saturday morning. Got room for 3 people.'],
                            ['refresh', 'Quick Updates', 'Change my BKC ride to 9:30 AM instead — or just "Cancel my trip tomorrow".'],
                        ];
                    @endphp
                    @foreach ($useCases as [$name, $label, $quote])
                        <div class="rounded-2xl rounded-bl-sm border border-ink/10 bg-white p-5 shadow-card transition hover:-translate-y-1 hover:shadow-card-lg">
                            <span class="flex items-center gap-2 font-display text-sm font-bold uppercase tracking-wider">
                                <span class="text-emerald-600">{!! $icon($name, 'h-4 w-4') !!}</span>{{ $label }}
                            </span>
                            <p class="mt-2 text-sm italic">“{{ $quote }}”</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-10 text-center">
                    <a href="{{ route('yaarpool.usage') }}" class="inline-flex items-center justify-center gap-2 rounded-full border border-ink/10 bg-white px-7 py-3.5 text-sm font-bold shadow-card transition hover:-translate-y-0.5 hover:text-emerald-700 hover:shadow-card-lg">
                        See all the ways to use it
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                </div>
            </section>

            {{-- CTA --}}
            <section id="cta" class="mx-auto max-w-6xl px-6 pb-24">
                <div class="relative overflow-hidden rounded-3xl bg-ink px-8 py-16 text-center text-cream shadow-[0_24px_60px_-20px_rgb(245_158_11_/_0.55)]">
                    {{-- Route arriving at its destination --}}
                    <div aria-hidden="true" class="mx-auto mb-6 flex max-w-xs items-center gap-1">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-saffron"></span>
                        <span class="flex-1 border-t-2 border-dashed border-cream/25"></span>
                        <span class="shrink-0 text-saffron">{!! sprintf($pin, 'h-7 w-7') !!}</span>
                    </div>
                    <h2 class="relative font-display text-4xl font-extrabold tracking-tight sm:text-5xl">Add it to your group</h2>
                    <p class="relative mx-auto mt-5 max-w-md text-cream/80">
                        Stop wasting money on empty seats and surge-priced cabs. Add Yaarpool to your group chat in 30 seconds — 100% free to start.
                    </p>
                    <div class="relative mt-9 flex justify-center">
                        <a href="{{ $whatsappInviteUrl ?? '#' }}" @if ($whatsappInviteUrl) target="_blank" rel="noopener" @endif class="inline-flex items-center gap-2 rounded-full bg-saffron px-8 py-4 text-sm font-bold text-ink shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 1.67c2.2 0 4.27.86 5.82 2.41a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24Z"/></svg>
                            Add to WhatsApp group
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
                    <div class="flex flex-col items-center gap-4 sm:flex-row">
                        <a href="https://github.com/jigar-dhulla/personal-bots" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-ink/10 bg-white px-4 py-2 text-xs font-bold shadow-card transition hover:-translate-y-0.5 hover:shadow-card-lg">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                            Contribute on GitHub
                        </a>
                        <p>No strangers. Just yaars. © {{ date('Y') }}</p>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
