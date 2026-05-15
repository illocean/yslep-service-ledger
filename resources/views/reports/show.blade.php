@extends('layouts.app')

@section('title', $reportGroup->display_label)

@section('content')
    @include('partials.alerts')

    <section class="paper-panel overflow-hidden rounded-[2rem]">
        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[1.45fr_0.85fr] lg:px-10 lg:py-8">
            <div class="space-y-3">
                <div class="section-kicker">Saved Report</div>
                <h1 class="font-serif text-3xl leading-tight text-stone-900 sm:text-4xl">
                    {{ $reportGroup->title ?: 'Untitled Report' }}
                </h1>
                <div class="font-mono text-xs text-stone-500">Tag: {{ $reportGroup->tag }}</div>
            </div>

            <div class="paper-panel rounded-[1.5rem] p-4">
                <div class="section-kicker">Totals</div>
                <div class="mt-3 grid gap-3">
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Created</div>
                        <div class="mt-1 text-sm font-semibold text-stone-900">{{ $reportGroup->created_at?->setTimezone(config('app.timezone'))->format('F j, Y g:i A') }}</div>
                    </div>
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Records</div>
                        <div class="mt-1 text-sm font-semibold text-stone-900">{{ $reportGroup->items->count() }}</div>
                    </div>
                    <div class="rounded-[1.25rem] border border-stone-900/10 bg-white/70 p-3">
                        <div class="form-label">Hours</div>
                        <div class="mt-1 font-bold text-stone-900">{{ $grandTotalLabel }}</div>
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

        <article class="stat-panel rounded-[1.75rem] border-[color:var(--ledger-accent)] p-5">
            <div class="section-kicker">Grand Total</div>
            <div class="mt-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Combined hours across all three indexes</div>
                <div class="mt-3 font-serif text-4xl text-stone-950">{{ $grandTotalLabel }}</div>
            </div>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
            <div class="section-kicker">Rename</div>
            <h2 class="mt-1 font-serif text-xl text-stone-950">Report title</h2>

            <form method="POST" action="{{ route('reports.update', $reportGroup) }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <input id="title" name="title" type="text" value="{{ old('title', $reportGroup->title) }}" placeholder="Leave blank to show only the tag" class="form-input">
                </div>

                <button type="submit" class="primary-button">Save Changes</button>
            </form>
        </article>

        <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
            <div class="space-y-4">
                <div>
                    <div class="section-kicker">Sync</div>
                    <h2 class="mt-1 font-serif text-xl text-stone-950">Pull edits from Obsidian</h2>

                    <form method="POST" action="{{ route('reports.sync-from-obsidian') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="secondary-button">Sync from Obsidian</button>
                    </form>
                </div>

                <div class="rounded-[1.5rem] border border-red-900/10 bg-red-50/70 p-4">
                    <div class="section-kicker text-red-700">Danger Zone</div>
                    <p class="mt-2 text-sm text-stone-700">
                        This removes record notes and frees entries for reuse.
                    </p>

                    <form method="POST" action="{{ route('reports.destroy', $reportGroup) }}" class="mt-3">
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
            @endphp

            <article class="paper-panel rounded-[2rem] p-5 sm:p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="section-kicker">{{ $type->cardTitle() }}</div>
                        <h2 class="mt-1 font-serif text-xl text-stone-950">{{ $type->label() }}</h2>
                    </div>
                    <div class="rounded-full border border-stone-900/10 bg-white/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                        {{ $items->count() }} record(s)
                    </div>
                </div>

                <form method="POST" action="{{ route('reports.records.store', $reportGroup) }}" class="record-form-shell mt-6">
                    @csrf
                    <input type="hidden" name="index_type" value="{{ $type->value }}">

                    <div class="record-form-grid">
                        <div>
                            <label for="{{ $sectionPrefix }}-served-on" class="form-label">Date</label>
                            <input id="{{ $sectionPrefix }}-served-on" name="served_on" type="date" value="{{ old('index_type') === $type->value ? old('served_on') : '' }}" class="form-input mt-2">
                        </div>

                        @if ($type === \App\Enums\IndexType::Formation)
                            <div>
                                <label for="{{ $sectionPrefix }}-cycle-code" class="form-label">Cycle No.</label>
                                    <input id="{{ $sectionPrefix }}-cycle-code" name="cycle_code" type="text" value="{{ old('index_type') === $type->value ? old('cycle_code') : '' }}" placeholder="C1" class="form-input mt-2">
                                </div>
                                <div>
                                    <label for="{{ $sectionPrefix }}-module-code" class="form-label">Module</label>
                                    <input id="{{ $sectionPrefix }}-module-code" name="module_code" type="text" value="{{ old('index_type') === $type->value ? old('module_code') : '' }}" placeholder="M1" class="form-input mt-2">
                                </div>
                                <div class="record-form-grid__wide">
                                    <label for="{{ $sectionPrefix }}-title" class="form-label">Title</label>
                                    <input id="{{ $sectionPrefix }}-title" name="title" type="text" value="{{ old('index_type') === $type->value ? old('title') : '' }}" placeholder="Formation Session" class="form-input mt-2">
                                </div>
                            @endif

                            @if ($type === \App\Enums\IndexType::SocialApostolate)
                                <div class="record-form-grid__wide">
                                    <label for="{{ $sectionPrefix }}-about" class="form-label">Activity</label>
                                    <input id="{{ $sectionPrefix }}-about" name="about" type="text" value="{{ old('index_type') === $type->value ? old('about') : '' }}" placeholder="Service activity" class="form-input mt-2">
                            </div>
                        @endif

                        <div>
                            <label for="{{ $sectionPrefix }}-time-start" class="form-label">Time in</label>
                            <input id="{{ $sectionPrefix }}-time-start" name="time_start" type="time" value="{{ old('index_type') === $type->value ? old('time_start') : '' }}" class="form-input mt-2">
                        </div>
                        <div>
                            <label for="{{ $sectionPrefix }}-time-end" class="form-label">Time out</label>
                            <input id="{{ $sectionPrefix }}-time-end" name="time_end" type="time" value="{{ old('index_type') === $type->value ? old('time_end') : '' }}" class="form-input mt-2">
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="primary-button">
                            Add Record
                        </button>
                    </div>
                </form>

                <div class="mt-6 space-y-4">
                    @forelse ($items as $item)
                        <article class="report-record-card">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="assignment-chip assignment-chip--saved">
                                            <span class="assignment-chip__dot" aria-hidden="true"></span>
                                            <span>{{ $type->label() }}</span>
                                        </span>
                                        <span class="compact-pill">{{ $item->duration_label }}</span>
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
                                    </div>

                                    @if ($item->obsidian_note_path)
                                        <div class="break-all font-mono text-[0.72rem] text-stone-500">
                                            {{ $item->obsidian_note_path }}
                                        </div>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('reports.records.destroy', [$reportGroup, $item]) }}" class="xl:pt-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button">
                                        Delete Record
                                    </button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('reports.records.update', [$reportGroup, $item]) }}" class="record-form-shell mt-5">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="index_type" value="{{ $type->value }}">

                                <div class="record-form-grid">
                                    <div>
                                        <label for="record-{{ $item->id }}-served-on" class="form-label">Date</label>
                                        <input id="record-{{ $item->id }}-served-on" name="served_on" type="date" value="{{ $item->served_on?->toDateString() }}" class="form-input mt-2">
                                    </div>

                                    @if ($type === \App\Enums\IndexType::Formation)
                                        <div>
                                            <label for="record-{{ $item->id }}-cycle-code" class="form-label">Cycle No.</label>
                                            <input id="record-{{ $item->id }}-cycle-code" name="cycle_code" type="text" value="{{ $item->cycle_code }}" class="form-input mt-2">
                                        </div>
                                        <div>
                                            <label for="record-{{ $item->id }}-module-code" class="form-label">Module</label>
                                            <input id="record-{{ $item->id }}-module-code" name="module_code" type="text" value="{{ $item->module_code }}" class="form-input mt-2">
                                        </div>
                                        <div class="record-form-grid__wide">
                                            <label for="record-{{ $item->id }}-title" class="form-label">Title</label>
                                            <input id="record-{{ $item->id }}-title" name="title" type="text" value="{{ $item->title }}" class="form-input mt-2">
                                        </div>
                                    @endif

                                    @if ($type === \App\Enums\IndexType::SocialApostolate)
                                        <div class="record-form-grid__wide">
                                            <label for="record-{{ $item->id }}-about" class="form-label">Activity / about</label>
                                            <input id="record-{{ $item->id }}-about" name="about" type="text" value="{{ $item->about }}" class="form-input mt-2">
                                        </div>
                                    @endif

                                    <div>
                                        <label for="record-{{ $item->id }}-time-start" class="form-label">Time in</label>
                                        <input id="record-{{ $item->id }}-time-start" name="time_start" type="time" value="{{ substr((string) $item->getRawOriginal('time_start'), 0, 5) }}" class="form-input mt-2">
                                    </div>
                                    <div>
                                        <label for="record-{{ $item->id }}-time-end" class="form-label">Time out</label>
                                        <input id="record-{{ $item->id }}-time-end" name="time_end" type="time" value="{{ substr((string) $item->getRawOriginal('time_end'), 0, 5) }}" class="form-input mt-2">
                                    </div>
                                </div>

                                <div class="mt-4 flex justify-end">
                                    <button type="submit" class="secondary-button !w-auto">
                                        Update Record
                                    </button>
                                </div>
                            </form>
                        </article>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm leading-7 text-stone-600">
                            No {{ strtolower($type->label()) }} records attached yet.
                        </div>
                    @endforelse
                </div>
            </article>
        @endforeach
    </section>
@endsection
