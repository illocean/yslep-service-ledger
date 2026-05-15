@extends('layouts.app')

@section('title', $academicYearSnapshot->display_label)

@section('content')
    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-[2rem]">
        <div class="grid gap-8 px-5 py-6 sm:px-8 lg:grid-cols-[1.45fr_0.85fr] lg:items-start lg:px-10 lg:py-8">
            <div class="space-y-5">
                <div class="section-kicker">Academic Year Snapshot</div>
                <div class="space-y-3">
                    <h1 class="font-serif text-4xl leading-tight text-stone-900 sm:text-5xl">
                        {{ $academicYearSnapshot->compact_label }}
                    </h1>
                    <p class="max-w-3xl text-sm leading-7 text-stone-700 sm:text-base">
                        This snapshot is a manual archive built from selected saved reports, one level above the normal saved-reports flow.
                    </p>
                </div>
            </div>

            <div class="paper-panel rounded-[1.5rem] p-5">
                <div class="section-kicker">Snapshot Totals</div>
                <div class="mt-4 grid gap-3">
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Created</div>
                        <div class="mt-2 text-sm font-semibold text-stone-900">{{ $academicYearSnapshot->created_at?->setTimezone(config('app.timezone'))->format('F j, Y g:i A') }}</div>
                    </div>
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Records</div>
                        <div class="mt-2 text-sm font-semibold text-stone-900">{{ $academicYearSnapshot->items->count() }}</div>
                    </div>
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Grand total</div>
                        <div class="mt-2 text-lg font-bold text-stone-900">{{ $grandTotalLabel }}</div>
                    </div>
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Obsidian archive file</div>
                        <div class="mt-2 break-all font-mono text-xs text-stone-700">{{ $snapshotFilePath }}</div>
                    </div>
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
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Count</div>
                        <div class="mt-2 font-serif text-4xl text-stone-950">{{ str_pad((string) $card['count'], 2, '0', STR_PAD_LEFT) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Hours</div>
                        <div class="mt-2 text-lg font-bold text-stone-900">{{ $card['total_label'] }}</div>
                    </div>
                </div>
            </article>
        @endforeach
    </section>

    <section class="paper-panel rounded-[2rem] p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="section-kicker">Included Saved Reports</div>
                <h2 class="mt-3 font-serif text-2xl text-stone-950">Source reports collected into this archive</h2>
            </div>

            <form method="POST" action="{{ route('academic-year-snapshots.destroy', $academicYearSnapshot) }}">
                @csrf
                @method('DELETE')

                <button type="submit" class="danger-button">
                    Delete Snapshot
                </button>
            </form>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            @foreach ($academicYearSnapshot->items->map(fn ($item) => ['label' => $item->source_report_display_label, 'tag' => $item->source_report_tag, 'reportGroup' => $item->reportGroup])->unique(fn ($reportMeta) => $reportMeta['tag'] ?: 'label:' . $reportMeta['label'])->values() as $reportMeta)
                @if ($reportMeta['reportGroup'])
                    <a href="{{ route('reports.show', $reportMeta['reportGroup']) }}" class="assignment-chip assignment-chip--saved">
                        <span class="assignment-chip__dot" aria-hidden="true"></span>
                        <span>{{ $reportMeta['label'] }}</span>
                    </a>
                @else
                    <span class="assignment-chip assignment-chip--saved">
                        <span class="assignment-chip__dot" aria-hidden="true"></span>
                        <span>{{ $reportMeta['label'] }}</span>
                    </span>
                @endif
            @endforeach
        </div>
    </section>

    <section class="grid gap-6">
        @foreach (\App\Enums\IndexType::cases() as $type)
            @php
                $items = $academicYearSnapshot->itemsFor($type->value);
            @endphp

            @if ($items->isEmpty())
                @continue
            @endif

            <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="section-kicker">{{ $type->cardTitle() }}</div>
                        <h2 class="mt-3 font-serif text-2xl text-stone-950">{{ $type->label() }}</h2>
                    </div>

                    <div class="rounded-full border border-stone-900/10 bg-white/70 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                        {{ $items->count() }} record(s)
                    </div>
                </div>

                <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-stone-900/10 bg-white/80">
                    <div class="clean-scroll overflow-x-auto">
                        <table class="ledger-table min-w-full text-left text-sm">
                            <thead class="bg-stone-950/[0.03] text-xs uppercase tracking-[0.18em] text-stone-600">
                                <tr>
                                    @if ($type === \App\Enums\IndexType::Formation)
                                        <th class="px-3 py-3">Date</th>
                                        <th class="px-3 py-3">Cycle No.</th>
                                        <th class="px-3 py-3">Module No.</th>
                                        <th class="px-3 py-3">Title</th>
                                        <th class="px-3 py-3">Time In</th>
                                        <th class="px-3 py-3">Time Out</th>
                                        <th class="px-3 py-3">Duration</th>
                                        <th class="px-3 py-3">Saved Report</th>
                                    @elseif ($type === \App\Enums\IndexType::SocialApostolate)
                                        <th class="px-3 py-3">Date</th>
                                        <th class="px-3 py-3">Activity</th>
                                        <th class="px-3 py-3">Time In</th>
                                        <th class="px-3 py-3">Time Out</th>
                                        <th class="px-3 py-3">Duration</th>
                                        <th class="px-3 py-3">Saved Report</th>
                                    @else
                                        <th class="px-3 py-3">Date</th>
                                        <th class="px-3 py-3">Activity</th>
                                        <th class="px-3 py-3">Time In</th>
                                        <th class="px-3 py-3">Time Out</th>
                                        <th class="px-3 py-3">Duration</th>
                                        <th class="px-3 py-3">Saved Report</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        @if ($type === \App\Enums\IndexType::Formation)
                                            <td class="px-3 py-3">{{ $item->served_on_label }}</td>
                                            <td class="px-3 py-3">{{ $item->cycle_code }}</td>
                                            <td class="px-3 py-3">{{ $item->module_code }}</td>
                                            <td class="px-3 py-3">{{ $item->title }}</td>
                                            <td class="px-3 py-3">{{ $item->time_start_label }}</td>
                                            <td class="px-3 py-3">{{ $item->time_end_label }}</td>
                                            <td class="px-3 py-3">{{ $item->duration_label }}</td>
                                            <td class="px-3 py-3">{{ $item->source_report_display_label }}</td>
                                        @elseif ($type === \App\Enums\IndexType::SocialApostolate)
                                            <td class="px-3 py-3">{{ $item->served_on_label }}</td>
                                            <td class="px-3 py-3">{{ $item->about }}</td>
                                            <td class="px-3 py-3">{{ $item->time_start_label }}</td>
                                            <td class="px-3 py-3">{{ $item->time_end_label }}</td>
                                            <td class="px-3 py-3">{{ $item->duration_label }}</td>
                                            <td class="px-3 py-3">{{ $item->source_report_display_label }}</td>
                                        @else
                                            <td class="px-3 py-3">{{ $item->served_on_label }}</td>
                                            <td class="px-3 py-3">Parish Involvement</td>
                                            <td class="px-3 py-3">{{ $item->time_start_label }}</td>
                                            <td class="px-3 py-3">{{ $item->time_end_label }}</td>
                                            <td class="px-3 py-3">{{ $item->duration_label }}</td>
                                            <td class="px-3 py-3">{{ $item->source_report_display_label }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        @endforeach
    </section>
@endsection
