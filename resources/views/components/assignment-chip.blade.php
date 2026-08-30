@props([
    'variant' => 'saved', // saved, locked, complete, conflict, custom
    'label' => '',
    'icon' => null,
    'href' => null,
    'class' => '',
    'title' => null,
])

@php
    $baseClasses = 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] transition-colors';
    
    $variantClasses = match ($variant) {
        'saved' => 'bg-stone-100 text-stone-700 border border-stone-200',
        'locked' => 'bg-amber-50 text-amber-800 border border-amber-200',
        'complete' => 'bg-emerald-50 text-emerald-800 border border-emerald-200',
        'conflict' => 'bg-rose-50 text-rose-800 border border-rose-200 animate-pulse-subtle',
        'custom' => '',
        default => 'bg-stone-100 text-stone-700 border border-stone-200',
    };
    
    $iconSvg = $icon ?? '
        <svg class="assignment-chip__dot" width="6" height="6" viewBox="0 0 6 6" fill="currentColor" aria-hidden="true">
            <circle cx="3" cy="3" r="3"/>
        </svg>
    ';
@endphp

@if ($href)
    <a href="{{ $href }}" class="{{ $baseClasses }} {{ $variantClasses }} {{ $class }}" {{ $title ? 'title="' . $title . '"' : '' }}>
        {!! $iconSvg !!}
        <span>{{ $label }}</span>
    </a>
@else
    <span class="{{ $baseClasses }} {{ $variantClasses }} {{ $class }}" {{ $title ? 'title="' . $title . '"' : '' }}>
        {!! $iconSvg !!}
        <span>{{ $label }}</span>
    </span>
@endif