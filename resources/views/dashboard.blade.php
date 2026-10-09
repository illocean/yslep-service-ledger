@extends('layouts.app')

@section('title', 'YSLEP Overview')

@section('content')
    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-panel">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.45fr_0.85fr] lg:items-start lg:px-10 lg:py-8">
            <div class="space-y-3">
                <div class="section-kicker">Dashboard</div>
                <h1 class="font-serif text-4xl leading-tight text-stone-900 sm:text-5xl">
                    All-time service ledger from your three Obsidian inputs.
                </h1>
            </div>

            <div class="paper-panel rounded-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="section-kicker">Saved Reports</div>
                    <span class="font-serif text-2xl text-stone-950">{{ str_pad((string) $reportGroups->count(), 2, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('reports.index') }}" class="primary-button w-full">
                        View All Reports
                    </a>

                    @if ($reportGroups->isNotEmpty())
                        <div class="rounded-cell border border-stone-900/10 bg-white/70 p-4">
                            <div class="form-label">Latest Report</div>
                            <p class="mt-1 text-sm font-semibold text-stone-900">{{ $reportGroups->first()->display_label }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-4">
        @foreach ($cards as $card)
            <article class="stat-panel rounded-stat p-5">
                <div class="section-kicker">{{ $card['label'] }}</div>
                <div class="mt-5 flex items-end justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">Total Entries</div>
                        <div class="mt-2 font-serif text-4xl text-stone-950">{{ str_pad((string) $card['count'], 2, '0', STR_PAD_LEFT) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">Total Hours</div>
                        <div class="mt-2 text-lg font-bold text-stone-900">{{ $card['total_label'] }}</div>
                    </div>
                </div>
            </article>
        @endforeach

        <article class="stat-panel rounded-stat border-[color:var(--ledger-accent)] p-5">
            <div class="section-kicker">Grand Total</div>
            <div class="mt-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">Combined hours across all three indexes</div>
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

            <article class="paper-panel flex flex-col rounded-panel p-5 sm:p-6">
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
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">School Year</div>
                        <div class="mt-2 text-sm font-semibold text-stone-900">{{ $profile['school_year'] ?: 'Not set' }}</div>
                    </div>
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-4">
                        <div class="form-label">Locked in Reports</div>
                        <div class="mt-2 text-sm font-semibold text-stone-900">{{ $liveEntryStats[$type->value]['locked'] }}</div>
                    </div>
                </div>

                <div class="mt-5 flex-1 rounded-card border border-stone-900/10 bg-white/75 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="form-label">All Time Preview</div>
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-600">{{ $card['total_label'] }}</div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($previewEntries as $entry)
                            @php
                                $assignment = $assignedReportLookup[$type->value . ':' . $entry->id] ?? null;
                            @endphp

                            <div class="rounded-cell border border-stone-900/8 bg-stone-50/80 px-4 py-3">
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
                                            <div class="mt-2">
                                                <x-assignment-chip 
                                                    variant="saved" 
                                                    :label="$assignment->compact_label"
                                                    :title="'Saved in ' . $assignment->display_label"
                                                />
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
                            <div class="rounded-cell border border-dashed border-stone-900/12 bg-stone-50/50 px-4 py-5 text-sm text-stone-600">
                                No records yet. Add entries in Obsidian to see them here.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-5 flex items-start justify-between gap-3 pt-1 sm:items-center">
                    <div class="text-sm text-stone-600">
                        Open the dedicated {{ strtolower($card['label']) }} page for the full all-time ledger and live input form.
                    </div>

                    <a href="{{ $manageRoute }}" class="primary-button">
                        Open {{ $card['label'] }} Page
                    </a>
                </div>
            </article>
        @endforeach
    </section>

    @include('partials.save-group-builder')
@endsection
