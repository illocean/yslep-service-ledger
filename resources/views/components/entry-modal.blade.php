@props([
    'type' => \App\Enums\IndexType::Formation, // formation, social_apostolate, parish_involvement
    'entry' => null, // existing entry for edit, null for create
    'report' => null, // associated report for saved scope
    'academicYears' => [], // available academic years
    'formAction' => '',
    'formMethod' => 'POST',
    'cancelUrl' => null,
    'class' => '',
])

@php
    $isEdit = $entry !== null;
    $typeLabels = [
        'formation' => 'Formation',
        'social_apostolate' => 'Social Apostolate',
        'parish_involvement' => 'Parish Involvement',
    ];
    
    $typeIcons = [
        'formation' => '
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12M8 11h8M8 7h8M4 15h16"/>
                <rect x="2" y="19" width="20" height="2" rx="1"/>
            </svg>',
        'social_apostolate' => '
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>',
        'parish_involvement' => '
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2v20M17 7H7M17 17H7M2 12h20"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>',
    ];
    
    $typeValue = $type instanceof \App\Enums\IndexType ? $type->value : $type;
    
    // Field configurations per type
    $fields = match ($typeValue) {
        'formation' => [
            ['name' => 'cycle_code', 'label' => 'Cycle Code', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., C1, C2'],
            ['name' => 'module_code', 'label' => 'Module Code', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., M101'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'placeholder' => 'Module title'],
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'hours', 'label' => 'Hours', 'type' => 'number', 'required' => true, 'step' => '0.5', 'min' => '0'],
        ],
        'social_apostolate' => [
            ['name' => 'activity', 'label' => 'Activity', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., Food Bank, Tutoring'],
            ['name' => 'role_in_activity', 'label' => 'Role in Activity', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Coordinator, Volunteer'],
            ['name' => 'about', 'label' => 'Description', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Brief description of the activity'],
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'time_start', 'label' => 'Time In', 'type' => 'time', 'required' => true],
            ['name' => 'time_end', 'label' => 'Time Out', 'type' => 'time', 'required' => true],
        ],
        'parish_involvement' => [
            ['name' => 'activity', 'label' => 'Activity', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., Sunday Mass, Choir Practice'],
            ['name' => 'role_in_activity', 'label' => 'Role in Activity', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Lector, Eucharistic Minister'],
            ['name' => 'served_on', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'time_start', 'label' => 'Time In', 'type' => 'time', 'required' => true],
            ['name' => 'time_end', 'label' => 'Time Out', 'type' => 'time', 'required' => true],
        ],
        default => [],
    };
@endphp

<div 
    class="entry-modal-overlay {{ $class }}"
    x-data="entryModal({ type: '{{ $typeValue }}', isEdit: {{ $isEdit ? 'true' : 'false' }}, entryId: '{{ $entry->id ?? '' }}' })"
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
    aria-labelledby="entry-modal-title"
    @keydown.escape.window="close()"
    @click.self="close()"
    data-form-action="{{ $formAction }}"
    data-form-method="{{ $formMethod }}"
    data-type-labels="{{ json_encode($typeLabels) }}"
    data-type-icons="{{ json_encode($typeIcons) }}"
    data-academic-years="{{ json_encode($academicYears) }}"
    @if ($isEdit && $entry)
        data-entry-data="{{ json_encode($entry->only(array_merge(array_column($fields, 'name'), ['academic_year']))) }}"
    @endif
>
    <div class="entry-modal" role="document">
        <!-- Header -->
        <header class="entry-modal__header">
            <div class="entry-modal__title-group">
                {!! $typeIcons[$typeValue] ?? '' !!}
                <h2 id="entry-modal-title" class="entry-modal__title">
                    {{ $isEdit ? 'Edit' : 'Add' }} {{ $typeLabels[$typeValue] ?? $typeValue }} Entry
                </h2>
            </div>
            <button 
                type="button"
                class="entry-modal__close"
                @click="close()"
                aria-label="Close modal"
            >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </header>
        
        <!-- Form -->
        <form 
            class="entry-modal__form"
            :action="formAction"
            :method="formMethod"
            @submit.prevent="submit"
            x-ref="form"
        >
            @method($formMethod)
            @csrf
            
            @if ($isEdit)
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="entry_id" :value="entryId">
            @endif
            
            @if ($report)
                <input type="hidden" name="report_id" value="{{ $report->id }}">
            @endif
            
            <input type="hidden" name="type" :value="type">
            
            <!-- Academic Year (for all types) -->
            @if (count($academicYears) > 0)
            <div class="form-field">
                <label for="academic_year" class="form-label">Academic Year</label>
                <select 
                    id="academic_year"
                    name="academic_year"
                    class="form-select"
                    required
                    x-model="formData.academic_year"
                >
                    <option value="" disabled>Select academic year</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <!-- Dynamic fields based on type -->
            @foreach ($fields as $field)
                <div class="form-field">
                    <label for="{{ $field['name'] }}" class="form-label">
                        {{ $field['label'] }}
                        @if ($field['required']) <span class="text-rose-500" aria-hidden="true">*</span> @endif
                    </label>
                    
                    @if ($field['type'] === 'textarea')
                        <textarea
                            id="{{ $field['name'] }}"
                            name="{{ $field['name'] }}"
                            class="form-textarea"
                            placeholder="{{ $field['placeholder'] ?? '' }}"
                            @if ($field['required']) required @endif
                            x-model="formData.{{ $field['name'] }}"
                            rows="3"
                        ></textarea>
                    @elseif ($field['type'] === 'select')
                        <select
                            id="{{ $field['name'] }}"
                            name="{{ $field['name'] }}"
                            class="form-select"
                            @if ($field['required']) required @endif
                            x-model="formData.{{ $field['name'] }}"
                        >
                            <option value="" disabled>Select...</option>
                            @foreach ($field['options'] ?? [] as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            type="{{ $field['type'] }}"
                            id="{{ $field['name'] }}"
                            name="{{ $field['name'] }}"
                            class="form-input"
                            placeholder="{{ $field['placeholder'] ?? '' }}"
                            @if ($field['required']) required @endif
                            @if (isset($field['step'])) step="{{ $field['step'] }}" @endif
                            @if (isset($field['min'])) min="{{ $field['min'] }}" @endif
                            @if (isset($field['max'])) max="{{ $field['max'] }}" @endif
                            x-model="formData.{{ $field['name'] }}"
                        >
                    @endif
                    
                    @if ($errors->has($field['name']))
                        <p class="form-error">{{ $errors->first($field['name']) }}</p>
                    @endif
                </div>
            @endforeach
            
            <!-- Actions -->
            <div class="entry-modal__actions">
                <button 
                    type="button"
                    class="button button--ghost"
                    @click="close()"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="button button--primary"
                    :disabled="isSubmitting"
                >
                    <span x-show="!isSubmitting">{{ $isEdit ? 'Save Changes' : 'Create Entry' }}</span>
                    <span x-show="isSubmitting" class="flex items-center gap-2">
                        <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                        </svg>
                        {{ $isEdit ? 'Saving…' : 'Creating…' }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

