@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-panel">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.45fr_0.85fr] lg:px-10 lg:py-8">
            <div class="space-y-3">
                <div class="section-kicker">Reports</div>
                <h1 class="font-serif text-3xl leading-tight text-stone-900 sm:text-4xl">
                    Saved report snapshots alongside live data
                </h1>
            </div>

            <div class="paper-panel rounded-card p-4">
                <div class="section-kicker">Stats</div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Report Groups</div>
                        <div class="mt-1 font-serif text-2xl text-stone-950">{{ $reportGroups->count() }}</div>
                    </div>
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Total Records</div>
                        <div class="mt-1 font-serif text-2xl text-stone-950">{{ $totalRecords }}</div>
                    </div>
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Total Hours</div>
                        <div class="mt-1 font-bold text-stone-900">{{ $grandTotalLabel }}</div>
                    </div>
                    @if (app()->isLocal())
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Sync Action</div>
                        <form method="POST" action="{{ route('reports.sync-from-obsidian') }}" class="mt-2 sync-form">
                            @csrf
                            <button type="submit" class="secondary-button w-full">Sync from Obsidian</button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-6 mt-8">
        @forelse ($reportGroups as $reportGroup)
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

            <article class="paper-panel rounded-panel p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="section-kicker">Saved Snapshot</div>
                        <h2 class="mt-3 font-serif text-3xl text-stone-950">{{ $reportGroup->title ?: 'Untitled Report Group' }}</h2>
                        <p class="mt-2 font-mono text-xs text-stone-600">{{ $reportGroup->tag }}</p>
                    </div>

                    <div class="mt-2 text-right">
                        <a href="{{ route('reports.show', $reportGroup) }}" class="primary-button">
                            Manage Report
                        </a>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Records</div>
                        <div class="mt-2 text-sm font-semibold text-stone-900">{{ $reportGroup->items->count() }}</div>
                    </div>
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Hours</div>
                        <div class="mt-2 text-sm font-semibold text-stone-900">{{ $reportTotalLabel }}</div>
                    </div>
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Types</div>
                        <div class="mt-1 text-xs uppercase tracking-[0.16em] text-stone-600">
                            @foreach (\App\Enums\IndexType::cases() as $type)
                                {{ $type->label() }}: {{ $reportGroup->itemsFor($type->value)->count() }}@if (!$loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-stone-600">
                    @foreach (\App\Enums\IndexType::cases() as $type)
                        <span class="rounded-full border border-stone-900/10 bg-white/80 px-3 py-2">
                            {{ $type->label() }}: {{ $reportGroup->itemsFor($type->value)->count() }}
                        </span>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="paper-panel rounded-panel px-5 py-10 text-center sm:px-8">
                <div class="section-kicker">No Saved Reports</div>
                <h2 class="mt-3 font-serif text-2xl text-stone-950">No reports yet</h2>
                <p class="mx-auto mt-3 max-w-lg text-sm text-stone-600">
                    Create your first saved report from the dashboard overview, or use the quick-add bar to add entries directly.
                </p>
                <div class="mt-6">
                    <a href="{{ route('dashboard') }}" class="primary-button">Back to Overview</a>
                </div>
            </div>
        @endforelse
    </section>
@stop