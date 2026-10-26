@extends('layouts.app')

@section('title', $type->cardTitle())

@section('content')
    @include('partials.alerts')

    @php
        $allScopeParams = ['type' => $type->value];
        $unsavedScopeParams = ['type' => $type->value, 'scope' => \App\Enums\IndexScope::Unsaved->value];

        if (filled($monthFilter)) {
            $allScopeParams['month'] = $monthFilter;
            $unsavedScopeParams['month'] = $monthFilter;
        }

        $ledgerHeading = match ($selectedScope) {
            \App\Enums\IndexScope::All => 'All records',
            \App\Enums\IndexScope::Unsaved => 'Unsaved records',
            \App\Enums\IndexScope::Report => 'Records in this saved report',
        };
    @endphp

    <section class="paper-panel rounded-panel px-5 py-6 sm:px-8 lg:px-10">
        <div class="flex flex-wrap items-start justify-between gap-6">
            <div class="min-w-0">
                <p class="section-kicker">{{ $sourceMode === 'report' ? 'Saved report' : $selectedScopeLabel }}</p>
                <h1 class="mt-2 font-serif text-3xl leading-tight text-stone-950 sm:text-4xl">{{ $type->label() }}</h1>
                @if ($sourceMode === 'report' && $selectedReportGroup)
                    <p class="mt-2 text-sm font-semibold text-stone-800">{{ $selectedReportGroup->compact_label }}</p>
                    <p class="mt-3 max-w-2xl rounded-cell border border-stone-900/10 bg-white/60 px-4 py-3 text-sm leading-6 text-stone-700">
                        Snapshot records. Edits and deletes here stay in this saved report and do not change the live ledger.
                    </p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-stone-600">
                    <span class="tabular-nums">{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'entry' : 'entries' }}</span>
                    <span aria-hidden="true">·</span>
                    <span class="tabular-nums">{{ $summary['total_label'] }} served</span>
                    @if (filled($monthFilter))
                        <a href="{{ route('indexes.show', ['type' => $type->value]) }}" class="compact-pill compact-pill--soft" title="Clear the month filter and show every dated entry">
                            {{ $monthLabel }} <span aria-hidden="true">&times;</span><span class="sr-only">— clear month filter</span>
                        </a>
                    @endif
                    @if (filled($cardMeta['profile']['school_year'] ?? null))
                        <span class="rounded-full border border-stone-900/10 bg-white/70 px-3 py-1 text-xs font-semibold">
                            {{ $cardMeta['profile']['school_year'] }}
                        </span>
                    @endif
                </div>
            </div>

            <button type="button" class="primary-button shrink-0" @click="$dispatch('quick-add-open', { type: '{{ $type->value }}' })" aria-haspopup="dialog">
                + Add {{ $type->label() }}
            </button>
        </div>

        <div class="mt-6 flex flex-wrap items-end gap-4 border-t border-stone-900/10 pt-5">
            <nav class="flex gap-2" aria-label="Record scope">
                <a href="{{ route('indexes.show', $allScopeParams) }}" class="topbar-link {{ $selectedScope === \App\Enums\IndexScope::All ? 'is-active' : '' }}">All entries</a>
                <a href="{{ route('indexes.show', $unsavedScopeParams) }}" class="topbar-link {{ $selectedScope === \App\Enums\IndexScope::Unsaved ? 'is-active' : '' }}">Unsaved only</a>
            </nav>

            <form method="GET" action="{{ route('indexes.show', ['type' => $type->value]) }}" class="min-w-0 flex-1 sm:max-w-sm">
                <input type="hidden" name="scope" value="report">
                @if (filled($monthFilter))
                    <input type="hidden" name="month" value="{{ $monthFilter }}">
                @endif
                <label for="report" class="form-label">Saved report</label>
                <select id="report" name="report" class="form-input mt-1" data-auto-submit>
                    <option value="">Choose a report</option>
                    @foreach ($reportGroups as $reportGroup)
                        <option value="{{ $reportGroup->tag }}" @selected($selectedReportTag === $reportGroup->tag)>{{ $reportGroup->compact_label }}</option>
                    @endforeach
                </select>
            </form>

            @if ($selectedScope === \App\Enums\IndexScope::Report && $selectedReportGroup)
                <a href="{{ route('reports.show', $selectedReportGroup) }}" class="secondary-button w-auto sm:ml-auto">
                    Open saved report
                </a>
            @endif
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.45fr_0.85fr]">
        <article class="paper-panel rounded-panel p-5 sm:p-6" x-data="ledgerSearch">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="section-kicker">Ledger</p>
                    <h2 class="mt-2 font-serif text-xl text-stone-950">{{ $ledgerHeading }}</h2>
                </div>
                <p class="text-sm text-stone-600 tabular-nums">{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'record' : 'records' }}</p>
            </div>

            <div class="mt-4">
                <label for="ledger-search" class="sr-only">Search records</label>
                <input
                    id="ledger-search"
                    type="search"
                    class="form-input"
                    placeholder="Search title, date, academic year, role..."
                    x-model.debounce.150ms="q"
                >
            </div>

            <div x-ref="rows" class="mt-4 space-y-2">
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
                            $entry->duration_label,
                            $entry->time_start_label,
                            $entry->time_end_label,
                        ])));
                    @endphp

                    <!-- Record Card -->
                    <article class="report-record-card rounded-cell" data-search="{{ $searchable }}" x-show="match($el)">
                        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <div class="min-w-0 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="compact-pill">{{ $entry->duration_label }}</span>
                                    @if ($assignment)
                                        <x-assignment-chip variant="saved" :label="$assignment->compact_label" />
                                    @endif
                                    @if ($entry->obsidian_conflict)
                                        <span class="compact-pill bg-amber-100 text-amber-900" title="Conflict detected: both Obsidian and database have changes since last sync">Conflict</span>
                                    @endif
                                </div>

                                <p class="text-sm font-semibold text-stone-900">{{ $headline }}</p>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-600">
                                    <span class="tabular-nums">{{ $entry->served_on_label }}</span>
                                    <span class="tabular-nums">{{ $entry->time_start_label }}&ndash;{{ $entry->time_end_label }}</span>
                                    @if ($entry->academic_year)<span>AY {{ $entry->academic_year }}</span>@endif
                                    @if ($entry->role_in_activity)<span>{{ $entry->role_in_activity }}</span>@endif
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <button
                                    type="button"
                                    class="compact-pill hover:text-stone-900"
                                    data-edit-entry-trigger
                                    data-type="{{ $type->value }}"
                                    data-entry-id="{{ $entry->id }}"
                                    data-source-mode="{{ $sourceMode }}"
                                    aria-label="Edit {{ $headline }}"
                                    @click="window.dispatchEvent(new CustomEvent('open-entry-modal', { detail: { type: $el.dataset.type, entryId: parseInt($el.dataset.entryId), isEdit: true } }))"
                                >Edit</button>

                                <form method="POST"
                                    action="{{ $sourceMode === 'live' ? route('entries.destroy', $entry->id) : route('reports.records.destroy', [$selectedReportGroup, $entry]) }}"
                                    data-confirm="{{ $sourceMode === 'live' ? 'Delete this live entry from the ledger and Obsidian?' : 'Remove this record from the saved report? The live ledger entry will not be deleted.' }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="type" value="{{ $type->value }}">
                                    <input type="hidden" name="return_type" value="{{ $type->value }}">
                                    <input type="hidden" name="return_scope" value="{{ $selectedScope->value }}">
                                    <input type="hidden" name="return_report" value="{{ $selectedReportTag }}">
                                    <button type="submit" class="compact-pill hover:text-rose-700" aria-label="{{ $sourceMode === 'live' ? 'Delete' : 'Remove' }} {{ $headline }}">
                                        {{ $sourceMode === 'live' ? 'Delete' : 'Remove' }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($entry->obsidian_conflict)
                            <div class="border-t border-stone-900/10 bg-amber-50/60 px-4 py-3">
                                <p class="text-sm text-stone-700">Obsidian and the database both changed since the last sync. Choose which copy to keep.</p>

                                @if ($sourceMode === 'live')
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('entries.conflict.accept-vault', $entry->id) }}" data-dirty-guard data-confirm="Keep the Obsidian copy? The database change to this live entry will be lost.">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $type->value }}">
                                            <input type="hidden" name="scope" value="{{ $selectedScope->value }}">
                                            <input type="hidden" name="report" value="{{ $selectedReportTag }}">
                                            <button type="submit" class="secondary-button compact-inline-button">Keep Obsidian copy</button>
                                        </form>
                                        <form method="POST" action="{{ route('entries.conflict.accept-db', $entry->id) }}" data-dirty-guard data-confirm="Keep the database copy? The Obsidian change to this live entry will be lost.">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $type->value }}">
                                            <input type="hidden" name="scope" value="{{ $selectedScope->value }}">
                                            <input type="hidden" name="report" value="{{ $selectedReportTag }}">
                                            <button type="submit" class="secondary-button compact-inline-button">Keep database copy</button>
                                        </form>
                                    </div>
                                @else
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('reports.records.conflict.accept-vault', [$selectedReportGroup, $entry]) }}" data-confirm="Keep the Obsidian copy? The database change to this saved record will be lost.">
                                            @csrf
                                            <button type="submit" class="secondary-button compact-inline-button">Keep Obsidian copy</button>
                                        </form>
                                        <form method="POST" action="{{ route('reports.records.conflict.accept-db', [$selectedReportGroup, $entry]) }}" data-confirm="Keep the database copy? The Obsidian change to this saved record will be lost.">
                                            @csrf
                                            <button type="submit" class="secondary-button compact-inline-button">Keep database copy</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($sourceMode === 'live')
                            <x-entry-modal
                                :type="$type"
                                :entry="$entry"
                                :form-action="route('entries.update', $entry->id)"
                                form-method="PATCH"
                                :academic-years="$academicYears"
                            />
                        @else
                            <x-entry-modal
                                :type="$type"
                                :entry="$entry"
                                :report="$selectedReportGroup"
                                :form-action="route('reports.records.update', [$selectedReportGroup, $entry])"
                                form-method="PATCH"
                                :academic-years="$academicYears"
                            />
                        @endif
                    </article>
                @empty
                    <div class="rounded-cell border border-dashed border-stone-900/12 bg-stone-50/50 px-4 py-8 text-center">
                        <p class="text-sm text-stone-600">
                            @if ($sourceMode === 'report')
                                No records in this saved report yet.
                            @elseif (filled($monthFilter))
                                No {{ strtolower($type->label()) }} records in {{ $monthLabel }}.
                            @else
                                No {{ strtolower($type->label()) }} records in this scope.
                            @endif
                        </p>
                        @if ($sourceMode === 'live')
                            <button type="button" class="primary-button mt-4" @click="$dispatch('quick-add-open', { type: '{{ $type->value }}' })" aria-haspopup="dialog">
                                Add your first {{ $type->label() }} entry
                            </button>
                        @endif
                    </div>
                @endforelse

                <div x-cloak x-show="noMatchesFor($refs.rows)" class="rounded-card border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm leading-7 text-stone-600">
                    No records match your search.
                </div>
            </div>
        </article>

        <aside class="space-y-4">
            <section class="paper-panel rounded-panel p-5 sm:p-6">
                <p class="section-kicker">Other indexes</p>
                <h2 class="mt-2 font-serif text-xl text-stone-950">Elsewhere in the ledger</h2>

                <div class="mt-4 grid gap-2">
                    @foreach (\App\Enums\IndexType::cases() as $otherType)
                        @continue($otherType === $type)

                        @php
                            $otherParams = ['type' => $otherType->value];

                            if (filled($monthFilter)) {
                                $otherParams['month'] = $monthFilter;
                            }

                            if ($sourceMode === 'report' && $selectedReportTag) {
                                $otherParams['scope'] = \App\Enums\IndexScope::Report->value;
                                $otherParams['report'] = $selectedReportTag;
                            } elseif ($selectedScope === \App\Enums\IndexScope::Unsaved) {
                                $otherParams['scope'] = \App\Enums\IndexScope::Unsaved->value;
                            }

                            $otherSummary = $otherCards[$otherType->value] ?? null;
                        @endphp

                        @if ($otherSummary)
                            <a href="{{ route('indexes.show', $otherParams) }}" class="secondary-link-card">
                                <span class="text-sm font-semibold text-stone-900">{{ $otherType->label() }}</span>
                                <span class="text-right text-xs font-semibold tabular-nums text-stone-600">
                                    {{ $otherSummary['count'] }} {{ $otherSummary['count'] === 1 ? 'record' : 'records' }}<br>
                                    {{ $otherSummary['total_label'] }}
                                </span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>

            <section class="paper-panel rounded-panel p-5 sm:p-6">
                <p class="section-kicker">Profile</p>
                <h2 class="mt-2 font-serif text-xl text-stone-950">Ledger file</h2>
                <p class="mt-2 font-mono text-xs text-stone-600">{{ $cardMeta['file_name'] }}</p>
                <p class="mt-1 text-xs leading-5 text-stone-600">Synced from the vault when this page opens.</p>

                @if (collect($cardMeta['profile'])->filter(fn ($value): bool => filled($value))->isNotEmpty())
                    <dl class="mt-4 space-y-2 text-sm">
                        @foreach ($cardMeta['profile'] as $key => $value)
                            @if (filled($value))
                                <div class="flex items-baseline justify-between gap-4 border-b border-stone-900/8 pb-2 last:border-0">
                                    <dt class="form-label">{{ str($key)->replace('_', ' ')->title() }}</dt>
                                    <dd class="text-right font-semibold text-stone-900">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                @endif
            </section>
        </aside>
    </div>
@endsection
