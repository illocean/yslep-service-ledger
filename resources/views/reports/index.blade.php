@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    @include('partials.alerts')

    @php
        $hasSaveableEntries = collect($saveGroupEntries)
            ->contains(fn ($entries): bool => $entries->isNotEmpty());
        $showSaveGroupBuilder = $hasSaveableEntries || $errors->any() || filled(old('title')) || filled(old('selected_entries'));
    @endphp

    <section class="paper-panel overflow-hidden rounded-panel">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.5fr_0.85fr] lg:items-end lg:px-10 lg:py-8">
            <div>
                <p class="section-kicker">Reports</p>
                <h1 class="mt-3 max-w-2xl font-serif text-3xl leading-tight text-stone-950 sm:text-4xl">
                    Saved reports, ready to review and archive.
                </h1>
                <p class="mt-3 max-w-xl text-sm leading-7 text-stone-600">
                    Each report keeps a frozen copy of its records until you delete it.
                </p>

                <dl class="ruled-figures">
                    <div class="ruled-figures__item">
                        <dt class="form-label">Reports</dt>
                        <dd class="ruled-figures__value">{{ $reportGroups->count() }}</dd>
                    </div>
                    <div class="ruled-figures__item">
                        <dt class="form-label">Records</dt>
                        <dd class="ruled-figures__value">{{ $totalRecords }}</dd>
                    </div>
                    <div class="ruled-figures__item">
                        <dt class="form-label">Hours</dt>
                        <dd class="ruled-figures__value">{{ $grandTotalLabel }}</dd>
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

                @if ($hasSaveableEntries)
                    <button type="button" class="primary-button" data-open-save-group-builder
                        aria-expanded="false" aria-controls="save-group-builder">
                        Build a report
                    </button>
                @else
                    <span class="compact-pill">All live entries are saved</span>
                @endif
            </div>
        </div>
    </section>

    <section class="paper-panel rounded-panel p-5 sm:p-6" aria-labelledby="register-heading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="register-heading" class="font-serif text-2xl leading-tight text-stone-950">Saved report register</h2>
                <p class="mt-1 text-sm leading-6 text-stone-600">Newest first. Open a report to review its records or rename it.</p>
            </div>
            <span class="text-sm text-stone-600 tabular-nums">{{ $reportGroups->count() }} {{ $reportGroups->count() === 1 ? 'report' : 'reports' }}</span>
        </div>

        <ul class="report-register">
            @forelse ($reportGroups as $reportGroup)
                @php
                    $reportMinutes = $reportGroup->items->sum('duration_minutes');
                    $reportHours = intdiv($reportMinutes, 60);
                    $reportRemainingMinutes = $reportMinutes % 60;
                    $reportDurationLabel = $reportRemainingMinutes === 0
                        ? $reportHours . ' hr'
                        : sprintf('%d hr %02d min', $reportHours, $reportRemainingMinutes);
                @endphp

                <li class="register-row">
                    <div class="min-w-0">
                        <a href="{{ route('reports.show', $reportGroup) }}" class="register-row__title" title="Open {{ $reportGroup->compact_label }}">
                            {{ $reportGroup->title ?: 'Untitled report group' }}
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                        <div class="register-row__types">
                            @foreach (\App\Enums\IndexType::cases() as $type)
                                @if ($reportGroup->itemsFor($type->value)->isNotEmpty())
                                    <span class="register-row__type">{{ $type->label() }} <span class="tabular-nums">{{ $reportGroup->itemsFor($type->value)->count() }}</span></span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <span class="register-row__stat register-row__stat--records tabular-nums">{{ $reportGroup->items->count() }} records</span>
                    <span class="register-row__stat tabular-nums">{{ $reportDurationLabel }}</span>
                </li>
            @empty
                <li class="register-empty">
                    <section class="rounded-card border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center">
                        <p class="section-kicker">No Saved Reports</p>
                        <h2 class="mt-2 font-serif text-2xl text-stone-950">No reports yet</h2>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-stone-600">
                            Pick unassigned live entries and group them into a report you can archive with an academic year.
                        </p>
                    </section>
                </li>
            @endforelse
        </ul>
    </section>

    @if ($showSaveGroupBuilder)
        @include('partials.save-group-builder', ['builderOpen' => $reportGroups->isEmpty()])
    @endif
@endsection
