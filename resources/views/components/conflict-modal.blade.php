@props([
    'conflicts' => [], // array of conflict objects
    'onResolve' => null, // callback: function(conflictId, resolution)
    'class' => '',
])

@php
    $resolutionLabels = [
        'vault' => 'Accept Vault Version',
        'db' => 'Keep Database Version',
        'merge' => 'Merge (Manual)',
    ];
    
    $resolutionDescriptions = [
        'vault' => 'Use the version from your Obsidian vault. Database changes will be overwritten.',
        'db' => 'Keep the version in the database. Vault changes will be overwritten on next sync.',
        'merge' => 'Manually combine changes. You will be taken to an edit form.',
    ];
    
    $resolutionIcons = [
        'vault' => '
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3"/>
                <path d="M21 16V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3"/>
                <path d="M4 12H2"/>
                <path d="M22 12H20"/>
            </svg>',
        'db' => '
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M3 5v14"/>
                <path d="M21 5v14"/>
                <ellipse cx="12" cy="19" rx="9" ry="3"/>
            </svg>',
        'merge' => '
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12M8 11h8M8 7h8M4 15h16"/>
                <rect x="2" y="19" width="20" height="2" rx="1"/>
            </svg>',
    ];
    
    $fieldLabels = [
        'served_on' => 'Date',
        'date' => 'Date',
        'cycle_code' => 'Cycle Code',
        'module_code' => 'Module Code',
        'title' => 'Title',
        'activity' => 'Activity',
        'role_in_activity' => 'Role in Activity',
        'about' => 'Description',
        'time_start' => 'Time In',
        'time_end' => 'Time Out',
        'hours' => 'Hours',
    ];
@endphp

<div 
    class="conflict-modal-overlay {{ $class }}"
    x-data="conflictModal()"
    x-init="init()"
    x-show="isOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    role="dialog"
    aria-modal="true"
    aria-labelledby="conflict-modal-title"
    @keydown.escape.window="close()"
    @click.self="close()"
    data-field-labels="{{ json_encode($fieldLabels) }}"
    data-conflicts="{{ json_encode($conflicts) }}"
    data-conflicts-resolve-url="{{ url('conflicts/resolve') }}"
>
    <div class="conflict-modal" role="document">
        <!-- Header -->
        <header class="conflict-modal__header">
            <div class="conflict-modal__icon" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div>
                <h2 id="conflict-modal-title" class="conflict-modal__title">Sync Conflict Detected</h2>
                <p class="conflict-modal__subtitle">
                    <span x-text="conflicts.length"></span> entr<span x-text="conflicts.length === 1 ? 'y has' : 'ies have'"></span> conflicting changes between the database and your Obsidian vault.
                </p>
            </div>
        </header>
        
        <!-- Conflicts List -->
        <div class="conflict-modal__body" x-show="conflicts.length > 0">
            <template x-for="conflict in conflicts" :key="conflict.id">
                <div class="conflict-card">
                    <div class="conflict-card__header">
                        <div class="conflict-card__type">
                            <span class="entry-type-badge" x-text="formatType(conflict.type)"></span>
                            <span class="conflict-card__id" x-text="'#' + conflict.id.slice(0, 8)"></span>
                        </div>
                        <div class="conflict-card__timestamp" x-text="formatDate(conflict.detected_at)"></div>
                    </div>
                    
                    <div class="conflict-card__diff">
                        <template x-for="(diff, field) in conflict.diffs" :key="field">
                            <div class="diff-row">
                                <div class="diff-field">
                                    <span class="diff-field__label" x-text="getFieldLabel(field)"></span>
                                    <span class="diff-field__name" x-text="field"></span>
                                </div>
                                
                                <div class="diff-values">
                                    <div class="diff-value diff-value--vault">
                                        <span class="diff-value__source">Vault</span>
                                        <span class="diff-value__content" x-text="diff.vault ?? '(empty)'"></span>
                                    </div>
                                    
                                    <div class="diff-separator" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="5" y1="12" x2="19" y2="12"/>
                                        </svg>
                                    </div>
                                    
                                    <div class="diff-value diff-value--db">
                                        <span class="diff-value__source">Database</span>
                                        <span class="diff-value__content" x-text="diff.db ?? '(empty)'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Resolution Options -->
                    <div class="conflict-card__resolution">
                        <label class="resolution-option">
                            <input 
                                type="radio" 
                                name="resolution_@{{ conflict.id }}"
                                value="vault"
                                x-model="resolutions[conflict.id]"
                            >
                            <div class="resolution-option__content">
                                <div class="resolution-option__icon">{{ $resolutionIcons['vault'] }}</div>
                                <div>
                                    <span class="resolution-option__label">{{ $resolutionLabels['vault'] }}</span>
                                    <span class="resolution-option__desc">{{ $resolutionDescriptions['vault'] }}</span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="resolution-option">
                            <input 
                                type="radio" 
                                name="resolution_@{{ conflict.id }}"
                                value="db"
                                x-model="resolutions[conflict.id]"
                            >
                            <div class="resolution-option__content">
                                <div class="resolution-option__icon">{{ $resolutionIcons['db'] }}</div>
                                <div>
                                    <span class="resolution-option__label">{{ $resolutionLabels['db'] }}</span>
                                    <span class="resolution-option__desc">{{ $resolutionDescriptions['db'] }}</span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="resolution-option">
                            <input 
                                type="radio" 
                                name="resolution_@{{ conflict.id }}"
                                value="merge"
                                x-model="resolutions[conflict.id]"
                            >
                            <div class="resolution-option__content">
                                <div class="resolution-option__icon">{{ $resolutionIcons['merge'] }}</div>
                                <div>
                                    <span class="resolution-option__label">{{ $resolutionLabels['merge'] }}</span>
                                    <span class="resolution-option__desc">{{ $resolutionDescriptions['merge'] }}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </template>
        </div>
        
        <!-- Empty State -->
        <div class="conflict-modal__empty" x-show="conflicts.length === 0">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <p>All conflicts resolved.</p>
        </div>
        
        <!-- Actions -->
        <div class="conflict-modal__actions">
            <button 
                type="button"
                class="button button--ghost"
                @click="close()"
                :disabled="isResolving"
            >
                Cancel
            </button>
            
            <template x-if="conflicts.length > 0">
                <button 
                    type="button"
                    class="button button--primary"
                    @click="applyResolutions()"
                    :disabled="!allResolved || isResolving"
                >
                    <span x-show="!isResolving">Apply Resolutions</span>
                    <span x-show="isResolving" class="flex items-center gap-2">
                        <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                        </svg>
                        Applying…
                    </span>
                </button>
            </template>
        </div>
    </div>
</div>

