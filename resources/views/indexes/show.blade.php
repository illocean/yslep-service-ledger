@extends('layouts.app')

@section('title', $type->cardTitle())

@section('content')
    @include('partials.alerts')

    @php
        $allScopeParams = ['type' => $type->value];
        $unsavedScopeParams = ['type' => $type->value, 'scope' => \App\Enums\IndexScope::Unsaved->value];
        $reportScopeParams = ['type' => $type->value, 'scope' => \App\Enums\IndexScope::Report->value];

        if ($selectedReportTag) {
            $reportScopeParams['report'] = $selectedReportTag;
        }

        $otherIndexScopeParams = [];

        if ($selectedScope !== \App\Enums\IndexScope::All) {
            $otherIndexScopeParams['scope'] = $selectedScope->value;
        }

        if ($selectedScope === \App\Enums\IndexScope::Report && $selectedReportTag) {
            $otherIndexScopeParams['report'] = $selectedReportTag;
        }

        $openEditorKey = old('editor_key');
        $createFormEditorKey = 'create-' . $type->value . '-' . $selectedScope->value;
    @endphp

    <section class="paper-panel overflow-hidden rounded-[2rem]">
        <div class="grid gap-6 px-5 py-6 sm:px-8 xl:grid-cols-[1.2fr_0.8fr] xl:px-10 xl:py-8">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="assignment-chip assignment-chip--saved">{{ $type->cardTitle() }}</span>
                    <span class="compact-pill">{{ $selectedScopeLabel }}</span>
                    <span class="compact-pill compact-pill--soft">{{ $summary['count'] }} record(s)</span>
                </div>
                <h1 class="font-serif text-3xl leading-tight text-stone-900 sm:text-4xl">
                    {{ $type->label() }} records
                </h1>
            </div>

            <div class="space-y-3">
                <div class="paper-panel rounded-[1.5rem] p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="form-label">Scope</span>
                        <a href="{{ route('indexes.show', $allScopeParams) }}" class="topbar-link {{ $selectedScope === \App\Enums\IndexScope::All ? 'is-active' : '' }}">All Time</a>
                        <a href="{{ route('indexes.show', $unsavedScopeParams) }}" class="topbar-link {{ $selectedScope === \App\Enums\IndexScope::Unsaved ? 'is-active' : '' }}">Unsaved</a>
                    </div>

                    <form method="GET" action="{{ route('indexes.show', ['type' => $type->value]) }}" class="mt-3">
                        <input type="hidden" name="scope" value="{{ \App\Enums\IndexScope::Report->value }}">
                        <label for="report" class="form-label">Report scope</label>
                        <select id="report" name="report" class="form-input mt-1" data-auto-submit>
                            <option value="">Choose a saved report</option>
                            @foreach ($reportGroups as $reportGroup)
                                <option value="{{ $reportGroup->tag }}" @selected($selectedReportTag === $reportGroup->tag)>
                                    {{ $reportGroup->display_label }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="paper-panel rounded-[1.5rem] p-4">
                    <div class="form-label">{{ $sourceMode === 'live' ? 'Source' : 'Snapshot' }}</div>
                    <p class="mt-1 truncate font-mono text-xs text-stone-600">
                        {{ $sourceMode === 'live' ? $cardMeta['file_path'] : ($selectedReportGroup->obsidian_index_note_path ?? 'Auto-synced') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <article class="stat-panel rounded-[1.75rem] p-5">
            <div class="section-kicker">{{ $type->label() }}</div>
            <div class="mt-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Total count</div>
                <div class="mt-2 font-serif text-4xl text-stone-950">{{ str_pad((string) $summary['count'], 2, '0', STR_PAD_LEFT) }}</div>
            </div>
        </article>

        <article class="stat-panel rounded-[1.75rem] p-5">
            <div class="section-kicker">Hours Served</div>
            <div class="mt-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Selected scope total</div>
                <div class="mt-2 font-serif text-4xl text-stone-950">{{ $summary['total_label'] }}</div>
            </div>
        </article>

        <article class="stat-panel rounded-[1.75rem] p-5">
            <div class="section-kicker">Scope</div>
            <div class="mt-4 grid gap-3">
                @foreach ($otherCards as $card)
                    <a href="{{ route('indexes.show', ['type' => $card['type']->value] + $otherIndexScopeParams) }}" class="secondary-link-card">
                        <div>
                            <div class="text-sm font-semibold text-stone-900">{{ $card['label'] }}</div>
                            <div class="mt-1 text-xs uppercase tracking-[0.18em] text-stone-500">{{ $card['count'] }} record(s)</div>
                        </div>
                        <div class="text-sm font-semibold text-stone-900">{{ $card['total_label'] }}</div>
                    </a>
                @endforeach
            </div>
        </article>
    </section>

    <section class="paper-panel rounded-[2rem] p-5 sm:p-6">
        <div class="section-kicker">Card Header</div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div class="profile-cell md:col-span-2 xl:col-span-1">
                <div class="form-label">Name</div>
                <div class="mt-2 text-sm font-semibold text-stone-900">{{ $cardMeta['profile']['name'] ?: 'Not set' }}</div>
            </div>
            <div class="profile-cell">
                <div class="form-label">SY</div>
                <div class="mt-2 text-sm font-semibold text-stone-900">{{ $cardMeta['profile']['school_year'] ?: 'Not set' }}</div>
            </div>
            <div class="profile-cell">
                <div class="form-label">Year Level</div>
                <div class="mt-2 text-sm font-semibold text-stone-900">{{ $cardMeta['profile']['year_level'] ?: 'Not set' }}</div>
            </div>
            <div class="profile-cell">
                <div class="form-label">Parish</div>
                <div class="mt-2 text-sm font-semibold text-stone-900">{{ $cardMeta['profile']['parish'] ?: 'Not set' }}</div>
            </div>
            <div class="profile-cell">
                <div class="form-label">Diocese / Institution</div>
                <div class="mt-2 text-sm font-semibold text-stone-900">{{ $cardMeta['profile']['diocese_institution'] ?: 'Not set' }}</div>
            </div>
            <div class="profile-cell">
                <div class="form-label">School</div>
                <div class="mt-2 text-sm font-semibold text-stone-900">{{ $cardMeta['profile']['school'] ?: 'Not set' }}</div>
            </div>
            <div class="profile-cell">
                <div class="form-label">Course</div>
                <div class="mt-2 text-sm font-semibold text-stone-900">{{ $cardMeta['profile']['course'] ?: 'Not set' }}</div>
            </div>
        </div>
    </section>

    <section class="space-y-6">
        <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
            @if ($sourceMode === 'live')
                <details class="group" @if($errors->any()) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 [&::-webkit-details-marker]:hidden">
                        <div>
                            <div class="section-kicker">Add Record</div>
                            <h2 class="mt-1 font-serif text-xl text-stone-950">New {{ strtolower($type->label()) }} entry</h2>
                        </div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-stone-500 transition-transform group-open:rotate-180">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
                        </div>
                    </summary>

                    <form method="POST" action="{{ route('entries.store') }}" class="record-form-shell mt-4">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type->value }}">
                        <input type="hidden" name="scope" value="{{ $selectedScope->value }}">
                        <input type="hidden" name="editor_key" value="{{ $createFormEditorKey }}">

                        <div class="record-form-grid md:grid-cols-2">
                            <div>
                                <label class="form-label" for="served-on">Date</label>
                                <input id="served-on" name="served_on" type="date" value="{{ old('editor_key') === $createFormEditorKey ? old('served_on') : '' }}" class="form-input mt-2">
                            </div>

                            @if ($type === \App\Enums\IndexType::Formation)
                                <div>
                                    <label class="form-label" for="cycle-code">Cycle</label>
                                    <input id="cycle-code" name="cycle_code" type="text" value="{{ old('editor_key') === $createFormEditorKey ? old('cycle_code') : '' }}" placeholder="C1" class="form-input mt-2">
                                </div>
                                <div>
                                    <label class="form-label" for="module-code">Module</label>
                                    <input id="module-code" name="module_code" type="text" value="{{ old('editor_key') === $createFormEditorKey ? old('module_code') : '' }}" placeholder="M1" class="form-input mt-2">
                                </div>
                                <div class="record-form-grid__wide">
                                    <label class="form-label" for="title">Title</label>
                                    <input id="title" name="title" type="text" value="{{ old('editor_key') === $createFormEditorKey ? old('title') : '' }}" placeholder="Formation Session" class="form-input mt-2">
                                </div>
                            @endif

                            @if ($type === \App\Enums\IndexType::SocialApostolate)
                                <div class="record-form-grid__wide">
                                    <label class="form-label" for="about">Activity</label>
                                    <input id="about" name="about" type="text" value="{{ old('editor_key') === $createFormEditorKey ? old('about') : '' }}" placeholder="Service activity" class="form-input mt-2">
                                </div>
                            @endif

                            <div>
                                <label class="form-label" for="time-start">Time in</label>
                                <input id="time-start" name="time_start" type="time" value="{{ old('editor_key') === $createFormEditorKey ? old('time_start') : '' }}" class="form-input mt-2">
                            </div>
                            <div>
                                <label class="form-label" for="time-end">Time out</label>
                                <input id="time-end" name="time_end" type="time" value="{{ old('editor_key') === $createFormEditorKey ? old('time_end') : '' }}" class="form-input mt-2">
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="primary-button">Add Entry</button>
                        </div>
                    </form>
                </details>
            @else
                <details class="group" @if($errors->any()) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 [&::-webkit-details-marker]:hidden">
                        <div>
                            <div class="section-kicker">{{ $selectedReportGroup->title ?: 'Untitled Report' }}</div>
                            <h2 class="mt-1 font-serif text-xl text-stone-950">Add record to report</h2>
                        </div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-stone-500 transition-transform group-open:rotate-180">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
                        </div>
                    </summary>

                    <form method="POST" action="{{ route('reports.records.store', $selectedReportGroup) }}" class="record-form-shell mt-4">
                        @csrf
                        <input type="hidden" name="index_type" value="{{ $type->value }}">
                        <input type="hidden" name="return_type" value="{{ $type->value }}">
                        <input type="hidden" name="return_scope" value="{{ \App\Enums\IndexScope::Report->value }}">
                        <input type="hidden" name="return_report" value="{{ $selectedReportTag }}">
                        <input type="hidden" name="editor_key" value="{{ $createFormEditorKey }}">

                        <div class="record-form-grid md:grid-cols-2">
                            <div>
                                <label class="form-label" for="report-served-on">Date</label>
                                <input id="report-served-on" name="served_on" type="date" value="{{ old('editor_key') === $createFormEditorKey ? old('served_on') : '' }}" class="form-input mt-2">
                            </div>

                            @if ($type === \App\Enums\IndexType::Formation)
                                <div>
                                    <label class="form-label" for="report-cycle-code">Cycle</label>
                                    <input id="report-cycle-code" name="cycle_code" type="text" value="{{ old('editor_key') === $createFormEditorKey ? old('cycle_code') : '' }}" placeholder="C1" class="form-input mt-2">
                                </div>
                                <div>
                                    <label class="form-label" for="report-module-code">Module</label>
                                    <input id="report-module-code" name="module_code" type="text" value="{{ old('editor_key') === $createFormEditorKey ? old('module_code') : '' }}" placeholder="M1" class="form-input mt-2">
                                </div>
                                <div class="record-form-grid__wide">
                                    <label class="form-label" for="report-title">Title</label>
                                    <input id="report-title" name="title" type="text" value="{{ old('editor_key') === $createFormEditorKey ? old('title') : '' }}" placeholder="Formation Session" class="form-input mt-2">
                                </div>
                            @endif

                            @if ($type === \App\Enums\IndexType::SocialApostolate)
                                <div class="record-form-grid__wide">
                                    <label class="form-label" for="report-about">Activity</label>
                                    <input id="report-about" name="about" type="text" value="{{ old('editor_key') === $createFormEditorKey ? old('about') : '' }}" placeholder="Service activity" class="form-input mt-2">
                                </div>
                            @endif

                            <div>
                                <label class="form-label" for="report-time-start">Time in</label>
                                <input id="report-time-start" name="time_start" type="time" value="{{ old('editor_key') === $createFormEditorKey ? old('time_start') : '' }}" class="form-input mt-2">
                            </div>
                            <div>
                                <label class="form-label" for="report-time-end">Time out</label>
                                <input id="report-time-end" name="time_end" type="time" value="{{ old('editor_key') === $createFormEditorKey ? old('time_end') : '' }}" class="form-input mt-2">
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <a href="{{ route('reports.show', $selectedReportGroup) }}" class="secondary-button !w-auto">Full Report</a>
                            <button type="submit" class="primary-button">Add Record</button>
                        </div>
                    </form>
                </details>
            @endif
        </article>

        <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div>
                    <div class="section-kicker">Ledger</div>
                    <h2 class="mt-1 font-serif text-xl text-stone-950">{{ $selectedScopeLabel }} entries</h2>
                </div>
                <div class="rounded-full border border-stone-900/10 bg-white/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                    {{ $summary['count'] }} record(s)
                </div>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($entries as $entry)
                    @php
                        $assignment = $sourceMode === 'live' ? ($assignedReportLookup[$type->value . ':' . $entry->id] ?? null) : null;
                        $editorKey = ($sourceMode === 'live' ? 'live-' : 'report-') . $entry->id;
                        $isOpen = $openEditorKey === $editorKey;
                        $editorValue = fn (string $name, mixed $default = '') => $isOpen ? old($name, $default) : $default;
                        $headline = match ($type) {
                            \App\Enums\IndexType::Formation => trim(($entry->cycle_code ?? '') . ' / ' . ($entry->module_code ?? ''), ' /') . ' - ' . $entry->title,
                            \App\Enums\IndexType::SocialApostolate => $entry->about,
                            default => 'Parish Involvement',
                        };
                    @endphp

                    <details class="report-record-card" @if($isOpen) open @endif>
                        <summary class="flex cursor-pointer list-none flex-col gap-4 rounded-[1.2rem] sm:flex-row sm:items-start sm:justify-between [&::-webkit-details-marker]:hidden">
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="assignment-chip {{ $sourceMode === 'live' ? 'assignment-chip--complete' : 'assignment-chip--saved' }}">
                                        <span class="assignment-chip__dot" aria-hidden="true"></span>
                                        <span>{{ $sourceMode === 'live' ? 'Live record' : 'Saved record' }}</span>
                                    </span>
                                    <span class="compact-pill">{{ $entry->duration_label }}</span>

                                    @if ($assignment)
                                        <span class="assignment-chip assignment-chip--saved">
                                            <span class="assignment-chip__dot" aria-hidden="true"></span>
                                            <span>Saved in {{ $assignment->compact_label }}</span>
                                        </span>
                                    @endif
                                </div>

                                <div class="text-lg font-semibold text-stone-900">{{ $headline }}</div>
                                <div class="text-sm leading-7 text-stone-600">
                                    {{ $entry->served_on_label }} | {{ $entry->time_start_label }} - {{ $entry->time_end_label }}
                                </div>
                            </div>

                            <div class="flex items-center gap-3 text-sm font-semibold text-stone-700">
                                <span>Edit details</span>
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-stone-900/10 bg-white/80 text-lg">+</span>
                            </div>
                        </summary>

                        <div class="mt-5 border-t border-stone-900/8 pt-5">
                            @if ($sourceMode === 'live')
                                <form method="POST" action="{{ route('entries.update', ['entry' => $entry->id]) }}" class="record-form-shell">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="type" value="{{ $type->value }}">
                                    <input type="hidden" name="scope" value="{{ $selectedScope->value }}">
                                    <input type="hidden" name="editor_key" value="{{ $editorKey }}">

                                    <div class="record-form-grid md:grid-cols-2">
                                        <div>
                                            <label class="form-label" for="entry-{{ $entry->id }}-served-on">Date</label>
                                            <input id="entry-{{ $entry->id }}-served-on" name="served_on" type="date" value="{{ $editorValue('served_on', $entry->served_on?->toDateString()) }}" class="form-input mt-2">
                                        </div>

                                        @if ($type === \App\Enums\IndexType::Formation)
                                            <div>
                                                <label class="form-label" for="entry-{{ $entry->id }}-cycle-code">Cycle</label>
                                                <input id="entry-{{ $entry->id }}-cycle-code" name="cycle_code" type="text" value="{{ $editorValue('cycle_code', $entry->cycle_code) }}" class="form-input mt-2">
                                            </div>
                                            <div>
                                                <label class="form-label" for="entry-{{ $entry->id }}-module-code">Module</label>
                                                <input id="entry-{{ $entry->id }}-module-code" name="module_code" type="text" value="{{ $editorValue('module_code', $entry->module_code) }}" class="form-input mt-2">
                                            </div>
                                            <div class="record-form-grid__wide">
                                                <label class="form-label" for="entry-{{ $entry->id }}-title">Title</label>
                                                <input id="entry-{{ $entry->id }}-title" name="title" type="text" value="{{ $editorValue('title', $entry->title) }}" class="form-input mt-2">
                                            </div>
                                        @endif

                                        @if ($type === \App\Enums\IndexType::SocialApostolate)
                                            <div class="record-form-grid__wide">
                                                <label class="form-label" for="entry-{{ $entry->id }}-about">Activity / about</label>
                                                <input id="entry-{{ $entry->id }}-about" name="about" type="text" value="{{ $editorValue('about', $entry->about) }}" class="form-input mt-2">
                                            </div>
                                        @endif

                                        <div>
                                            <label class="form-label" for="entry-{{ $entry->id }}-time-start">Time in</label>
                                            <input id="entry-{{ $entry->id }}-time-start" name="time_start" type="time" value="{{ $editorValue('time_start', substr((string) $entry->getRawOriginal('time_start'), 0, 5)) }}" class="form-input mt-2">
                                        </div>
                                        <div>
                                            <label class="form-label" for="entry-{{ $entry->id }}-time-end">Time out</label>
                                            <input id="entry-{{ $entry->id }}-time-end" name="time_end" type="time" value="{{ $editorValue('time_end', substr((string) $entry->getRawOriginal('time_end'), 0, 5)) }}" class="form-input mt-2">
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="break-all font-mono text-[0.72rem] text-stone-500">{{ $cardMeta['file_path'] }}</div>
                                        <button type="submit" class="primary-button !w-auto">Save Changes</button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('entries.destroy', ['entry' => $entry->id]) }}" class="mt-3 flex justify-end" data-confirm="Delete this live record from the index note?">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="type" value="{{ $type->value }}">
                                    <input type="hidden" name="scope" value="{{ $selectedScope->value }}">
                                    <button type="submit" class="danger-button">Delete Live Record</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('reports.records.update', [$selectedReportGroup, $entry]) }}" class="record-form-shell">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="index_type" value="{{ $type->value }}">
                                    <input type="hidden" name="return_type" value="{{ $type->value }}">
                                    <input type="hidden" name="return_scope" value="{{ \App\Enums\IndexScope::Report->value }}">
                                    <input type="hidden" name="return_report" value="{{ $selectedReportTag }}">
                                    <input type="hidden" name="editor_key" value="{{ $editorKey }}">

                                    <div class="record-form-grid md:grid-cols-2">
                                        <div>
                                            <label class="form-label" for="saved-entry-{{ $entry->id }}-served-on">Date</label>
                                            <input id="saved-entry-{{ $entry->id }}-served-on" name="served_on" type="date" value="{{ $editorValue('served_on', $entry->served_on?->toDateString()) }}" class="form-input mt-2">
                                        </div>

                                        @if ($type === \App\Enums\IndexType::Formation)
                                            <div>
                                                <label class="form-label" for="saved-entry-{{ $entry->id }}-cycle-code">Cycle</label>
                                                <input id="saved-entry-{{ $entry->id }}-cycle-code" name="cycle_code" type="text" value="{{ $editorValue('cycle_code', $entry->cycle_code) }}" class="form-input mt-2">
                                            </div>
                                            <div>
                                                <label class="form-label" for="saved-entry-{{ $entry->id }}-module-code">Module</label>
                                                <input id="saved-entry-{{ $entry->id }}-module-code" name="module_code" type="text" value="{{ $editorValue('module_code', $entry->module_code) }}" class="form-input mt-2">
                                            </div>
                                            <div class="record-form-grid__wide">
                                                <label class="form-label" for="saved-entry-{{ $entry->id }}-title">Title</label>
                                                <input id="saved-entry-{{ $entry->id }}-title" name="title" type="text" value="{{ $editorValue('title', $entry->title) }}" class="form-input mt-2">
                                            </div>
                                        @endif

                                        @if ($type === \App\Enums\IndexType::SocialApostolate)
                                            <div class="record-form-grid__wide">
                                                <label class="form-label" for="saved-entry-{{ $entry->id }}-about">Activity / about</label>
                                                <input id="saved-entry-{{ $entry->id }}-about" name="about" type="text" value="{{ $editorValue('about', $entry->about) }}" class="form-input mt-2">
                                            </div>
                                        @endif

                                        <div>
                                            <label class="form-label" for="saved-entry-{{ $entry->id }}-time-start">Time in</label>
                                            <input id="saved-entry-{{ $entry->id }}-time-start" name="time_start" type="time" value="{{ $editorValue('time_start', substr((string) $entry->getRawOriginal('time_start'), 0, 5)) }}" class="form-input mt-2">
                                        </div>
                                        <div>
                                            <label class="form-label" for="saved-entry-{{ $entry->id }}-time-end">Time out</label>
                                            <input id="saved-entry-{{ $entry->id }}-time-end" name="time_end" type="time" value="{{ $editorValue('time_end', substr((string) $entry->getRawOriginal('time_end'), 0, 5)) }}" class="form-input mt-2">
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="break-all font-mono text-[0.72rem] text-stone-500">{{ $entry->obsidian_note_path ?: 'Saved report notes refresh automatically' }}</div>
                                        <button type="submit" class="primary-button !w-auto">Save Changes</button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('reports.records.destroy', [$selectedReportGroup, $entry]) }}" class="mt-3 flex justify-end" data-confirm="Delete this saved record from the report?">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="return_type" value="{{ $type->value }}">
                                    <input type="hidden" name="return_scope" value="{{ \App\Enums\IndexScope::Report->value }}">
                                    <input type="hidden" name="return_report" value="{{ $selectedReportTag }}">
                                    <button type="submit" class="danger-button">Delete Saved Record</button>
                                </form>
                            @endif
                        </div>
                    </details>
                @empty
                    <div class="rounded-[1.5rem] border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm leading-7 text-stone-600">
                        No {{ strtolower($type->label()) }} records in this scope.
                    </div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
