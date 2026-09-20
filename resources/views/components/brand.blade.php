@props(['chipClass' => 'h-9 w-9', 'iconClass' => 'h-4.5 w-4.5', 'nameClass' => 'text-lg'])

<span class="flex {{ $chipClass }} items-center justify-center rounded-xl border border-ink/10 bg-saffron text-ink shadow-card">
    <x-logo :class="$iconClass" />
</span>
<span class="{{ $nameClass }} tracking-tight">{{ config('app.name') }}</span>
