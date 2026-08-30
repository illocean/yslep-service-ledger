@props([
    'types' => [], // array of IndexType enums
    'defaultType' => null,
    'class' => '',
])

@php
    $types = $types ?: [
        \App\Enums\IndexType::Formation,
        \App\Enums\IndexType::SocialApostolate,
        \App\Enums\IndexType::ParishInvolvement,
    ];
    
    $defaultType = $defaultType ?? $types[0];
    
    $typeLabels = [
        'formation' => 'Formation',
        'social_apostolate' => 'Social Apostolate',
        'parish_involvement' => 'Parish Involvement',
    ];
    
    $typeIcons = [
        'formation' => '
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12M8 11h8M8 7h8M4 15h16"/>
                <rect x="2" y="19" width="20" height="2" rx="1"/>
            </svg>',
        'social_apostolate' => '
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>',
        'parish_involvement' => '
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2v20M17 7H7M17 17H7M2 12h20"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>',
    ];
@endphp

<div class="quick-add-bar {{ $class }}" x-data="quickAddBar()" x-init="init()" data-type-labels="{{ json_encode($typeLabels) }}" data-type-icons="{{ json_encode($typeIcons) }}" data-default-type="{{ $defaultType->value }}">
    <!-- Keyboard shortcut hint -->
    <div class="quick-add-bar__shortcut" aria-hidden="true">
        <kbd class="kbd">⌘</kbd><kbd class="kbd">K</kbd>
        <span>Quick Add</span>
    </div>
    
    <!-- Type selector -->
    <div class="quick-add-bar__type-selector" role="group" aria-label="Select entry type">
        @foreach ($types as $type)
            @php
                $isActive = $type === $defaultType;
                $typeValue = $type->value;
            @endphp
            <button 
                type="button"
                class="quick-add-bar__type-btn {{ $isActive ? 'is-active' : '' }}"
                data-type="{{ $typeValue }}"
                @click="setType('{{ $typeValue }}')"
                :class="{ 'is-active': activeType === '{{ $typeValue }}' }"
                aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                title="{{ $typeLabels[$typeValue] ?? $type->label() }}"
            >
                {!! $typeIcons[$typeValue] ?? '' !!}
                <span class="sr-only">{{ $typeLabels[$typeValue] ?? $type->label() }}</span>
            </button>
        @endforeach
    </div>
    
    <!-- Primary action -->
    <button 
        type="button"
        class="quick-add-bar__primary-btn primary-button"
        @click="openModal()"
        aria-haspopup="dialog"
        aria-label="Add new {{ $typeLabels[$defaultType->value] ?? $defaultType->label() }} entry"
    >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        <span>Add Entry</span>
    </button>
    
    <!-- Sync status indicator -->
    <div class="quick-add-bar__sync-status" x-data="syncStatus()" x-init="init()">
        <span class="sync-status__indicator" :class="{ 'is-syncing': isSyncing, 'has-error': hasError }" aria-hidden="true"></span>
        <span class="sync-status__text" x-text="statusText"></span>
    </div>
</div>

