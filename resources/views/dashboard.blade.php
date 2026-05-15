@extends('layouts.app')

@section('title', 'YSLEP Overview')

@section('content')
    @php
        $selectedEntries = collect(old('selected_entries', []));
        $saveableTypes = collect(\App\Enums\IndexType::cases())
            ->filter(fn (\App\Enums\IndexType $type) => $saveGroupEntries[$type->value]->isNotEmpty())
            ->values();
        $hiddenTypes = collect(\App\Enums\IndexType::cases())
            ->reject(fn (\App\Enums\IndexType $type) => $saveGroupEntries[$type->value]->isNotEmpty())
            ->values();
    @endphp

    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-[2rem]">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.45fr_0.85fr] lg:items-start lg:px-10 lg:py-8">
            <div class="space-y-3">
                <div class="section-kicker">Dashboard</div>
                <h1 class="font-serif text-4xl leading-tight text-stone-900 sm:text-5xl">
                    All-time service ledger from your three Obsidian inputs.
                </h1>
            </div>

            <div class="paper-panel rounded-[1.5rem] p-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="section-kicker">Saved Reports</div>
                    <span class="font-serif text-2xl text-stone-950">{{ str_pad((string) $reportGroups->count(), 2, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('reports.index') }}" class="primary-button w-full">
                        Open Saved Reports
                    </a>

                    @if ($reportGroups->isNotEmpty())
                        <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-4">
                            <div class="form-label">Latest</div>
                            <p class="mt-1 text-sm font-semibold text-stone-900">{{ $reportGroups->first()->display_label }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-4">
        @foreach ($cards as $card)
            <article class="stat-panel rounded-[1.75rem] p-5">
                <div class="section-kicker">{{ $card['label'] }}</div>
                <div class="mt-5 flex items-end justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Total count</div>
                        <div class="mt-2 font-serif text-4xl text-stone-950">{{ str_pad((string) $card['count'], 2, '0', STR_PAD_LEFT) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Total hours</div>
                        <div class="mt-2 text-lg font-bold text-stone-900">{{ $card['total_label'] }}</div>
                    </div>
                </div>
            </article>
        @endforeach

        <article class="stat-panel rounded-[1.75rem] border-[color:var(--ledger-accent)] p-5">
            <div class="section-kicker">Grand Total</div>
            <div class="mt-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Combined hours across all three indexes</div>
                <div class="mt-3 font-serif text-4xl text-stone-950">{{ $grandTotalLabel }}</div>
            </div>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        @foreach (\App\Enums\IndexType::cases() as $type)
            @php
                $card = $cards[$type->value];
                $previewEntries = $entries[$type->value];
                $profile = $meta[$type->value]['profile'];
                $manageRoute = route('indexes.show', ['type' => $type->value]);
            @endphp

            <article class="paper-panel flex flex-col rounded-[2rem] p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="section-kicker">{{ $card['card_title'] }}</div>
                        <h2 class="mt-3 font-serif text-2xl text-stone-950">{{ $card['label'] }}</h2>
                        <p class="mt-2 text-sm leading-7 text-stone-600">
                            File: <span class="font-mono text-xs">{{ $meta[$type->value]['file_name'] }}</span>
                        </p>
                    </div>

                    <div class="rounded-full border border-stone-900/10 bg-white/70 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                        {{ $card['count'] }} record(s)
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">School year</div>
                        <div class="mt-2 text-sm font-semibold text-stone-900">{{ $profile['school_year'] ?: 'Not set' }}</div>
                    </div>
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Locked in reports</div>
                        <div class="mt-2 text-sm font-semibold text-stone-900">{{ $liveEntryStats[$type->value]['locked'] }}</div>
                    </div>
                </div>

                <div class="mt-5 flex-1 rounded-[1.5rem] border border-stone-900/10 bg-white/75 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="form-label">All Time Preview</div>
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">{{ $card['total_label'] }}</div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($previewEntries as $entry)
                            @php
                                $assignment = $assignedReportLookup[$type->value . ':' . $entry->id] ?? null;
                            @endphp

                            <div class="rounded-[1.25rem] border border-stone-900/8 bg-stone-50/80 px-4 py-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-sm font-semibold text-stone-900">{{ $entry->served_on_label }}</div>
                                        <div class="mt-1 text-sm text-stone-600">
                                            @if ($type === \App\Enums\IndexType::Formation)
                                                {{ $entry->cycle_code }} / {{ $entry->module_code }} - {{ $entry->title }}
                                            @elseif ($type === \App\Enums\IndexType::SocialApostolate)
                                                {{ $entry->about }}
                                            @else
                                                Parish Involvement
                                            @endif
                                        </div>

                                        @if ($assignment)
                                            <div class="assignment-chip assignment-chip--saved mt-2" title="Saved in {{ $assignment->display_label }}">
                                                <span class="assignment-chip__dot" aria-hidden="true"></span>
                                                <span>{{ $assignment->compact_label }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="text-right text-sm text-stone-600">
                                        <div>{{ $entry->time_start_label }} - {{ $entry->time_end_label }}</div>
                                        <div class="mt-1 font-semibold text-stone-900">{{ $entry->duration_label }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[1.25rem] border border-dashed border-stone-900/12 bg-stone-50/50 px-4 py-5 text-sm text-stone-500">
                                No records yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-5 flex items-start justify-between gap-3 pt-1 sm:items-center">
                    <div class="text-sm text-stone-600">
                        Open the dedicated {{ strtolower($card['label']) }} page for the full all-time ledger and live input form.
                    </div>

                    <a href="{{ $manageRoute }}" class="primary-button">
                        Open Page
                    </a>
                </div>
            </article>
        @endforeach
    </section>

    <section class="paper-panel rounded-[2rem] p-5 sm:p-6">
        <details class="group" @if($errors->any() || old('title')) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 [&::-webkit-details-marker]:hidden">
                <div>
                    <div class="section-kicker">Save Report Group</div>
                    <h2 class="mt-2 font-serif text-2xl text-stone-950">Build a saved report from unassigned live entries</h2>
                </div>
                <div class="flex items-center gap-2 text-sm font-semibold text-stone-600 transition-transform group-open:rotate-180">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
                </div>
            </summary>

            <form method="POST" action="{{ route('report-groups.store') }}" class="mt-5 space-y-5">
                @csrf

                <div class="max-w-lg">
                    <label for="title" class="form-label">Report title</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" placeholder="e.g. Christmas break service report" class="form-input mt-2">
                </div>

                @if ($hiddenTypes->isNotEmpty())
                    <div class="rounded-[1.5rem] border border-stone-900/10 bg-stone-50/70 p-4">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-stone-600">
                            <span class="font-semibold">Already saved:</span>
                            @foreach ($hiddenTypes as $type)
                                <span class="assignment-chip assignment-chip--complete">{{ $type->label() }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($saveableTypes->isEmpty())
                    <div class="rounded-[1.5rem] border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm text-stone-600">
                        All categories are saved or locked. Create new live entries in Obsidian first.
                    </div>
                @else
                    <div class="save-group-grid">
                        @foreach ($saveableTypes as $type)
                            <article class="rounded-[1.5rem] border border-stone-900/10 bg-white/70 p-4" data-save-group-card="{{ $type->value }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="section-kicker">{{ $type->cardTitle() }}</div>
                                        <h3 class="mt-1 font-serif text-xl text-stone-950">{{ $type->label() }}</h3>
                                    </div>
                                    <div class="text-right text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">
                                        {{ $liveEntryStats[$type->value]['available'] }} available
                                    </div>
                                </div>

                                <div class="clean-scroll mt-3 max-h-[30rem] overflow-auto rounded-[1.25rem] border border-stone-900/10 bg-stone-50/70">
                                    <table class="ledger-table min-w-full text-left text-sm">
                                        <thead class="bg-stone-950/[0.03] text-xs uppercase tracking-[0.14em] text-stone-600">
                                            <tr>
                                                <th class="px-3 py-2">Pick</th>
                                                <th class="px-3 py-2">Date</th>
                                                <th class="px-3 py-2">Details</th>
                                                <th class="px-3 py-2">Hrs</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($saveGroupEntries[$type->value] as $entry)
                                                @php $entryValue = $type->value . ':' . $entry->id; @endphp
                                                <tr>
                                                    <td class="px-3 py-2 align-top">
                                                        <input type="checkbox" name="selected_entries[]" value="{{ $entryValue }}" @checked($selectedEntries->contains($entryValue)) class="report-checkbox">
                                                    </td>
                                                    <td class="px-3 py-2 align-top whitespace-nowrap">{{ $entry->served_on_label }}</td>
                                                    <td class="px-3 py-2 align-top text-stone-600">
                                                        @if ($type === \App\Enums\IndexType::Formation)
                                                            <div>{{ $entry->cycle_code }} / {{ $entry->module_code }}</div>
                                                            <div class="mt-1">{{ $entry->title }}</div>
                                                        @elseif ($type === \App\Enums\IndexType::SocialApostolate)
                                                            <div>{{ $entry->about }}</div>
                                                        @else
                                                            <div>Parish Involvement</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 align-top whitespace-nowrap">{{ $entry->duration_label }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-end gap-4">
                    <button type="submit" class="primary-button">Save Report</button>
                </div>
            </form>
        </details>
    </section>
@endsection
