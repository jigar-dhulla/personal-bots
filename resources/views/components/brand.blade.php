@props(['chipClass' => 'h-9 w-9', 'iconClass' => 'h-4.5 w-4.5', 'nameClass' => 'text-lg'])

<span class="flex {{ $chipClass }} items-center justify-center rounded-xl border border-ink/10 bg-saffron text-ink shadow-card">
    <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM7 9h10v2H7V9zm6 5H7v-2h6v2zm4-6H7V6h10v2z"/></svg>
</span>
<span class="{{ $nameClass }} tracking-tight">{{ config('app.name') }}</span>
