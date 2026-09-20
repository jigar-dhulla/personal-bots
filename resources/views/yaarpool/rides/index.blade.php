<x-admin.layout title="Rides">
    <header class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="font-display text-3xl font-extrabold tracking-tight">Rides</h1>
        <p class="text-sm opacity-70">{{ $rides->count() }} upcoming {{ Str::plural('ride', $rides->count()) }}</p>
    </header>

    <form method="GET" action="{{ route('yaarpool.rides.index') }}" class="mt-8 flex flex-col gap-4 rounded-3xl border border-ink/10 bg-white p-6 shadow-card sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="search" class="block text-sm font-bold">Search</label>
            <input id="search" type="text" name="search" value="{{ $search }}" placeholder="Location, chat, or name"
                class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div>
            <label for="type" class="block text-sm font-bold">Type</label>
            <select id="type" name="type" class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:w-40">
                <option value="" @selected($type === null)>All</option>
                @foreach (\App\Bots\Yaarpool\Enums\RideType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($type === $case)>{{ ucfirst($case->value) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                Filter
            </button>
            @if ($type !== null || $search !== '')
                <a href="{{ route('yaarpool.rides.index') }}" class="inline-flex items-center gap-2 rounded-full border border-ink/10 bg-white px-5 py-2.5 text-sm font-bold shadow-card transition hover:-translate-y-0.5 hover:shadow-card-lg">
                    Clear
                </a>
            @endif
        </div>
    </form>

    @if ($rides->isEmpty())
        <div class="mt-8 rounded-3xl border border-ink/10 bg-white p-12 text-center shadow-card">
            <p class="font-display text-xl font-bold">No upcoming rides</p>
            <p class="mt-2 text-sm opacity-70">Offers and requests posted in WhatsApp will show up here once they're due.</p>
        </div>
    @else
        <div class="mt-8 space-y-4">
            @foreach ($rides as $ride)
                <a href="{{ route('yaarpool.rides.show', $ride) }}" class="block rounded-2xl border border-ink/10 bg-white p-5 shadow-card transition hover:-translate-y-0.5 hover:border-emerald-500/40 hover:shadow-card-lg">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $ride->type === \App\Bots\Yaarpool\Enums\RideType::Offer ? 'bg-emerald-100 text-emerald-800' : 'bg-saffron/40 text-ink' }}">
                                    {{ $ride->type === \App\Bots\Yaarpool\Enums\RideType::Offer ? 'Offer' : 'Request' }}
                                </span>
                                <span class="text-xs font-mono opacity-50">#{{ $ride->id }}</span>
                            </div>
                            <h2 class="mt-1.5 min-w-0 break-words font-display text-lg font-bold">
                                {{ $ride->from_location }} → {{ $ride->to_location }}
                            </h2>
                            <p class="mt-1 text-sm opacity-70">
                                {{ $ride->sender_name ?? 'Unknown sender' }}
                                @if ($ride->chat_jid)
                                    · <span class="font-mono text-xs">{{ $ride->chat_jid }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="shrink-0 text-left sm:text-right">
                            <p class="text-sm font-bold">
                                {{ $ride->when_text }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm opacity-80">
                        @if ($ride->type === \App\Bots\Yaarpool\Enums\RideType::Offer)
                            <span>{{ $ride->seatsAvailable() === 0 ? 'Full' : $ride->seatsAvailable().' of '.$ride->seats.' seats left' }}</span>
                        @else
                            <span>{{ $ride->seats }} {{ Str::plural('seat', $ride->seats) }} needed</span>
                        @endif

                        @if ($ride->price_per_seat)
                            <span>{{ $ride->price_per_seat }} / seat</span>
                        @endif

                        @if ($ride->vehicle)
                            <span>{{ $ride->vehicle }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-admin.layout>
