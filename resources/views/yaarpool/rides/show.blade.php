<x-admin.layout title="Ride #{{ $ride->id }}">
    <a href="{{ route('yaarpool.rides.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold opacity-70 transition hover:opacity-100">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
        Back to rides
    </a>

    <header class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $ride->type === \App\Bots\Yaarpool\Enums\RideType::Offer ? 'bg-emerald-100 text-emerald-800' : 'bg-saffron/40 text-ink' }}">
                    {{ $ride->type === \App\Bots\Yaarpool\Enums\RideType::Offer ? 'Offer' : 'Request' }}
                </span>
                <span class="text-xs font-mono opacity-50">#{{ $ride->id }}</span>
            </div>
            <h1 class="mt-2 min-w-0 break-words font-display text-3xl font-extrabold tracking-tight">
                {{ $ride->from_location }} → {{ $ride->to_location }}
            </h1>
            <p class="mt-1.5 text-sm opacity-70">
                {{ $ride->sender_name ?? 'Unknown sender' }}
                @if ($ride->chat_jid)
                    · <span class="font-mono text-xs">{{ $ride->chat_jid }}</span>
                @endif
            </p>
        </div>

        <form method="POST" action="{{ route('yaarpool.rides.destroy', $ride) }}" class="shrink-0" onsubmit="return confirm('Cancel ride #{{ $ride->id }} ({{ $ride->from_location }} → {{ $ride->to_location }})?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-white px-5 py-2.5 text-sm font-bold text-rose-600 shadow-card transition hover:-translate-y-0.5 hover:bg-rose-50 hover:shadow-card-lg">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Cancel ride
            </button>
        </form>
    </header>

    <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-card">
            <p class="text-xs font-bold uppercase tracking-wide opacity-50">When</p>
            <p class="mt-1.5 font-display text-lg font-bold">{{ $ride->when_text }}</p>
            <p class="mt-1 text-sm opacity-70">{{ $ride->departs_at->format('D, j M Y · H:i') }}</p>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-card">
            <p class="text-xs font-bold uppercase tracking-wide opacity-50">Seats</p>
            @if ($ride->type === \App\Bots\Yaarpool\Enums\RideType::Offer)
                <p class="mt-1.5 font-display text-lg font-bold">
                    {{ $ride->seatsAvailable() === 0 ? 'Full' : $ride->seatsAvailable().' of '.$ride->seats.' seats left' }}
                </p>
            @else
                <p class="mt-1.5 font-display text-lg font-bold">{{ $ride->seats }} {{ Str::plural('seat', $ride->seats) }} needed</p>
            @endif
        </div>

        @if ($ride->price_per_seat)
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-card">
                <p class="text-xs font-bold uppercase tracking-wide opacity-50">Price</p>
                <p class="mt-1.5 font-display text-lg font-bold">{{ $ride->price_per_seat }} / seat</p>
            </div>
        @endif

        @if ($ride->vehicle)
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-card">
                <p class="text-xs font-bold uppercase tracking-wide opacity-50">Vehicle</p>
                <p class="mt-1.5 font-display text-lg font-bold">{{ $ride->vehicle }}</p>
            </div>
        @endif
    </div>

    @if ($ride->notes)
        <div class="mt-4 rounded-2xl border border-ink/10 bg-white p-5 shadow-card">
            <p class="text-xs font-bold uppercase tracking-wide opacity-50">Notes</p>
            <p class="mt-1.5 text-sm break-words">{{ $ride->notes }}</p>
        </div>
    @endif

    @if ($ride->type === \App\Bots\Yaarpool\Enums\RideType::Offer)
        <div class="mt-4 rounded-2xl border border-ink/10 bg-white p-5 shadow-card">
            <p class="text-xs font-bold uppercase tracking-wide opacity-50">Passengers</p>
            @if ($ride->passengers->isEmpty())
                <p class="mt-1.5 text-sm opacity-70">No one has joined this ride yet.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($ride->passengers as $passenger)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 break-words font-medium">{{ $passenger->sender_name ?? 'Unknown passenger' }}</span>
                            <span class="shrink-0 opacity-70">{{ $passenger->seats }} {{ Str::plural('seat', $passenger->seats) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</x-admin.layout>
