@extends('layouts.app')

@section('title', $reportGroup->display_label)

@section('content')
    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-panel">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.5fr_0.85fr] lg:items-start lg:px-10 lg:py-8">
            <div class="space-y-3">
                <a class="text-link" href="{{ route('reports.index') }}">&larr; All saved reports</a>
                <div class="section-kicker">Saved report</div>
                <h1 class="font-serif text-3xl leading-tight text-stone-900 sm:text-4xl">
                    {{ $reportGroup->title ?: 'Untitled report' }}
                </h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-600">
                    <span class="font-mono">Tag: {{ $reportGroup->tag }}</span>
                    <span aria-hidden="true">&middot;</span>
                    <span>Created {{ $reportGroup->created_at?->setTimezone(config('app.timezone'))->format('F j, Y') }}</span>
                </div>

                <p class="max-w-2xl rounded-cell border border-stone-900/10 bg-white/60 px-4 py-3 text-sm leading-6 text-stone-700">
                    Editing a record here changes this saved report only. The live ledger entry stays unchanged.
                </p>

                @if ($archivedInSnapshot)
                    <div class="assignment-chip assignment-chip--complete" title="This report is already part of an academic-year snapshot">
                        <span class="assignment-chip__dot" aria-hidden="true"></span>
                        <span>Archived in {{ $archivedInSnapshot->compact_label }}</span>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    @if (! $archivedInSnapshot)
                        <a href="{{ route('academic-year-snapshots.index', ['prefill' => $reportGroup->id]) }}" class="secondary-button compact-inline-button">
                            Archive this report
                        </a>
                        <span class="text-xs leading-5 text-stone-600">Opens the snapshot builder with this report selected.</span>
                    @else
                        <a href="{{ route('academic-year-snapshots.show', $archivedInSnapshot) }}" class="secondary-button compact-inline-button">
                            Open snapshot
                        </a>
                    @endif
                </div>
            </div>

            <div class="paper-panel rounded-card p-4">
                <div class="section-kicker">Totals</div>
                <dl class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <dt class="form-label">Records</dt>
                        <dd class="mt-1 font-serif text-2xl tabular-nums text-stone-950">{{ $reportGroup->items->count() }}</dd>
                    </div>
                    <div class="rounded-cell border border-stone-900/10 bg-white/70 p-3">
                        <dt class="form-label">Hours</dt>
                        <dd class="mt-1 font-serif text-2xl tabular-nums text-stone-950">{{ $grandTotalLabel }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <details class="group paper-panel rounded-panel" @if ($errors->has('title') || filled(old('title'))) open @endif>
        <summary class="flex cursor-pointer list-none items-start justify-between gap-4 px-5 py-4 sm:px-8 [&::-webkit-details-marker]:hidden">
            <span>
                <span class="section-kicker block">Report settings</span>
                <span class="mt-1 block font-serif text-lg text-stone-950">Rename, sync, or delete this report</span>
            </span>
            <span class="compact-pill">
                <span class="group-open:hidden">Open</span>
                <span class="hidden group-open:inline">Close</span>
            </span>
        </summary>

        <div class="border-t border-stone-900/10 px-5 py-5 sm:px-8">
            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <h2 class="font-serif text-xl text-stone-950">Rename this report</h2>
                    <p class="mt-1 text-sm leading-6 text-stone-600">Use a title that will still make sense in an academic-year snapshot.</p>

                    <form method="POST" action="{{ route('reports.update', $reportGroup) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="title" class="form-label">Report title</label>
                            <input id="title" name="title" type="text" value="{{ old('title', $reportGroup->title) }}" placeholder="Leave blank to show only the tag" class="form-input mt-1">
                            @error('title')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="secondary-button compact-inline-button">Save title</button>
                    </form>
                </div>

                <div class="space-y-5">
                    <div>
                        <h2 class="font-serif text-xl text-stone-950">Pull edits from Obsidian</h2>
                        <p class="mt-1 text-sm leading-6 text-stone-600">Syncs the saved report notes without touching live ledger entries.</p>

                        <form method="POST" action="{{ route('reports.sync-from-obsidian') }}" class="sync-form mt-3">
                            @csrf
                            <button type="submit" class="secondary-button compact-inline-button">Sync from Obsidian</button>
                        </form>
                    </div>

                    <div class="rounded-card border border-red-900/10 bg-red-50/70 p-4">
                        <h2 class="font-serif text-xl text-red-800">Delete this report</h2>
                        <p class="mt-1 text-sm leading-6 text-stone-700">Removes its record notes and makes its live entries available to save again.</p>

                        <form method="POST" action="{{ route('reports.destroy', $reportGroup) }}" class="mt-3" data-confirm="Delete this report and its record notes? Its live entries will become available to save again.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger-button">Delete report</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </details>

    <section class="grid gap-6">
        @foreach (\App\Enums\IndexType::cases() as $type)
            @php
                $items = $reportGroup->itemsFor($type->value);
                $typeMinutes = $items->sum('duration_minutes');
                $typeHours = intdiv($typeMinutes, 60);
                $typeRemainingMinutes = $typeMinutes % 60;
                $typeDurationLabel = $typeRemainingMinutes === 0
                    ? $typeHours . ' hr'
                    : sprintf('%d hr %02d min', $typeHours, $typeRemainingMinutes);
                $headingId = 'report-records-' . $type->value;
            @endphp

            <article class="paper-panel rounded-panel p-5 sm:p-6" aria-labelledby="{{ $headingId }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="section-kicker">{{ $type->cardTitle() }}</div>
                        <h2 id="{{ $headingId }}" class="mt-1 font-serif text-xl text-stone-950">{{ $type->label() }}</h2>
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

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" class="primary-button" @click="$dispatch('quick-add-open', { type: '{{ $type->value }}' })" aria-haspopup="dialog">+ Add {{ $type->label() }} record</button>
                    <a class="text-link" href="{{ route('indexes.show', ['type' => $type->value]) }}">Open live {{ $type->label() }} ledger</a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($items as $item)
                        @php
                            $headline = match ($type) {
                                \App\Enums\IndexType::Formation => trim(($item->cycle_code ?? '') . ' / ' . ($item->module_code ?? ''), ' /') . ' - ' . $item->title,
                                \App\Enums\IndexType::SocialApostolate => $item->about,
                                default => $item->role_in_activity ?: 'Parish involvement',
                            };
                        @endphp

                        <article class="report-record-card">
                            <div class="grid gap-3 sm:grid-cols-[8rem_minmax(0,1fr)] xl:grid-cols-[8rem_minmax(0,1fr)_auto] xl:items-start">
                                <div>
                                    <div class="form-label">Date</div>
                                    <div class="mt-1 text-sm font-semibold text-stone-900">{{ $item->served_on_label }}</div>
                                    <div class="text-xs tabular-nums text-stone-600">{{ $item->time_start_label }}&ndash;{{ $item->time_end_label }}</div>
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold text-stone-900 sm:text-lg">{{ $headline }}</h3>

                                        @if ($item->obsidian_conflict)
                                            <span class="assignment-chip assignment-chip--conflict" title="Conflict detected: both Obsidian and database have changes since last sync">
                                                <span class="assignment-chip__dot" aria-hidden="true"></span>
                                                <span>Conflict</span>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-stone-600">
                                        @if ($item->academic_year)<span>AY {{ $item->academic_year }}</span>@endif
                                        @if ($item->role_in_activity && $headline !== $item->role_in_activity)<span>{{ $item->role_in_activity }}</span>@endif
                                        <span class="compact-pill">{{ $item->duration_label }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 sm:col-span-2 xl:col-span-1 xl:justify-end">
                                    <button type="button" class="secondary-button compact-inline-button" @click="$dispatch('open-entry-modal', { type: '{{ $type->value }}', entryId: {{ $item->id }} })" aria-haspopup="dialog" aria-label="Edit record: {{ $headline }}">Edit record</button>
                                    <x-entry-modal :type="$type" :entry="$item" :report="$reportGroup"
                                        :form-action="route('reports.records.update', [$reportGroup, $item])" form-method="PATCH" />

                                    <form method="POST" action="{{ route('reports.records.destroy', [$reportGroup, $item]) }}" data-confirm="Remove this record from the saved report? The live ledger entry will not be deleted.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="destructive-link">Delete record</button>
                                    </form>
                                </div>
                            </div>

                            @if ($item->obsidian_conflict)
                                <div class="mt-3 rounded-cell border border-stone-900/10 bg-amber-50/70 p-3">
                                    <p class="text-sm leading-6 text-stone-700">Obsidian and the database both changed since the last sync. Choose which copy to keep.</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('reports.records.conflict.accept-vault', [$reportGroup, $item]) }}" class="inline-flex" data-confirm="Keep the Obsidian copy? The database change to this record will be lost.">
                                            @csrf
                                            <button type="submit" class="secondary-button compact-inline-button" title="Keep the Obsidian version and overwrite database changes">
                                                Keep Obsidian copy
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('reports.records.conflict.accept-db', [$reportGroup, $item]) }}" class="inline-flex" data-confirm="Keep the database copy? The Obsidian change to this record will be lost.">
                                            @csrf
                                            <button type="submit" class="secondary-button compact-inline-button" title="Keep the database version and write it to Obsidian">
                                                Keep database copy
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif
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
