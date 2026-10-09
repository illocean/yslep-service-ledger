@props([
    'type' => \App\Enums\IndexType::Formation,
    'entry' => null,
    'report' => null,
    'academicYears' => [],
    'formAction' => '',
    'formMethod' => 'POST',
    'class' => '',
])

@php
    $isEdit = $entry !== null;
    $typeValue = $type->value;
    $modalKey = ($report ? 'report-'.$report->id : 'live').'-'.$typeValue.'-'.($entry?->id ?? 'new');
    $hasErrors = $errors->any() && old('_entry_modal') === $modalKey;
    $fields = [
        ['served_on', 'Date', 'date', true],
        ['academic_year', 'Academic year (optional)', 'text', false],
    ];
    if ($type === \App\Enums\IndexType::Formation) {
        $fields = [...$fields,
            ['cycle_code', 'Cycle code', 'text', true],
            ['module_code', 'Module code', 'text', true],
            ['title', 'Title', 'text', true],
        ];
    } else {
        if ($type === \App\Enums\IndexType::SocialApostolate) {
            $fields[] = ['about', 'Activity', 'text', true];
        }
        $fields[] = ['role_in_activity', 'Role in activity', 'text', $type === \App\Enums\IndexType::SocialApostolate && ! $report];
    }
    $fields = [...$fields, ['time_start', 'Time in', 'time', true], ['time_end', 'Time out', 'time', true]];
@endphp

<dialog class="entry-dialog" x-data="entryModal" data-modal-key="{{ $modalKey }}"
    data-type="{{ $typeValue }}" data-entry-id="{{ $entry?->id }}"
    data-has-errors="{{ $hasErrors ? 'true' : 'false' }}"
    aria-labelledby="{{ $modalKey }}-heading"
    @quick-add-open.window="if (!$el.dataset.entryId && $event.detail.type === $el.dataset.type) open()"
    @open-entry-modal.window="if ($el.dataset.entryId && $event.detail.type === $el.dataset.type && String($event.detail.entryId) === $el.dataset.entryId) open()"
    @click="if ($event.target === $el) close()"
    @close="document.body.style.overflow = ''">
    <div class="entry-modal">
        <header class="entry-modal__header">
            <div>
                <p class="section-kicker">{{ $report ? 'Saved report: '.$report->compact_label : 'Live ledger' }}</p>
                <h2 id="{{ $modalKey }}-heading" class="entry-modal__title mt-2">{{ $isEdit ? 'Edit' : 'Add' }} {{ $type->label() }}</h2>
            </div>
            <button type="button" class="entry-modal__close" @click="close()" aria-label="Close modal">&#215;</button>
        </header>
        <form method="POST" action="{{ $formAction }}" class="entry-modal__form" @submit="isSubmitting = true">
            @csrf
            @if ($isEdit)
                @method('PATCH')
            @endif
            <input type="hidden" name="_entry_modal" value="{{ $modalKey }}">
            <input type="hidden" name="{{ $report ? 'index_type' : 'type' }}" value="{{ $typeValue }}">
            @if (request()->routeIs('indexes.show'))
                <input type="hidden" name="return_type" value="{{ request()->route('type')->value }}">
                <input type="hidden" name="return_scope" value="{{ $report ? 'report' : request('scope', 'all') }}">
                @if ($report)
                    <input type="hidden" name="return_report" value="{{ $report->tag }}">
                @endif
            @endif
            @if ($hasErrors)
                <div role="alert" class="mb-4 text-sm text-red-800">Please correct the highlighted fields.</div>
            @endif
            <div class="grid grid-cols-2 gap-4">
                @foreach ($fields as [$name, $label, $inputType, $required])
                    @php
                        $value = $entry?->getAttribute($name);
                        if ($name === 'served_on') {
                            $value = $entry?->served_on?->toDateString() ?? (! $entry ? now()->toDateString() : null);
                        } elseif ($inputType === 'time') {
                            $value = substr((string) $value, 0, 5);
                        }
                        $value = $hasErrors ? old($name) : $value;
                    @endphp
                    <div class="{{ in_array($name, ['title', 'about', 'role_in_activity']) ? 'col-span-2' : '' }} min-w-0">
                        <label for="{{ $modalKey }}-{{ $name }}" class="form-label">{{ $label }} @if ($required)<span aria-hidden="true">*</span>@endif</label>
                        <input id="{{ $modalKey }}-{{ $name }}" name="{{ $name }}" type="{{ $inputType }}"
                            class="form-input mt-2" value="{{ $value }}" @required($required)
                            @if ($inputType === 'text') maxlength="{{ in_array($name, ['cycle_code', 'module_code']) ? 20 : ($name === 'academic_year' ? 9 : 255) }}" @endif
                            @if ($name === 'academic_year') list="entry-academic-years" placeholder="2025-2026" pattern="[0-9]{4}-[0-9]{4}" @endif
                            @if ($name === 'cycle_code') placeholder="C1" pattern="[Cc][0-9]+" @endif
                            @if ($name === 'module_code') placeholder="M1" pattern="[Mm][0-9]+" @endif
                            @if ($hasErrors && $errors->has($name)) aria-invalid="true" aria-describedby="{{ $modalKey }}-{{ $name }}-error" @endif>
                        @if ($name === 'academic_year')
                            <p class="mt-1 text-xs text-stone-600">Choose a year or type a new one. This does not archive the entry.</p>
                        @endif
                        @if ($hasErrors && $errors->has($name))
                            <p id="{{ $modalKey }}-{{ $name }}-error" class="form-error">{{ $errors->first($name) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="mt-6 flex justify-end gap-3 border-t border-stone-200 pt-4">
                <button type="button" class="secondary-button" @click="close()">Cancel</button>
                <button type="submit" class="primary-button" :disabled="isSubmitting" x-text="isSubmitting ? 'Saving…' : '{{ $isEdit ? 'Save changes' : 'Add entry' }}'">{{ $isEdit ? 'Save changes' : 'Add entry' }}</button>
            </div>
        </form>
    </div>
</dialog>
