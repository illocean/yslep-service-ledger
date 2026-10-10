@extends('layouts.app')

@section('title', $reportGroup->display_label)

@section('content')
    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-panel">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.45fr_0.85fr] lg:px-10 lg:py-8">
            <div class="space-y-3">
                <div class="section-kicker">Saved Report</div>
                <h1 class="font-serif text-3xl leading-tight text-stone-900 sm:text-4xl">
                    {{ $reportGroup->title ?: 'Untitled Report' }}
                </h1>
                <div class="font-mono text-xs text-stone-600">Tag: {{ $reportGroup->tag }}</div>

                @if ($archivedInSnapshot)
                    <div class="assignment-chip assignment-chip--complete mt-3" title="This report is already part of an academic-year snapshot">
                        <span class="assignment-chip__dot" aria-hidden="true"></span>
                        <span>Archived in {{ $archivedInSnapshot->compact_label }}</span>
                    </div>
                @endif
            </div>

            <div class="paper-panel rounded-card p-4">
                <div class="section-kicker">Totals</div>
                <div class="mt-3 grid gap-3">
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Created</div>
                        <div class="mt-1 text-sm font-semibold text-stone-900">{{ $reportGroup->created_at?->setTimezone(config('app.timezone'))->format('F j, Y g:i A') }}</div>
                    </div>
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Records</div>
                        <div class="mt-1 text-sm font-semibold text-stone-900">{{ $reportGroup->items->count() }}</div>
                    </div>
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Hours</div>
                        <div class="mt-1 font-serif text-2xl tabular-nums text-stone-950">{{ $grandTotalLabel }}</div>
                    </div>
                </div>

                @if (! $archivedInSnapshot)
                    <a href="{{ route('academic-year-snapshots.index', ['prefill' => $reportGroup->id]) }}" class="primary-button mt-4 w-full justify-center">
                        Archive this report
                    </a>
                    <p class="mt-2 text-[0.72rem] uppercase tracking-[0.16em] text-stone-600">
                        Jump to the snapshot builder with this report pre-selected.
                    </p>
                @else
                    <a href="{{ route('academic-year-snapshots.show', $archivedInSnapshot) }}" class="secondary-button mt-4 w-full justify-center">
                        Open snapshot
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
        <article class="paper-panel rounded-panel p-5 sm:p-6">
            <div class="section-kicker">Rename</div>
            <h2 class="mt-1 font-serif text-xl text-stone-950">Report title</h2>

            <form method="POST" action="{{ route('reports.update', $reportGroup) }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="title" class="sr-only">Report title</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $reportGroup->title) }}" placeholder="Leave blank to show only the tag" class="form-input">
                </div>

                <button type="submit" class="primary-button">Save Changes</button>
            </form>
        </article>

        <article class="paper-panel rounded-panel p-5 sm:p-6">
            <div class="space-y-4">
                <div>
                    <div class="section-kicker">Sync</div>
                    <h2 class="mt-1 font-serif text-xl text-stone-950">Pull edits from Obsidian</h2>

                    <form method="POST" action="{{ route('reports.sync-from-obsidian') }}" class="mt-4 sync-form">
                        @csrf
                        <button type="submit" class="secondary-button">Sync from Obsidian</button>
                    </form>
                </div>

                <div class="rounded-card border border-red-900/10 bg-red-50/70 p-4">
                    <div class="section-kicker text-red-700">Danger Zone</div>
                    <p class="mt-2 text-sm text-stone-700">
                        This removes record notes and frees entries for reuse.
                    </p>

                    <form method="POST" action="{{ route('reports.destroy', $reportGroup) }}" class="mt-3" data-confirm="Delete this report and its record notes?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="danger-button">Delete Report</button>
                    </form>
                </div>
            </div>
        </article>
    </section>

    <section class="grid gap-6">
        @foreach (\App\Enums\IndexType::cases() as $type)
            @php
                $items = $reportGroup->itemsFor($type->value);
                $sectionPrefix = 'report-' . $type->value;
                $typeMinutes = $items->sum('duration_minutes');
                $typeHours = intdiv($typeMinutes, 60);
                $typeRemainingMinutes = $typeMinutes % 60;
                $typeDurationLabel = $typeRemainingMinutes === 0
                    ? $typeHours . ' hr'
                    : sprintf('%d hr %02d min', $typeHours, $typeRemainingMinutes);
            @endphp

            <article class="paper-panel rounded-panel p-5 sm:p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="section-kicker">{{ $type->cardTitle() }}</div>
                        <h2 class="mt-1 font-serif text-xl text-stone-950">{{ $type->label() }}</h2>
                    </div>
                    <dl class="flex items-center gap-4 text-right">
                        <div>
                            <dt class="form-label">Records</dt>
                            <dd class="mt-1 font-serif text-2xl tabular-nums text-stone-950">{{ $items->count() }}</dd>
                        </div>
                        <div>
                            <dt class="form-label">Hours</dt>
                            <dd class="mt-1 text-sm font-semibold tabular-nums text-stone-900">{{ $typeDurationLabel }}</dd>
                        </div>
                    </dl>
                </div>

                <button type="button" class="primary-button mt-4" @click="$dispatch('quick-add-open', { type: '{{ $type->value }}' })" aria-haspopup="dialog">+ Add {{ $type->label() }}</button>

                <div class="mt-6 space-y-4">
                    @forelse ($items as $item)
                        <article class="report-record-card">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="assignment-chip assignment-chip--saved">
                                            <span class="assignment-chip__dot" aria-hidden="true"></span>
                                            <span>Saved record · {{ $type->label() }}</span>
                                        </span>
                                        <span class="compact-pill">{{ $item->duration_label }}</span>

                                        @if ($item->obsidian_conflict)
                                            <span class="assignment-chip assignment-chip--conflict" title="Conflict detected: both Obsidian and database have changes since last sync">
                                                <span class="assignment-chip__dot" aria-hidden="true"></span>
                                                <span>Conflict</span>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="text-lg font-semibold text-stone-900">
                                        @if ($type === \App\Enums\IndexType::Formation)
                                            {{ $item->cycle_code }} / {{ $item->module_code }} - {{ $item->title }}
                                        @elseif ($type === \App\Enums\IndexType::SocialApostolate)
                                            {{ $item->about }}
                                        @else
                                            Parish Involvement
                                        @endif
                                    </div>

                                    <div class="text-sm leading-7 text-stone-600">
                                        {{ $item->served_on_label }} | {{ $item->time_start_label }} - {{ $item->time_end_label }}
                                        @if ($item->academic_year)<span class="ml-2">AY {{ $item->academic_year }}</span>@endif
                                        @if ($item->role_in_activity)<div>{{ $item->role_in_activity }}</div>@endif
                                    </div>


                                </div>

                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button type="button" class="secondary-button" @click="$dispatch('open-entry-modal', { type: '{{ $type->value }}', entryId: {{ $item->id }} })" aria-haspopup="dialog">Edit entry</button>
                            <x-entry-modal :type="$type" :entry="$item" :report="$reportGroup"
                                :form-action="route('reports.records.update', [$reportGroup, $item])" form-method="PATCH" />

                            @if ($item->obsidian_conflict)
                                <div class="mt-3 p-3 rounded-cell border border-stone-900/10 bg-amber-50/70">
                                    <p class="text-sm text-stone-700">This record has a conflict: both Obsidian and the database have changes since the last sync.</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('reports.records.conflict.accept-vault', [$reportGroup, $item]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="secondary-button !text-xs !py-1.5 !px-3" title="Accept the Obsidian version and overwrite database changes">
                                                Accept Vault Version
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('reports.records.conflict.accept-db', [$reportGroup, $item]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="secondary-button !text-xs !py-1.5 !px-3" title="Keep the database version and write it to Obsidian">
                                                Accept Database Version
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('reports.records.destroy', [$reportGroup, $item]) }}" class="ml-auto" data-confirm="Delete this record?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger-button">
                                    Delete Record
                                </button>
                            </form>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-card border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm leading-7 text-stone-600">
                            No {{ strtolower($type->label()) }} records attached yet.
                        </div>
                    @endforelse
                </div>
            </article>
        @endforeach
    </section>
@endsection
