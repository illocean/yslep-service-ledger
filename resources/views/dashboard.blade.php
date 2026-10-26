@extends('layouts.app')

@section('title', 'YSLEP Overview')

@section('content')
    @php
        $totalEntries = collect($cards)->sum('count');
        $unassignedTotal = collect($liveEntryStats)->sum(fn (array $stats): int => $stats['available']);
        $lockedTotal = collect($liveEntryStats)->sum(fn (array $stats): int => $stats['locked']);
        $unassignedLabels = collect(\App\Enums\IndexType::cases())
            ->filter(fn (\App\Enums\IndexType $type): bool => $liveEntryStats[$type->value]['available'] > 0)
            ->map(fn (\App\Enums\IndexType $type): string => $type->label())
            ->all();
        $hasSaveableEntries = collect($saveGroupEntries)
            ->contains(fn ($entries): bool => $entries->isNotEmpty());
        $latestReport = $reportGroups->first();
    @endphp

    @include('partials.alerts')

    {{-- Head spread: what the ledger holds, and the figure that answers "how much" --}}
    <section class="paper-panel overflow-hidden rounded-panel" aria-labelledby="ledger-heading">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.5fr_0.85fr] lg:items-start lg:px-10 lg:py-8">
            <div>
                <p class="section-kicker">Overview</p>
                <h1 id="ledger-heading" class="mt-3 max-w-2xl font-serif text-4xl leading-tight text-stone-950 sm:text-5xl">
                    All-time service ledger from your three Obsidian inputs.
                </h1>
                <p class="mt-4 max-w-xl text-sm leading-7 text-stone-600">
                    Entries stay live until you group them into a saved report. Hours are summed from the recorded time in and time out.
                </p>

                <dl class="mt-6 ruled-figures">
                    <div class="ruled-figures__item">
                        <dt class="form-label">Entries</dt>
                        <dd class="ruled-figures__value">{{ $totalEntries }}</dd>
                    </div>
                    <div class="ruled-figures__item">
                        <dt class="form-label">Saved Reports</dt>
                        <dd class="ruled-figures__value">{{ $reportGroups->count() }}</dd>
                    </div>
                    <div class="ruled-figures__item">
                        <dt class="form-label">Unassigned</dt>
                        <dd class="ruled-figures__value {{ $unassignedTotal > 0 ? 'text-[color:var(--ledger-accent-ink)]' : '' }}">{{ $unassignedTotal }}</dd>
                    </div>
                </dl>
            </div>

            <div class="total-cell">
                <p class="section-kicker">Time Served</p>
                <p class="total-cell__figure">{{ $grandTotalLabel }}</p>
                <p class="total-cell__note">Combined hours across all three indexes, {{ $series['periodLabel'] ?? 'this ledger' }}.</p>

                <div class="total-cell__trend">
                    <span class="form-label">Last month served</span>
                    @if ($series['lastActiveMonth'])
                        <span class="total-cell__trend-value">{{ $series['lastActiveMonth']['total_label'] }}</span>
                        <span class="total-cell__trend-note">
                            {{ $series['lastActiveMonth']['name'] }} {{ $series['lastActiveMonth']['year'] }}
                            @if ($series['monthsSinceLast'] === 0)
                                &mdash; this month is still open.
                            @else
                                &mdash; nothing recorded in {{ implode(', ', $series['emptyMonthsSince']) }}{{ $series['monthsSinceLast'] > count($series['emptyMonthsSince']) ? ', …' : '' }}.
                            @endif
                        </span>
                    @else
                        <span class="total-cell__trend-value">0 hr</span>
                        <span class="total-cell__trend-note">no dated entry has been served yet.</span>
                    @endif
                </div>

                @if ($latestReport)
                    <div class="total-cell__report">
                        <p class="form-label">Latest Report</p>
                        <p class="mt-1 text-sm font-semibold text-stone-900">{{ $latestReport->compact_label }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <a href="{{ route('reports.show', $latestReport) }}" class="secondary-button compact-inline-button">Open report</a>
                            <a href="{{ route('reports.index') }}" class="text-link text-xs">View all reports</a>
                        </div>
                    </div>
                @else
                    <a href="{{ route('reports.index') }}" class="secondary-button compact-inline-button mt-5">View all reports</a>
                @endif
            </div>
        </div>

        {{-- What needs me next, as a ruled footer on the same sheet --}}
        <div class="attention-strip px-5 py-4 sm:px-8 lg:px-10">
            <span class="attention-rule {{ $unassignedTotal > 0 ? 'attention-rule--open' : '' }}" aria-hidden="true"></span>
            <div class="attention-strip__copy">
                <h2 id="attention-heading" class="font-serif text-lg leading-tight text-stone-950">
                    @if ($totalEntries === 0)
                        The ledger is empty
                    @elseif ($unassignedTotal > 0)
                        {{ $unassignedTotal }} {{ $unassignedTotal === 1 ? 'entry' : 'entries' }} ready to save
                    @else
                        Every entry is saved
                    @endif
                </h2>
                <p class="mt-0.5 text-sm leading-6 text-stone-600">
                    @if ($totalEntries === 0)
                        Nothing has been synced from Obsidian yet. Add a record in the vault, then sync here.
                    @elseif ($unassignedTotal > 0)
                        Unassigned entries in {{ implode(', ', $unassignedLabels) }} — group them into a saved report below.
                    @else
                        Nothing is waiting: every live entry already belongs to a saved report.
                    @endif
                </p>
            </div>

            <div class="attention-strip__action">
                @if ($totalEntries === 0)
                    <button type="button" class="primary-button" @click="$dispatch('quick-add-open', { type: 'formation' })" aria-haspopup="dialog">
                        Add a Formation entry
                    </button>
                @elseif ($unassignedTotal > 0)
                    <button type="button" class="primary-button" data-open-save-group-builder
                        aria-expanded="false" aria-controls="save-group-builder">
                        Build a report
                    </button>
                @else
                    <a href="{{ route('reports.index') }}" class="primary-button">
                        Review saved reports
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if ($hasSaveableEntries || $errors->any() || filled(old('title')) || filled(old('selected_entries')))
        @include('partials.save-group-builder')
    @endif

    {{-- Trend: monthly hours split by index, drawn as ruled marginalia --}}
    <section class="paper-panel rounded-panel px-5 py-6 sm:px-8" aria-labelledby="trend-heading">
        <figure class="trend-figure">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="trend-heading" class="font-serif text-2xl leading-tight text-stone-950">Hours served per month</h2>
                    <figcaption class="mt-2 max-w-2xl text-sm leading-6 text-stone-600">
                        @if ($series['months'] === [])
                            No dated entries yet. The ledger period starts with the first record you serve.
                        @else
                            Every month from {{ $series['periodLabel'] }} on one shared scale, split by index. The tallest column is
                            {{ $series['busiestMonth']['name'] }} {{ $series['busiestMonth']['year'] }} at {{ $series['busiestMonth']['total_label'] }}.
                        @endif
                    </figcaption>
                </div>

                <ul class="trend-legend" aria-label="Index colours">
                    @foreach (\App\Enums\IndexType::cases() as $legendType)
                        <li class="trend-legend__item">
                            <span class="trend-legend__swatch fill-{{ $legendType->value }}" aria-hidden="true"></span>
                            {{ $legendType->label() }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="marg-scroll clean-scroll" tabindex="0" role="group" aria-label="Monthly hours stacked by index; scroll horizontally if needed">
                <div class="marg-plot">
                    @forelse ($series['months'] as $month)
                        @php
                            $monthFill = $series['maxMinutes'] > 0 ? ($month['minutes'] / $series['maxMinutes']) * 100 : 0;
                            $showYear = $loop->first || $month['label'] === 'Jan';
                        @endphp
                        <div class="marg-col {{ $month['minutes'] === 0 ? 'marg-col--gap' : '' }}">
                            <div class="marg-total">{{ $month['minutes'] === 0 ? '—' : $month['total_label'] }}</div>
                            <div class="marg-stackwrap">
                                <div class="marg-stack" style="height: {{ round($monthFill, 2) }}%">
                                    @foreach (\App\Enums\IndexType::cases() as $columnType)
                                        @php
                                            $columnMinutes = $series['byType'][$columnType->value][$month['key']] ?? 0;
                                            $columnLabel = $series['labelsByType'][$columnType->value][$month['key']] ?? null;
                                        @endphp
                                        @if ($columnMinutes > 0)
                                            <a class="marg-seg fill-{{ $columnType->value }}"
                                                style="flex-grow: {{ $columnMinutes }}"
                                                href="{{ route('indexes.show', ['type' => $columnType->value, 'month' => $month['key']]) }}"
                                                aria-label="{{ $columnType->label() }}, {{ $month['name'] }} {{ $month['year'] }}: {{ $columnLabel }}. Open the {{ $columnType->label() }} ledger for this month.">
                                                <span class="marg-seg__tip" aria-hidden="true">{{ $columnType->label() }} · {{ $month['label'] }} {{ $month['year'] }} · {{ $columnLabel }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            <div class="marg-base {{ $month['minutes'] === 0 ? 'marg-base--gap' : '' }}">
                                <span class="marg-base__count">{{ $month['count'] }}</span>
                                <span class="marg-base__label">{{ $month['minutes'] === 0 ? 'no entries' : 'entries' }}</span>
                            </div>
                            <div class="marg-month">{{ $month['label'] }}<span class="marg-month__year">{{ $showYear ? $month['year'] : '' }}</span></div>
                        </div>
                    @empty
                        <p class="marg-empty">The first dated entry draws the first column.</p>
                    @endforelse
                </div>
            </div>

            @if ($series['months'] !== [])
                <p class="trend-coverage">
                    <span class="form-label">Coverage</span>
                    <span class="trend-coverage__figure">{{ count($series['months']) - count($series['gaps']) }} of {{ count($series['months']) }} months hold entries</span>
                    @if ($series['gaps'] !== [])
                        <span class="trend-coverage__gap">No entries in {{ collect($series['gaps'])->map(fn (array $gap): string => $gap['name'].' '.$gap['year'])->implode(', ') }}.</span>
                    @endif
                    @if ($series['undatedCount'] > 0)
                        <span class="trend-coverage__gap">{{ $series['undatedCount'] }} {{ $series['undatedCount'] === 1 ? 'entry' : 'entries' }} without a service date</span>
                    @endif
                </p>
            @endif

            {{-- Visually clipped, still in the accessibility tree, and never
                 widening the page: the table keeps its real layout. --}}
            <div class="w-px h-px overflow-hidden">
                <table id="trend-data">
                    <caption>Hours served per month, by index</caption>
                    <thead>
                        <tr>
                            <th scope="col">Month</th>
                            @foreach (\App\Enums\IndexType::cases() as $type)
                                <th scope="col">{{ $type->label() }} hours</th>
                            @endforeach
                            <th scope="col">Entries</th>
                            <th scope="col">Total hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($series['months'] as $month)
                            <tr>
                                <th scope="row">{{ $month['name'] }} {{ $month['year'] }}</th>
                                @foreach (\App\Enums\IndexType::cases() as $type)
                                    <td>{{ ($series['byType'][$type->value][$month['key']] ?? 0) === 0 ? 'none' : ($series['labelsByType'][$type->value][$month['key']] ?? '') }}</td>
                                @endforeach
                                <td>{{ $month['count'] }}</td>
                                <td>{{ $month['total_label'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </figure>
    </section>

    {{-- Assignment funnel and the academic-year lens --}}
    <div class="grid gap-4 lg:grid-cols-[1.35fr_0.9fr]">
        <section class="paper-panel rounded-panel p-5 sm:p-6" aria-labelledby="funnel-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="funnel-heading" class="font-serif text-2xl leading-tight text-stone-950">Assignment funnel</h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        {{ $lockedTotal }} of {{ $totalEntries }} entries are locked into saved reports; {{ $unassignedTotal }} are still ready to save.
                    </p>
                </div>
                <ul class="funnel-legend" aria-label="Funnel segments">
                    <li class="funnel-legend__item funnel-legend__item--locked">Locked in Reports</li>
                    <li class="funnel-legend__item funnel-legend__item--ready">Ready to save</li>
                </ul>
            </div>

            <ol class="funnel-list">
                @foreach (\App\Enums\IndexType::cases() as $funnelType)
                    @php
                        $funnelStats = $liveEntryStats[$funnelType->value];
                        $funnelTotal = max($funnelStats['total'], 1);
                        $lockedWidth = round(($funnelStats['locked'] / $funnelTotal) * 100);
                        $readyWidth = 100 - $lockedWidth;
                    @endphp
                    <li class="funnel-row">
                        <a class="funnel-row__name" href="{{ route('indexes.show', ['type' => $funnelType->value, 'scope' => 'unsaved']) }}">
                            {{ $funnelType->label() }}
                            <span class="funnel-row__hint">unsaved only</span>
                        </a>
                        <div class="funnel-bar" role="img" aria-label="{{ $funnelType->label() }}: {{ $funnelStats['locked'] }} locked in reports, {{ $funnelStats['available'] }} ready to save, {{ $funnelStats['total'] }} entries in total.">
                            <span class="funnel-bar__locked" style="width: {{ $lockedWidth }}%"></span>
                            <span class="funnel-bar__ready" style="width: {{ $readyWidth }}%"></span>
                        </div>
                        <div class="funnel-row__counts">
                            <span><strong class="funnel-row__num">{{ $funnelStats['locked'] }}</strong> locked</span>
                            <span><strong class="funnel-row__num">{{ $funnelStats['available'] }}</strong> ready</span>
                            <span><strong class="funnel-row__num">{{ $funnelStats['total'] }}</strong> total</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="paper-panel rounded-panel p-5 sm:p-6" aria-labelledby="ay-heading">
            <h2 id="ay-heading" class="font-serif text-2xl leading-tight text-stone-950">Academic year lens</h2>

            @if ($academicYearTotals === [])
                <p class="mt-4 text-sm leading-6 text-stone-600">
                    No entries yet. An academic year is only set on an entry when you type it in.
                </p>
            @else
                @php
                    $ayTotal = collect($academicYearTotals)->sum('count');
                    $aySet = collect($academicYearTotals)->sum(fn (array $row): int => $row['year'] === null ? 0 : $row['count']);
                @endphp

                <p class="ay-lede">{{ $aySet }} of {{ $ayTotal }} entries carry an academic year.</p>
                <p class="mt-1 text-sm leading-6 text-stone-600">
                    Totals by the academic year stored on each entry. Years are never inferred.
                </p>

                <ul class="ay-list">
                    @foreach ($academicYearTotals as $yearRow)
                        <li class="ay-row {{ $yearRow['year'] === null ? 'ay-row--unset' : '' }}">
                            @if ($yearRow['year'] !== null)
                                <a class="ay-row__main" href="{{ route('academic-year-snapshots.index') }}">
                                    <span class="ay-row__year">{{ $yearRow['label'] }}</span>
                                    <span class="ay-row__meta">{{ $yearRow['count'] }} entries</span>
                                    <span class="ay-row__hours">{{ $yearRow['hours_label'] }}</span>
                                </a>
                            @else
                                <div class="ay-row__main">
                                    <span class="ay-row__year">
                                        {{ $yearRow['label'] }}
                                        <span class="ay-row__stamp">not archived</span>
                                    </span>
                                    <span class="ay-row__meta">{{ $yearRow['count'] }} entries</span>
                                    <span class="ay-row__hours">{{ $yearRow['hours_label'] }}</span>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if (collect($academicYearTotals)->every(fn (array $row): bool => $row['year'] === null))
                    <p class="mt-4 text-sm leading-6 text-stone-600">
                        No entry carries an academic year yet. Entries stay unassigned to a year until you set one.
                        <a class="text-link" href="{{ route('academic-year-snapshots.index') }}">Open academic year snapshots</a>
                    </p>
                @endif
            @endif
        </section>
    </div>

    {{-- The three indexes as a ruled ledger table --}}
    <section class="paper-panel rounded-panel p-5 sm:p-6" aria-labelledby="indexes-heading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="indexes-heading" class="font-serif text-2xl leading-tight text-stone-950">The three indexes</h2>
                <p class="mt-2 text-sm leading-6 text-stone-600">Share of all-time hours, average per entry, and what is locked in reports.</p>
            </div>
            <a class="compact-pill compact-pill--soft" href="{{ route('academic-year-snapshots.index') }}">Academic year snapshots</a>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="index-table ledger-table text-left text-sm">
                <thead>
                    <tr>
                        <th scope="col">Index</th>
                        <th scope="col">Share of hours</th>
                        <th scope="col">Hours</th>
                        <th scope="col">Entries</th>
                        <th scope="col">Avg per entry</th>
                        <th scope="col">Locked in Reports</th>
                        <th scope="col" class="text-right">Ledger</th>
                    </tr>
                </thead>
                <tbody>
                    @php $allMinutes = collect($cards)->sum('total_minutes'); @endphp
                    @foreach (\App\Enums\IndexType::cases() as $indexType)
                        @php
                            $indexCard = $cards[$indexType->value];
                            $share = $allMinutes > 0 ? round(($indexCard['total_minutes'] / $allMinutes) * 100) : 0;
                            $avgMinutes = $indexCard['count'] > 0 ? intdiv($indexCard['total_minutes'], $indexCard['count']) : null;
                            $averageLabel = $avgMinutes === null
                                ? '—'
                                : ($avgMinutes < 60
                                    ? $avgMinutes . ' min'
                                    : sprintf('%d hr %02d min', intdiv($avgMinutes, 60), $avgMinutes % 60));
                        @endphp
                        <tr>
                            <th scope="row">
                                <a class="index-table__name" href="{{ route('indexes.show', ['type' => $indexType->value]) }}">{{ $indexCard['label'] }}</a>
                                <span class="index-table__file">{{ $meta[$indexType->value]['file_name'] }}</span>
                            </th>
                            <td>
                                <span class="share-rail"><span class="share-rail__fill fill-{{ $indexType->value }}" style="width: {{ $share }}%"></span></span>
                                <span class="share-rail__value tabular-nums">{{ $share }}%</span>
                            </td>
                            <td class="tabular-nums text-stone-950">{{ $indexCard['total_label'] }}</td>
                            <td class="tabular-nums">{{ $indexCard['count'] }}</td>
                            <td class="tabular-nums">{{ $averageLabel }}</td>
                            <td class="tabular-nums">{{ $liveEntryStats[$indexType->value]['locked'] }}</td>
                            <td class="text-right"><a class="text-link" href="{{ route('indexes.show', ['type' => $indexType->value]) }}">Open ledger</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Most recent entries across every index --}}
    <section class="paper-panel rounded-panel p-5 sm:p-6" aria-labelledby="recent-heading">
        <h2 id="recent-heading" class="font-serif text-2xl leading-tight text-stone-950">Most recent entries</h2>
        <ul class="recent-list">
            @forelse ($recentEntries as $recent)
                @php
                    $recentEntry = $recent['entry'];
                    $recentType = $recent['type'];
                    $recentAssignment = $recent['assignment'];
                @endphp
                <li class="recent-row">
                    <span class="recent-row__date tabular-nums">{{ $recentEntry->served_on_label ?? 'No date' }}</span>
                    <a class="recent-row__title" href="{{ route('indexes.show', ['type' => $recentType->value]) }}">
                        @if ($recentType === \App\Enums\IndexType::Formation)
                            {{ trim(($recentEntry->cycle_code ?? '').' / '.($recentEntry->module_code ?? ''), ' /') }} — {{ $recentEntry->title }}
                        @elseif ($recentType === \App\Enums\IndexType::SocialApostolate)
                            {{ $recentEntry->about }}
                        @else
                            {{ filled($recentEntry->role_in_activity) ? $recentEntry->role_in_activity : 'Parish Involvement' }}
                        @endif
                        @if (! ($recentType === \App\Enums\IndexType::ParishInvolvement && blank($recentEntry->role_in_activity)))
                            <span class="recent-row__type">{{ $recentType->label() }}</span>
                        @endif
                    </a>
                    <span class="recent-row__duration tabular-nums">{{ $recentEntry->duration_label }}</span>
                    @if ($recentAssignment)
                        <x-assignment-chip
                            variant="saved"
                            class="assignment-chip"
                            :label="$recentAssignment->compact_label"
                            :title="'Saved in ' . $recentAssignment->display_label"
                            :href="route('reports.show', $recentAssignment)"
                        />
                    @endif
                </li>
            @empty
                <li class="recent-row recent-row--empty">No entries have been synced yet.</li>
            @endforelse
        </ul>
    </section>
@endsection
