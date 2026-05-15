@extends('layouts.app')

@section('title', 'Academic Year Snapshots')

@section('content')
    @php
        $selectedReportGroups = collect(old('selected_report_groups', []))->map(fn ($id) => (int) $id);
    @endphp

    @include('partials.alerts')

    <section class="compact-hero paper-panel rounded-[2rem] px-5 py-5 sm:px-7">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="space-y-2">
                <div class="section-kicker">Academic Year Snapshots</div>
                <h1 class="font-serif text-3xl leading-tight text-stone-950 sm:text-4xl">
                    Archive saved reports into one academic-year layer.
                </h1>
                <p class="max-w-3xl text-sm leading-7 text-stone-600">
                    This works like saved reports, one level higher. Pick whole saved reports, and once a report is archived here it disappears from the picker automatically.
                </p>
            </div>

            <div class="compact-summary-grid">
                <div class="compact-summary-card">
                    <div class="form-label">Snapshots</div>
                    <div class="mt-2 font-serif text-2xl text-stone-950">{{ str_pad((string) $snapshots->count(), 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="compact-summary-card">
                    <div class="form-label">Available Reports</div>
                    <div class="mt-2 font-serif text-2xl text-stone-950">{{ str_pad((string) $availableReportGroups->count(), 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="compact-summary-card compact-summary-card--wide">
                    <div class="form-label">Obsidian Snapshot File</div>
                    <div class="mt-2 break-all font-mono text-[0.72rem] text-stone-700">{{ $snapshotFilePath }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="snapshot-builder-grid">
        <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="section-kicker">Create Snapshot</div>
                    <h2 class="mt-3 font-serif text-2xl text-stone-950">Select available saved reports</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-7 text-stone-600">
                        Each saved report can belong to only one academic-year snapshot. Anything already archived is hidden from this list and moved to the side panel.
                    </p>
                </div>
                <div class="rounded-full border border-stone-900/10 bg-white/70 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                    {{ $selectedReportGroups->count() }} selected
                </div>
            </div>

            <form method="POST" action="{{ route('academic-year-snapshots.store') }}" class="mt-5 space-y-5">
                @csrf

                <div class="snapshot-builder-toolbar">
                    <div class="snapshot-builder-toolbar__field">
                        <label for="title" class="form-label">Snapshot title</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" placeholder="Example: AY 2025-2026 Final Archive" class="form-input mt-2">
                    </div>

                    <div class="snapshot-builder-toolbar__meta">
                        <div class="compact-stat">
                            <div class="form-label">Available</div>
                            <div class="mt-2 text-sm font-semibold text-stone-900">{{ $availableReportGroups->count() }} report(s)</div>
                        </div>
                        <div class="compact-stat">
                            <div class="form-label">Archived</div>
                            <div class="mt-2 text-sm font-semibold text-stone-900">{{ $archivedReportGroups->count() }} hidden</div>
                        </div>
                    </div>

                    <div class="snapshot-builder-toolbar__action">
                        <button type="submit" class="primary-button" @disabled($availableReportGroups->isEmpty())>
                            Create Academic Year Snapshot
                        </button>
                    </div>
                </div>

                @if ($availableReportGroups->isEmpty())
                    <div class="rounded-[1.5rem] border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm leading-7 text-stone-600">
                        No saved reports are currently available for a new academic-year snapshot.
                    </div>
                @else
                    <div class="archive-report-list">
                        @foreach ($availableReportGroups as $reportGroup)
                            @php
                                $reportMinutes = $reportGroup->items->sum('duration_minutes');
                                $reportHours = intdiv($reportMinutes, 60);
                                $reportRemainingMinutes = $reportMinutes % 60;
                                $reportTotalLabel = $reportMinutes === 0
                                    ? '0 hr'
                                    : ($reportRemainingMinutes === 0
                                        ? $reportHours . ' hr'
                                        : sprintf('%d hr %02d min', $reportHours, $reportRemainingMinutes));
                            @endphp

                            <label class="archive-report-card" data-available-archive-report="{{ $reportGroup->id }}">
                                <div class="archive-report-card__main">
                                    <input
                                        type="checkbox"
                                        name="selected_report_groups[]"
                                        value="{{ $reportGroup->id }}"
                                        @checked($selectedReportGroups->contains($reportGroup->id))
                                        class="report-checkbox mt-1"
                                    >

                                    <div class="min-w-0 flex-1">
                                        <div class="section-kicker">Saved Report</div>
                                        <div class="mt-2 font-serif text-[1.45rem] leading-tight text-stone-950">{{ $reportGroup->compact_label }}</div>
                                        <div class="mt-2 text-xs uppercase tracking-[0.16em] text-stone-500">
                                            {{ $reportGroup->tag }} | saved {{ $reportGroup->created_at?->setTimezone(config('app.timezone'))->format('M j, Y') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="archive-report-card__stats">
                                    <span class="compact-pill">{{ $reportGroup->items->count() }} record(s)</span>
                                    <span class="compact-pill">{{ $reportTotalLabel }}</span>
                                    @foreach (\App\Enums\IndexType::cases() as $type)
                                        @php
                                            $typeCount = $reportGroup->itemsFor($type->value)->count();
                                        @endphp

                                        @if ($typeCount > 0)
                                            <span class="compact-pill compact-pill--soft">
                                                {{ $type->label() }} {{ $typeCount }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </form>
        </article>

        <aside class="space-y-6">
            <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
                <div class="section-kicker">Already Archived</div>
                <h2 class="mt-3 font-serif text-2xl text-stone-950">Hidden from the picker</h2>
                <p class="mt-2 text-sm leading-7 text-stone-600">
                    Saved reports already assigned to an academic-year snapshot are hidden automatically to prevent duplicates.
                </p>

                @if ($archivedReportGroups->isEmpty())
                    <div class="mt-4 rounded-[1.5rem] border border-dashed border-stone-900/12 bg-stone-50/60 px-4 py-5 text-sm text-stone-500">
                        Nothing has been archived yet.
                    </div>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($archivedReportGroups as $archived)
                            <div class="compact-archive-chip" data-hidden-archive-report="{{ $archived['reportGroup']->id }}">
                                <div>
                                    <div class="text-sm font-semibold text-stone-900">{{ $archived['reportGroup']->compact_label }}</div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.14em] text-stone-500">In {{ $archived['snapshot']->compact_label }}</div>
                                </div>
                                <a href="{{ route('academic-year-snapshots.show', $archived['snapshot']) }}" class="secondary-button compact-inline-button">
                                    Open
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>

            <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
                <div class="section-kicker">Current Snapshots</div>
                <div class="mt-4 space-y-3">
                    @forelse ($snapshots as $snapshot)
                        <a href="{{ route('academic-year-snapshots.show', $snapshot) }}" class="secondary-link-card">
                            <div>
                                <div class="text-sm font-semibold text-stone-900">{{ $snapshot->compact_label }}</div>
                                <div class="mt-1 text-xs uppercase tracking-[0.18em] text-stone-500">
                                    {{ $snapshot->reportGroups()->count() }} report(s) | {{ $snapshot->items->count() }} record(s)
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-stone-900">Open</div>
                        </a>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-stone-900/12 bg-stone-50/60 px-4 py-5 text-sm text-stone-500">
                            No academic-year snapshots yet.
                        </div>
                    @endforelse
                </div>
            </article>
        </aside>
    </section>
@endsection
