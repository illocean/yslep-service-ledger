@extends('layouts.app')

@section('title', 'YSLEP Overview')

@section('content')
    @php
        $totalEntries = collect($cards)->sum('count');
        $unassignedTotal = collect($liveEntryStats)->sum(fn (array $stats): int => $stats['available']);
        $unassignedLabels = collect(\App\Enums\IndexType::cases())
            ->filter(fn (\App\Enums\IndexType $type): bool => $liveEntryStats[$type->value]['available'] > 0)
            ->map(fn (\App\Enums\IndexType $type): string => $type->label())
            ->all();
        $fallbackType = collect(\App\Enums\IndexType::cases())
            ->first(fn (\App\Enums\IndexType $type): bool => $cards[$type->value]['count'] > 0)
            ?? \App\Enums\IndexType::Formation;
    @endphp

    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-panel">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.45fr_0.85fr] lg:items-center lg:px-10 lg:py-8">
            <div class="space-y-4">
                <div class="section-kicker">Dashboard</div>
                <h1 class="max-w-2xl font-serif text-4xl leading-tight text-stone-900 sm:text-5xl">
                    All-time service ledger from your three Obsidian inputs.
                </h1>

                <dl class="flex flex-wrap items-baseline gap-x-8 gap-y-3 pt-1">
                    <div class="flex items-baseline gap-2">
                        <dt class="form-label">Entries</dt>
                        <dd class="font-semibold tabular-nums text-stone-900">{{ $totalEntries }}</dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt class="form-label">Saved Reports</dt>
                        <dd class="font-semibold tabular-nums text-stone-900">{{ $reportGroups->count() }}</dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt class="form-label">Unassigned</dt>
                        <dd class="font-semibold tabular-nums {{ $unassignedTotal > 0 ? 'text-[color:var(--ledger-accent-ink)]' : 'text-stone-900' }}">{{ $unassignedTotal }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-card border-[color:var(--ledger-accent)] bg-white/70 p-5 sm:p-6">
                <div class="section-kicker">Time Served</div>
                <div class="mt-3 font-serif text-5xl leading-none tabular-nums text-stone-950 sm:text-6xl">
                    {{ $grandTotalLabel }}
                </div>
                <div class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-stone-600">
                    Combined hours across all three indexes
                </div>

                @if ($reportGroups->isNotEmpty())
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-stone-900/10 pt-4">
                        <div class="min-w-0">
                            <div class="form-label">Latest Report</div>
                            <p class="mt-1 truncate text-sm font-semibold text-stone-900">{{ $reportGroups->first()->display_label }}</p>
                        </div>
                        <a href="{{ route('reports.index') }}" class="primary-button">View All Reports</a>
                    </div>
                @else
                    <a href="{{ route('reports.index') }}" class="primary-button mt-5 w-full">View All Reports</a>
                @endif
            </div>
        </div>
    </section>

    <section class="paper-panel rounded-panel px-5 py-5 sm:px-8 sm:py-6" aria-labelledby="attention-heading">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <span class="mt-1 h-10 w-1 shrink-0 rounded-full {{ $unassignedTotal > 0 ? 'bg-[color:var(--ledger-accent)]' : 'bg-stone-900/10' }}" aria-hidden="true"></span>
                <div>
                    <h2 id="attention-heading" class="font-serif text-xl text-stone-950">
                        @if ($totalEntries === 0)
                            No entries yet
                        @elseif ($unassignedTotal > 0)
                            {{ $unassignedTotal }} {{ $unassignedTotal === 1 ? 'entry' : 'entries' }} ready to save
                        @else
                            Every entry is saved
                        @endif
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-stone-600">
                        @if ($totalEntries === 0)
                            Add your first service entry to start the ledger.
                        @elseif ($unassignedTotal > 0)
                            Unassigned entries in {{ implode(', ', $unassignedLabels) }} — group them into a saved report below.
                        @else
                            Nothing is waiting: every live entry already belongs to a saved report.
                        @endif
                    </p>
                </div>
            </div>

            @if ($totalEntries === 0)
                <button type="button" class="primary-button shrink-0" @click="$dispatch('quick-add-open', { type: '{{ $fallbackType->value }}' })" aria-haspopup="dialog">
                    Add your first entry
                </button>
            @elseif ($unassignedTotal > 0)
                <button type="button" class="primary-button shrink-0" data-open-save-group-builder
                    aria-expanded="false" aria-controls="save-group-builder">
                    Save a Report
                </button>
            @else
                <a href="{{ route('indexes.show', ['type' => $fallbackType->value]) }}" class="primary-button shrink-0">
                    Open the {{ $fallbackType->label() }} Ledger
                </a>
            @endif
        </div>
    </section>

    @include('partials.save-group-builder')

    <section class="grid gap-4 lg:grid-cols-3">
        @foreach (\App\Enums\IndexType::cases() as $type)
            @php
                $card = $cards[$type->value];
                $previewEntries = $entries[$type->value];
                $profile = $meta[$type->value]['profile'];
                $manageRoute = route('indexes.show', ['type' => $type->value]);
            @endphp

            <article class="paper-panel flex flex-col rounded-panel p-5 sm:p-6">
                <header class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="section-kicker">{{ $card['card_title'] }}</div>
                        <h2 class="mt-2 font-serif text-2xl text-stone-950">{{ $card['label'] }}</h2>
                    </div>
                    <span class="shrink-0 rounded-full border border-stone-900/10 bg-white/70 px-3 py-1.5 text-xs font-semibold tabular-nums uppercase tracking-[0.16em] text-stone-600">
                        {{ $card['count'] }} record(s)
                    </span>
                </header>

                <div class="mt-5 flex items-end justify-between gap-4 rounded-cell border border-stone-900/10 bg-white/70 px-4 py-3">
                    <div>
                        <div class="form-label">Hours Served</div>
                        <div class="mt-1 font-serif text-3xl leading-none tabular-nums text-stone-950">{{ $card['total_label'] }}</div>
                    </div>
                    <dl class="text-right text-sm">
                        <dt class="form-label">Entries</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-stone-900">{{ $card['count'] }}</dd>
                        <dt class="form-label mt-3">Locked in Reports</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-stone-900">{{ $liveEntryStats[$type->value]['locked'] }}</dd>
                    </dl>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-stone-600">
                    <span class="rounded-full border border-stone-900/10 bg-stone-50/80 px-3 py-1 font-semibold">
                        {{ $profile['school_year'] ?: 'School year not set' }}
                    </span>
                    <span class="font-mono text-[0.68rem] uppercase tracking-[0.14em] text-stone-500">{{ $meta[$type->value]['file_name'] }}</span>
                </div>

                <div class="mt-5 flex-1 space-y-2">
                    <h3 class="form-label">Recent Entries</h3>

                <ul class="mt-2 flex-1 space-y-2">
                    @forelse ($previewEntries as $entry)
                        @php
                            $assignment = $assignedReportLookup[$type->value . ':' . $entry->id] ?? null;
                        @endphp

                        <li class="rounded-cell border border-stone-900/8 bg-stone-50/80 px-4 py-2.5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-stone-900">
                                        @if ($type === \App\Enums\IndexType::Formation)
                                            {{ $entry->cycle_code }} / {{ $entry->module_code }} — {{ $entry->title }}
                                        @elseif ($type === \App\Enums\IndexType::SocialApostolate)
                                            {{ $entry->about }}
                                        @else
                                            Parish Involvement
                                        @endif
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-600">
                                        <span class="tabular-nums">{{ $entry->served_on_label }}</span>
                                        <span class="tabular-nums">{{ $entry->time_start_label }}&ndash;{{ $entry->time_end_label }}</span>
                                        @if ($assignment)
                                            <x-assignment-chip
                                                variant="saved"
                                                :label="$assignment->compact_label"
                                                :title="'Saved in ' . $assignment->display_label"
                                            />
                                            <span class="sr-only">saved in {{ $assignment->display_label }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-sm font-semibold tabular-nums text-stone-900">{{ $entry->duration_label }}</div>
                            </div>
                        </li>
                    @empty
                        <li class="rounded-cell border border-dashed border-stone-900/12 bg-stone-50/50 px-4 py-6 text-sm text-stone-600">
                            No records yet. Add entries in Obsidian to see them here.
                        </li>
                    @endforelse
                </ul>

                <div class="mt-5 flex items-center justify-between gap-3 border-t border-stone-900/10 pt-4">
                    <span class="text-xs text-stone-600">Full all-time ledger and entry form</span>
                    <a href="{{ $manageRoute }}" class="primary-button">Open {{ $card['label'] }} Page</a>
                </div>
            </article>
        @endforeach
    </section>
@endsection
