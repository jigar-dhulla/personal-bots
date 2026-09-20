<x-admin.layout title="Dashboard">
    <h1 class="font-display text-3xl font-extrabold tracking-tight">Dashboard</h1>

    <h2 class="mt-8 text-xs font-bold uppercase tracking-wider opacity-60">Queue</h2>

    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-admin.stat-card
            label="Failed jobs"
            :value="$failedJobsCount"
            route="failed-jobs.index"
            :value-class="$failedJobsCount > 0 ? 'text-rose-600' : 'text-emerald-600'"
            :note="$failedJobsCount === 0 ? 'Queue is healthy — nothing waiting for a retry.' : 'Review and retry from the failed jobs page.'" />

        <x-admin.stat-card
            label="Queued jobs"
            :value="$queuedJobsCount"
            note="Jobs waiting to be processed." />
    </div>

    @forelse ($botSections as $section)
        <h2 class="mt-10 text-xs font-bold uppercase tracking-wider opacity-60">{{ $section['name'] }}</h2>

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($section['cards'] as $card)
                <x-admin.stat-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :route="$card['route']"
                    :note="$card['note']" />
            @endforeach
        </div>
    @empty
        <p class="mt-10 rounded-3xl border border-dashed border-ink/20 px-6 py-8 text-center text-sm opacity-70">
            No bots are registered yet. Add one to <code class="font-mono">config/bots.php</code>.
        </p>
    @endforelse
</x-admin.layout>
