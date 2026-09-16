@props(['label', 'value', 'note' => '', 'route' => null, 'valueClass' => ''])

@php
    $classes = 'block rounded-3xl border border-ink/10 bg-white p-6 shadow-card'
        .($route ? ' transition hover:-translate-y-0.5 hover:shadow-card-lg' : '');
@endphp

@if ($route)
    <a href="{{ route($route) }}" class="{{ $classes }}">
@else
    <div class="{{ $classes }}">
@endif
        <p class="text-xs font-bold uppercase tracking-wider opacity-60">{{ $label }}</p>
        <p class="mt-2 font-display text-4xl font-extrabold {{ $valueClass }}">{{ $value }}</p>
        @if ($note !== '')
            <p class="mt-2 text-sm opacity-70">{{ $note }}</p>
        @endif
@if ($route)
    </a>
@else
    </div>
@endif
