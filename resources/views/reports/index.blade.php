@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-panel">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.45fr_0.85fr] lg:items-center lg:px-10 lg:py-8">
            <div>
                <p class="section-kicker">Reports</p>
                <h1 class="mt-2 font-serif text-3xl leading-tight text-stone-900 sm:text-4xl">
                    Saved report snapshots alongside live data
                </h1>

                <dl class="mt-4 flex flex-wrap items-baseline gap-x-8 gap-y-3">
                    <div class="flex items-baseline gap-2">
                        <dt class="form-label">Report Groups</dt>
                        <dd class="font-semibold tabular-nums text-stone-900">{{ $reportGroups->count() }}</dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt class="form-label">Total Records</dt>
                        <dd class="font-semibold tabular-nums text-stone-900">{{ $totalRecords }}</dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt class="form-label">Total Hours</dt>
                        <dd class="font-semibold tabular-nums text-stone-900">{{ $grandTotalLabel }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                @if (app()->isLocal())
                    <form method="POST" action="{{ route('reports.sync-from-obsidian') }}" class="sync-form">
                        @csrf
                        <button type="submit" class="secondary-button w-auto">Sync from Obsidian</button>
                    </form>
                @endif

                <button type="button" class="primary-button" data-open-save-group-builder
                    aria-expanded="false" aria-controls="save-group-builder">
                    Save a Report
                </button>
            </div>
        </div>
    </section>

    @forelse ($reportGroups as $reportGroup)
        @php
            $reportMinutes = $reportGroup->items->sum('duration_minutes');
            $reportHours = intdiv($reportMinutes, 60);
            $reportRemainingMinutes = $reportMinutes % 60;
            $reportDurationLabel = $reportRemainingMinutes === 0
                ? $reportHours . ' hr'
                : sprintf('%d hr %02d min', $reportHours, $reportRemainingMinutes);
        @endphp
        <article class="paper-panel rounded-panel p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="section-kicker">Saved Snapshot</p>
                    <h2 class="mt-2 font-serif text-2xl leading-snug text-stone-950">
                        {{ $reportGroup->title ?: 'Untitled Report Group' }}
                    </h2>
                    <p class="mt-2 font-mono text-xs uppercase tracking-[0.14em] text-stone-500">{{ $reportGroup->tag }}</p>
                </div>

                <a href="{{ route('reports.show', $reportGroup) }}" class="primary-button shrink-0">Manage Report</a>
            </div>

            <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-cell border border-stone-900/10 bg-white/70 px-4 py-3">
                    <dt class="form-label">Records</dt>
                    <dd class="mt-1 font-serif text-2xl tabular-nums text-stone-950">{{ $reportGroup->items()->count() }}</dd>
                </div>
                <div class="rounded-cell border border-stone-900/10 bg-white/70 px-4 py-3">
                    <dt class="form-label">Hours</dt>
                    <dd class="mt-1 font-serif text-2xl tabular-nums text-stone-950">{{ $reportDurationLabel }}</dd>
                </div>
                <div class="rounded-cell border border-stone-900/10 bg-white/70 px-4 py-3">
                    <dt class="form-label">Types</dt>
                    <dd class="mt-2 flex flex-wrap gap-1.5">
                        @foreach (\App\Enums\IndexType::cases() as $type)
                            <span class="compact-pill">{{ $type->label() }}: {{ $reportGroup->itemsFor($type->value)->count() }}</span>
                        @endforeach
                    </dd>
                </div>
            </dl>
        </article>
    @empty
        <section class="paper-panel rounded-panel px-5 py-8 text-center sm:px-8">
            <p class="section-kicker">No Saved Reports</p>
            <h2 class="mt-2 font-serif text-2xl text-stone-950">No reports yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-stone-600">
                Pick unassigned live entries and group them into a report you can archive with an academic year.
            </p>
        </section>
    @endforelse

    @include('partials.save-group-builder', ['builderOpen' => $reportGroups->isEmpty()])
@endsection