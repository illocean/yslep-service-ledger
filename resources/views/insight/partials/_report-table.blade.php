@php
    /** @var array $reportCards */
    /** @var array<string,string> $typeColor */
    /** @var string|null $scope */

    // Pre-compute the widest bar for proportional scaling within each table.
    $maxMinutes = $reportCards->max(fn (array $card): int => $card['total_minutes']) ?: 1;
@endphp

<table class="insight-table w-full border-collapse">
    <thead>
        <tr>
            <th class="insight-table__head" scope="col">Report</th>
            <th class="insight-table__head" scope="col">Year</th>
            @foreach ($indexTypes as $type)
                <th class="insight-table__head" scope="col">{{ $type->label() }}</th>
            @endforeach
            <th class="insight-table__head insight-table__head--total" scope="col">Report total</th>
            <th class="insight-table__head sr-only" scope="col">Range</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($reportCards as $card)
            @php
                /** @var \App\Models\ReportGroup $reportGroup */
                $reportGroup = $card['reportGroup'];
            @endphp
            <tr class="insight-table__row">
                {{-- Report identity + drill-in --}}
                <td class="insight-table__cell insight-table__cell--name">
                    <a href="{{ route('reports.show', $reportGroup) }}" class="font-serif text-base font-semibold text-stone-900 hover:text-amber-800">
                        {{ $reportGroup->compact_label }}
                    </a>
                    <span class="mt-0.5 block text-xs text-stone-500/80">{{ $reportGroup->tag }}</span>
                </td>

                {{-- Academic year bucket --}}
                <td class="insight-table__cell">
                    @if ($card['snapshot_label'])
                        <span class="inline-flex items-center gap-1">
                            <span class="block h-1.5 w-1.5 rounded-full bg-stone-400"></span>
                            {{ $card['snapshot_label'] }}
                        </span>
                    @else
                        <span class="text-xs italic text-stone-400">Unfiled</span>
                    @endif
                </td>

                {{-- Three index columns with marginalia bars --}}
                @foreach ($indexTypes as $type)
                    @php
                        $totals = $card['totals'][$type->value];
                        $barRatio = $maxMinutes > 0 ? $totals['minutes'] / $maxMinutes : 0;
                        $fillClass = $typeColor[$type->value] ?? 'fill-formation';
                    @endphp
                    <td class="insight-table__cell">
                        <div class="flex items-end gap-1.5">
                            {{-- Marginalia bar: width proportional to this report's share of the column max --}}
                            <div class="insight-bar" role="img"
                                 aria-label="{{ $type->label() }}: {{ $totals['label'] }} across {{ $totals['count'] }} {{ $totals['count'] === 1 ? 'entry' : 'entries' }}">
                                <div class="insight-bar__track">
                                    <div class="{{ $fillClass }} insight-bar__fill"
                                         style="width: {{ $barRatio * 100 }}%;">
                                    </div>
                                </div>
                            </div>

                            {{-- Stacked figure + count --}}
                            <span class="font-serif text-sm font-medium text-stone-800" aria-hidden="true">
                                {{ $totals['label'] }}
                            </span>
                            <span class="text-xs text-stone-500">
                                {{ $totals['count'] }}
                            </span>
                        </div>

                        {{-- Visually-hidden data table for screen readers --}}
                        <div class="sr-only">
                            <dl class="flex flex-col gap-0.5">
                                <div><dt>{{ $type->label() }} hours</dt><dd>{{ $totals['label'] }}</dd></div>
                                <div><dt>{{ $type->label() }} entries</dt><dd>{{ $totals['count'] }}</dd></div>
                                <div><dt>{{ $type->label() }} minutes</dt><dd>{{ $totals['minutes'] }}</dd></div>
                            </dl>
                        </div>
                    </td>
                @endforeach

                {{-- Report total --}}
                <td class="insight-table__cell insight-table__cell--total">
                    <span class="font-serif text-lg font-semibold text-stone-900">
                        {{ $card['total_label'] }}
                    </span>
                </td>

                {{-- Served range (human-readable) --}}
                <td class="insight-table__cell text-xs text-stone-500">
                    @if ($card['first_served_on'])
                        {{ \Carbon\Carbon::parse($card['first_served_on'])->format('M j') }}–
                        {{ \Carbon\Carbon::parse($card['last_served_on'])->format('M j') }}
                    @else
                        <span class="italic">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="py-12 text-center text-sm text-stone-500">
                    No reports in this group.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="insight-table__foot-label">Grand total all reports</td>
            <td colspan="3" class="insight-table__foot-value">{{ $grandTotalLabel }}</td>
        </tr>
    </tfoot>
</table>
