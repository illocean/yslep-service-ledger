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
    @endphp

    <section class="paper-panel overflow-hidden rounded-panel">
        <div class="grid gap-6 px-5 py-6 sm:px-8 xl:grid-cols-[1.2fr_0.8fr] xl:px-10 xl:py-8">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-assignment-chip variant="saved" :label="$type->cardTitle()" />
                    <span class="compact-pill">{{ $selectedScopeLabel }}</span>
                    <span class="compact-pill compact-pill--soft">{{ $summary['count'] }} record(s)</span>
                </div>
                <h1 class="font-serif text-3xl leading-tight text-stone-900 sm:text-4xl">
                    {{ $type->label() }} records
                </h1>
            </div>

            <div class="space-y-3">
                <div class="paper-panel rounded-card p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="form-label" id="scope-label">Scope</span>
                        <a href="{{ route('indexes.show', $allScopeParams) }}" class="topbar-link {{ $selectedScope === \App\Enums\IndexScope::All ? 'is-active' : '' }}" aria-current="{{ $selectedScope === \App\Enums\IndexScope::All ? 'page' : 'false' }}">All Time</a>
                        <a href="{{ route('indexes.show', $unsavedScopeParams) }}" class="topbar-link {{ $selectedScope === \App\Enums\IndexScope::Unsaved ? 'is-active' : '' }}" aria-current="{{ $selectedScope === \App\Enums\IndexScope::Unsaved ? 'page' : 'false' }}">Unsaved Only</a>
                    </div>

                    <form method="GET" action="{{ route('indexes.show', ['type' => $type->value]) }}" class="mt-3" aria-label="Filter by saved report">
                        <input type="hidden" name="scope" value="{{ \App\Enums\IndexScope::Report->value }}">
                        <label for="report" class="form-label">Filter by Saved Report</label>
                        <select id="report" name="report" class="form-input mt-1" data-auto-submit aria-describedby="scope-label">
                            <option value="">Choose a saved report…</option>
                            @foreach ($reportGroups as $reportGroup)
                                <option value="{{ $reportGroup->tag }}" @selected($selectedReportTag === $reportGroup->tag)>
                                    {{ $reportGroup->display_label }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="paper-panel rounded-card p-4">
                    <div class="form-label">{{ $sourceMode === 'live' ? 'Obsidian Source File' : 'Snapshot Source' }}</div>
                    <p class="mt-1 truncate font-mono text-xs text-stone-600" title="{{ $sourceMode === 'live' ? $cardMeta['file_path'] : ($selectedReportGroup->obsidian_index_note_path ?? 'Auto-synced') }}">
                        {{ $sourceMode === 'live' ? $cardMeta['file_path'] : ($selectedReportGroup->obsidian_index_note_path ?? 'Auto-synced') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <article class="stat-panel rounded-stat p-5">
            <div class="section-kicker">{{ $type->label() }}</div>
            <div class="mt-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">Total Entries</div>
                <div class="mt-2 font-serif text-4xl text-stone-950">{{ str_pad((string) $summary['count'], 2, '0', STR_PAD_LEFT) }}</div>
            </div>
        </article>

        <article class="stat-panel rounded-stat p-5">
            <div class="section-kicker">Hours Served</div>
            <div class="mt-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">Selected Scope Total</div>
                <div class="mt-2 font-serif text-4xl text-stone-950">{{ $summary['total_label'] }}</div>
            </div>
        </article>

        <article class="stat-panel rounded-stat p-5">
            <div class="section-kicker">Other Indexes</div>
            <div class="mt-4 grid gap-3">
                @foreach ($otherCards as $card)
                    <a href="{{ route('indexes.show', ['type' => $card['type']->value] + $otherIndexScopeParams) }}" class="secondary-link-card">
                        <div>
                            <div class="text-sm font-semibold text-stone-900">{{ $card['label'] }}</div>
                            <div class="mt-1 text-xs uppercase tracking-[0.18em] text-stone-600">{{ $card['count'] }} record(s)</div>
                        </div>
                        <div class="text-sm font-semibold text-stone-900">{{ $card['total_label'] }}</div>
                    </a>
                @endforeach
            </div>
        </article>
    </section>

    <section class="paper-panel rounded-panel p-5 sm:p-6">
        <div class="section-kicker">Profile</div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div class="profile-cell md:col-span-2 xl:col-span-1">
                <div class="form-label">Full Name</div>
                <div class="mt-2 text-sm font-semibold text-stone-900">{{ $cardMeta['profile']['name'] ?: 'Not set' }}</div>
            </div>
            <div class="profile-cell">
                <div class="form-label">School Year</div>
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
        <article class="paper-panel rounded-panel p-5 sm:p-6">
            @if ($sourceMode === 'live')
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <div class="section-kicker">Add Record</div>
                        <h2 class="mt-1 font-serif text-xl text-stone-950">New {{ strtolower($type->label()) }} entry</h2>
                    </div>
                    <button 
                        type="button"
                        class="primary-button"
                        @click="window.dispatchEvent(new CustomEvent('quick-add-open', { detail: { type: '{{ $type->value }}' } }))"
                        aria-haspopup="dialog"
                        aria-label="Add new {{ strtolower($type->label()) }} entry"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        <span>Add Entry</span>
                    </button>
                </div>

                <!-- Live Entry Create Modal -->
                <x-entry-modal
                    :type="$type"
                    :form-action="route('entries.store')"
                    :academic-years="$academicYears"
                    :cancel-url="route('indexes.show', ['type' => $type->value] + $allScopeParams)"
                />
            @else
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <div class="section-kicker">{{ $selectedReportGroup->title ?: 'Untitled Report' }}</div>
                        <h2 class="mt-1 font-serif text-xl text-stone-950">Add record to report</h2>
                    </div>
                    <button 
                        type="button"
                        class="primary-button"
                        data-add-entry-trigger
                        data-type="{{ $type->value }}"
                        data-report-id="{{ $selectedReportGroup->id }}"
                        @click="window.dispatchEvent(new CustomEvent('quick-add-open', { detail: { type: $el.dataset.type, reportId: parseInt($el.dataset.reportId) } }))"
                        aria-haspopup="dialog"
                        aria-label="Add new {{ strtolower($type->label()) }} entry to report"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        <span>Add Entry</span>
                    </button>
                </div>

                <!-- Saved Report Entry Create Modal -->
                <x-entry-modal
                    :type="$type"
                    :report="$selectedReportGroup"
                    :form-action="route('reports.records.store', $selectedReportGroup)"
                    :academic-years="$academicYears"
                    :cancel-url="route('reports.show', $selectedReportGroup)"
                />
            @endif
        </article>

        <article class="paper-panel rounded-panel p-5 sm:p-6">
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
                        $headline = match ($type) {
                            \App\Enums\IndexType::Formation => trim(($entry->cycle_code ?? '') . ' / ' . ($entry->module_code ?? ''), ' /') . ' - ' . $entry->title,
                            \App\Enums\IndexType::SocialApostolate => $entry->about,
                            default => 'Parish Involvement',
                        };
                    @endphp

                    <!-- Record Card -->
                    <article class="report-record-card rounded-cell">
                        <div class="flex flex-col gap-4 rounded-cell sm:flex-row sm:items-start sm:justify-between p-4">
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-assignment-chip 
                                        :variant="$sourceMode === 'live' ? 'complete' : 'saved'" 
                                        :label="$sourceMode === 'live' ? 'Live record' : 'Saved record'"
                                    />
                                    <span class="compact-pill">{{ $entry->duration_label }}</span>

                                    @if ($entry->obsidian_conflict)
                                        <x-assignment-chip 
                                            variant="conflict" 
                                            label="Conflict"
                                            title="Conflict detected: both Obsidian and database have changes since last sync"
                                        />
                                    @endif

                                    @if ($assignment)
                                        <x-assignment-chip 
                                            variant="saved" 
                                            :label="'Saved in ' . $assignment->compact_label"
                                            :title="'Saved in ' . $assignment->display_label"
                                        />
                                    @endif
                                </div>

                                <div class="text-lg font-semibold text-stone-900">{{ $headline }}</div>
                                <div class="text-sm leading-7 text-stone-600">
                                    {{ $entry->served_on_label }} | {{ $entry->time_start_label }} - {{ $entry->time_end_label }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button 
                                    type="button"
                                    class="button button--ghost text-sm"
                                    data-edit-entry-trigger
                                    data-type="{{ $type->value }}"
                                    data-entry-id="{{ $entry->id }}"
                                    data-source-mode="{{ $sourceMode }}"
                                    @if ($sourceMode === 'report') data-report-id="{{ $selectedReportGroup->id }}" @endif
                                    @click="window.dispatchEvent(new CustomEvent('open-entry-modal', { detail: { type: $el.dataset.type, entryId: parseInt($el.dataset.entryId), isEdit: true, sourceMode: $el.dataset.sourceMode, reportId: $el.dataset.reportId ? parseInt($el.dataset.reportId) : null } }))"
                                    aria-label="Edit {{ $headline }}"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    <span class="hidden sm:inline">Edit</span>
                                </button>
                                
                                @if ($entry->obsidian_conflict)
                                    <x-assignment-chip 
                                        variant="conflict" 
                                        label="Conflict"
                                        class="hidden sm:inline-flex"
                                    />
                                @endif
                            </div>
                        </div>
                    </article>

                    <!-- Live Entry Edit Modal -->
                    @if ($sourceMode === 'live')
                        <x-entry-modal
                            :type="$type"
                            :entry="$entry"
                            :form-action="route('entries.update', ['entry' => $entry->id])"
                            :form-method="'PUT'"
                            :academic-years="$academicYears"
                            :cancel-url="route('indexes.show', ['type' => $type->value] + $allScopeParams)"
                        />
                    @else
                        <!-- Saved Report Entry Edit Modal -->
                        <x-entry-modal
                            :type="$type"
                            :entry="$entry"
                            :report="$selectedReportGroup"
                            :form-action="route('reports.records.update', [$selectedReportGroup, $entry])"
                            :form-method="'PUT'"
                            :academic-years="$academicYears"
                            :cancel-url="route('reports.show', $selectedReportGroup)"
                        />
                    @endif
                @empty
                    <div class="rounded-card border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm leading-7 text-stone-600">
                        No {{ strtolower($type->label()) }} records in this scope.
                        @if ($selectedScope === \App\Enums\IndexScope::Unsaved)
                            All records here are already saved to reports.
                        @elseif ($selectedScope === \App\Enums\IndexScope::Report)
                            Choose a different report or add records to this one.
                        @else
                            Add entries in Obsidian to see them appear here.
                        @endif
                    </div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
