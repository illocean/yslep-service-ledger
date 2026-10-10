@php
    $selectedEntries = collect(old('selected_entries', []));
    $saveableTypes = collect(\App\Enums\IndexType::cases())
        ->filter(fn (\App\Enums\IndexType $type) => $saveGroupEntries[$type->value]->isNotEmpty())
        ->values();
    $hiddenTypes = collect(\App\Enums\IndexType::cases())
        ->reject(fn (\App\Enums\IndexType $type) => $saveGroupEntries[$type->value]->isNotEmpty())
        ->values();
@endphp

<section class="paper-panel rounded-panel p-5 sm:p-6">
    <details id="save-group-builder" class="group" @if($errors->any() || old('title') || ($builderOpen ?? false)) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 [&::-webkit-details-marker]:hidden">
            <div>
                <div class="section-kicker">Save Report Group</div>
                <h2 class="mt-2 font-serif text-2xl text-stone-950">Build a saved report from unassigned live entries</h2>
            </div>
            <div class="flex items-center gap-2 text-sm font-semibold text-stone-600 transition-transform group-open:rotate-180">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
            </div>
        </summary>

        <form method="POST" action="{{ route('report-groups.store') }}" class="mt-5 space-y-5" data-dirty-guard data-no-keep-scroll>
            @csrf

            <div class="max-w-lg">
                <label for="title" class="form-label">Report Title</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" placeholder="e.g. Christmas break service report" class="form-input mt-2">
            </div>

            @if ($hiddenTypes->isNotEmpty())
                <div class="rounded-card border border-stone-900/10 bg-stone-50/70 p-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-stone-600">
                        <span class="font-semibold">Already saved:</span>
                        @foreach ($hiddenTypes as $type)
                            <x-assignment-chip
                                variant="complete"
                                :label="$type->label()"
                            />
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($saveableTypes->isEmpty())
                <div class="rounded-card border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm text-stone-600">
                    All categories are saved or locked. Create new live entries in Obsidian first.
                </div>
            @else
                <div class="save-group-grid">
                    @foreach ($saveableTypes as $type)
                        <article class="rounded-card border border-stone-900/10 bg-white/70 p-4" data-save-group-card="{{ $type->value }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="section-kicker">{{ $type->cardTitle() }}</div>
                                    <h3 class="mt-1 font-serif text-xl text-stone-950">{{ $type->label() }}</h3>
                                </div>
                                <div class="text-right text-xs font-semibold uppercase tracking-[0.16em] text-stone-600">
                                    {{ $liveEntryStats[$type->value]['available'] }} available
                                </div>
                            </div>

                            <div class="mt-3 max-h-[30rem] overflow-auto rounded-cell border border-stone-900/10 bg-stone-50/70">
                                <table class="ledger-table min-w-full text-left text-sm">
                                    <thead class="bg-stone-950/[0.03] text-xs uppercase tracking-[0.14em] text-stone-600">
                                        <tr>
                                            <th class="px-3 py-2" scope="col">Pick</th>
                                            <th class="px-3 py-2" scope="col">Date</th>
                                            <th class="px-3 py-2" scope="col">Details</th>
                                            <th class="px-3 py-2" scope="col">Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($saveGroupEntries[$type->value] as $entry)
                                            @php $entryValue = $type->value . ':' . $entry->id; @endphp
                                            <tr>
                                                <td class="px-3 py-2 align-top">
                                                    <input type="checkbox" name="selected_entries[]" value="{{ $entryValue }}" @checked($selectedEntries->contains($entryValue)) class="report-checkbox"
                                                        aria-label="Include the {{ $type->label() }} entry from {{ $entry->served_on_label }} ({{ $entry->duration_label }}) in this report">
                                                </td>
                                                <td class="px-3 py-2 align-top whitespace-nowrap">{{ $entry->served_on_label }}</td>
                                                <td class="px-3 py-2 align-top text-stone-600">
                                                    @if ($type === \App\Enums\IndexType::Formation)
                                                        <div>{{ $entry->cycle_code }} / {{ $entry->module_code }}</div>
                                                        <div class="mt-1">{{ $entry->title }}</div>
                                                    @elseif ($type === \App\Enums\IndexType::SocialApostolate)
                                                        <div>{{ $entry->about }}</div>
                                                    @else
                                                        <div>Parish Involvement</div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 align-top whitespace-nowrap">{{ $entry->duration_label }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center justify-end gap-4">
                <button type="submit" class="primary-button">Save Report</button>
            </div>
        </form>
    </details>
</section>
