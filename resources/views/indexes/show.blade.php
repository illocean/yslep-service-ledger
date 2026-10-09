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

    <section class="border-b border-stone-300 pb-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="section-kicker">{{ $selectedScopeLabel }}</p>
                <h1 class="mt-2 font-serif text-3xl text-stone-950 sm:text-4xl">{{ $type->label() }}</h1>
                <p class="mt-2 text-sm text-stone-600">{{ $summary['count'] }} entries <span aria-hidden="true">·</span> {{ $summary['total_label'] }} served</p>
            </div>
            <button type="button" class="primary-button" @click="$dispatch('quick-add-open', { type: '{{ $type->value }}' })" aria-haspopup="dialog">+ Add {{ $type->label() }}</button>
        </div>
        <div class="mt-5 flex flex-wrap items-end gap-4">
            <nav class="flex gap-2" aria-label="Record scope">
                <a href="{{ route('indexes.show', $allScopeParams) }}" class="topbar-link {{ $selectedScope === \App\Enums\IndexScope::All ? 'is-active' : '' }}">All entries</a>
                <a href="{{ route('indexes.show', $unsavedScopeParams) }}" class="topbar-link {{ $selectedScope === \App\Enums\IndexScope::Unsaved ? 'is-active' : '' }}">Unsaved only</a>
            </nav>
            <form method="GET" action="{{ route('indexes.show', ['type' => $type->value]) }}" class="min-w-0 flex-1 sm:max-w-sm">
                <input type="hidden" name="scope" value="report">
                <label for="report" class="form-label">Saved report</label>
                <select id="report" name="report" class="form-input mt-1" data-auto-submit>
                    <option value="">Choose a report</option>
                    @foreach ($reportGroups as $reportGroup)
                        <option value="{{ $reportGroup->tag }}" @selected($selectedReportTag === $reportGroup->tag)>{{ $reportGroup->compact_label }}</option>
                    @endforeach
                </select>
            </form>
            <details class="text-sm text-stone-600">
                <summary class="cursor-pointer py-2">Profile details</summary>
                <dl class="mt-2 grid gap-2">
                    @foreach ($cardMeta['profile'] as $key => $value)
                        @if (filled($value))
                            <div><dt class="inline font-semibold">{{ str($key)->replace('_', ' ')->title() }}:</dt> <dd class="inline">{{ $value }}</dd></div>
                        @endif
                    @endforeach
                </dl>
            </details>
        </div>
    </section>

    <section>
        <article class="paper-panel rounded-panel p-5 sm:p-6" x-data="ledgerSearch">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div>
                    <div class="section-kicker">Ledger</div>
                    <h2 class="mt-1 font-serif text-xl text-stone-950">{{ $selectedScopeLabel }} entries</h2>
                </div>
                <div class="rounded-full border border-stone-900/10 bg-white/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                    {{ $summary['count'] }} record(s)
                </div>
            </div>

            <div class="mt-4 max-w-sm">
                <label for="ledger-search" class="sr-only">Search records</label>
                <input id="ledger-search" type="search" class="form-input" placeholder="Search by title, date, or year…" x-model.debounce.150ms="q" autocomplete="off">
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
                        $searchable = mb_strtolower(implode(' ', array_filter([
                            $headline,
                            $entry->served_on_label,
                            $entry->served_on?->toDateString(),
                            $entry->academic_year,
                            $entry->role_in_activity,
                        ])));
                    @endphp

                    <!-- Record Card -->
                    <article class="report-record-card rounded-cell" data-search="{{ $searchable }}" x-show="match($el)">
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
                                    @if ($entry->academic_year)<span class="ml-2">AY {{ $entry->academic_year }}</span>@endif
                                    @if ($entry->role_in_activity)<div>{{ $entry->role_in_activity }}</div>@endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button 
                                    type="button"
                                    class="secondary-button text-sm"
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
                                    <span>Edit</span>
                                </button>
                                
                                <form method="POST" action="{{ $sourceMode === 'live' ? route('entries.destroy', $entry->id) : route('reports.records.destroy', [$selectedReportGroup, $entry]) }}" data-confirm="Delete this entry?">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="type" value="{{ $type->value }}">
                                    <input type="hidden" name="return_type" value="{{ $type->value }}">
                                    <input type="hidden" name="return_scope" value="{{ $selectedScope->value }}">
                                    <input type="hidden" name="return_report" value="{{ $selectedReportTag }}">
                                    <button type="submit" class="danger-button">Delete</button>
                                </form>
                            </div>
                        </div>

                        @if ($entry->obsidian_conflict)
                            <div class="mx-4 mb-4 p-3 rounded-cell border border-stone-900/10 bg-amber-50/70">
                                <p class="text-sm text-stone-700">This record has a conflict: both Obsidian and the database have changes since the last sync.</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if ($sourceMode === 'live')
                                        <form method="POST" action="{{ route('entries.conflict.accept-vault', $entry) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $type->value }}">
                                            <input type="hidden" name="scope" value="{{ $selectedScope->value }}">
                                            <input type="hidden" name="report" value="{{ $selectedReportTag }}">
                                            <button type="submit" class="secondary-button !text-xs !py-1.5 !px-3" title="Accept the Obsidian version and overwrite database changes">
                                                Accept Vault Version
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('entries.conflict.accept-db', $entry) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $type->value }}">
                                            <input type="hidden" name="scope" value="{{ $selectedScope->value }}">
                                            <input type="hidden" name="report" value="{{ $selectedReportTag }}">
                                            <button type="submit" class="secondary-button !text-xs !py-1.5 !px-3" title="Keep the database version and write it to Obsidian">
                                                Accept Database Version
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('reports.records.conflict.accept-vault', [$selectedReportGroup, $entry]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="secondary-button !text-xs !py-1.5 !px-3" title="Accept the Obsidian version and overwrite database changes">
                                                Accept Vault Version
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('reports.records.conflict.accept-db', [$selectedReportGroup, $entry]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="secondary-button !text-xs !py-1.5 !px-3" title="Keep the database version and write it to Obsidian">
                                                Accept Database Version
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </article>

                    <!-- Live Entry Edit Modal -->
                    @if ($sourceMode === 'live')
                        <x-entry-modal
                            :type="$type"
                            :entry="$entry"
                            :form-action="route('entries.update', ['entry' => $entry->id])"
                            :form-method="'PATCH'"
                            :academic-years="$academicYears"
                        />
                    @else
                        <!-- Saved Report Entry Edit Modal -->
                        <x-entry-modal
                            :type="$type"
                            :entry="$entry"
                            :report="$selectedReportGroup"
                            :form-action="route('reports.records.update', [$selectedReportGroup, $entry])"
                            :form-method="'PATCH'"
                            :academic-years="$academicYears"
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
                            Use Add entry to record your first activity.
                        @endif
                    </div>
                @endforelse
                @if ($entries->isNotEmpty())
                    <div x-cloak x-show="noMatchesFor($el.parentElement)" class="rounded-card border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm leading-7 text-stone-600">
                        No records match your search.
                    </div>
                @endif
            </div>
        </article>
    </section>
@endsection
