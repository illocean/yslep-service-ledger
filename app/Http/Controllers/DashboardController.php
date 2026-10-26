<?php

namespace App\Http\Controllers;

use App\Enums\IndexType;
use App\Http\Controllers\Concerns\BuildsReportScopeData;
use App\Models\ReportGroup;
use App\Services\ObsidianSyncService;
use App\Services\ReportGroupService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use BuildsReportScopeData;

    public function __invoke(
        ObsidianSyncService $syncService,
        ReportGroupService $reportGroupService,
    ): View {
        $syncService->syncAll();
        $reportGroups = $reportGroupService->all();
        $assignedReportLookup = $reportGroupService->assignedReportLookup();
        $builder = $this->saveGroupData($assignedReportLookup);

        $cards = [];
        $meta = [];

        foreach (IndexType::cases() as $type) {
            $cards[$type->value] = $this->summaryForType($type, $builder['live_entries'][$type->value]);
            $meta[$type->value] = $syncService->cardMeta($type);
        }

        $grandTotalMinutes = collect($cards)->sum('total_minutes');

        return view('dashboard', [
            'cards' => $cards,
            'saveGroupEntries' => $builder['save_group_entries'],
            'liveEntryStats' => $builder['live_entry_stats'],
            'meta' => $meta,
            'reportGroups' => $reportGroups,
            'assignedReportLookup' => $assignedReportLookup,
            'grandTotalLabel' => $this->formatMinutes($grandTotalMinutes),
            'series' => $this->monthlySeries($builder['live_entries']),
            'academicYearTotals' => $this->academicYearTotals($builder['live_entries']),
            'recentEntries' => $this->recentEntries($builder['live_entries'], $assignedReportLookup),
        ]);
    }

    /**
     * Monthly hours and entry counts per index, for every month between the
     * first and last dated entry (missing months are real gaps, kept explicit).
     *
     * @param  array<string, Collection>  $liveEntries
     * @return array<string, mixed>
     */
    private function monthlySeries(array $liveEntries): array
    {
        $minutesByType = [];
        $countsByType = [];
        $labelsByType = [];
        $monthTotals = [];
        $monthCounts = [];
        $undatedCount = 0;

        foreach (IndexType::cases() as $type) {
            $minutesByType[$type->value] = [];
            $countsByType[$type->value] = [];
            $labelsByType[$type->value] = [];

            foreach ($liveEntries[$type->value] as $entry) {
                if ($entry->served_on === null) {
                    $undatedCount++;

                    continue;
                }

                $key = $entry->served_on->format('Y-m');
                $minutesByType[$type->value][$key] = ($minutesByType[$type->value][$key] ?? 0) + $entry->duration_minutes;
                $countsByType[$type->value][$key] = ($countsByType[$type->value][$key] ?? 0) + 1;
                $labelsByType[$type->value][$key] = $this->formatMinutes($minutesByType[$type->value][$key]);
                $monthTotals[$key] = ($monthTotals[$key] ?? 0) + $entry->duration_minutes;
                $monthCounts[$key] = ($monthCounts[$key] ?? 0) + 1;
            }
        }

        if ($monthTotals === []) {
            return [
                'months' => [],
                'byType' => $minutesByType,
                'countsByType' => $countsByType,
                'labelsByType' => $labelsByType,
                'maxMinutes' => 0,
                'gaps' => [],
                'undatedCount' => $undatedCount,
                'periodLabel' => null,
                'lastActiveMonth' => null,
                'monthsSinceLast' => null,
                'emptyMonthsSince' => [],
                'busiestMonth' => null,
            ];
        }

        ksort($monthTotals);

        $first = CarbonImmutable::createFromFormat('Y-m', array_key_first($monthTotals))->startOfMonth();
        $last = CarbonImmutable::createFromFormat('Y-m', array_key_last($monthTotals))->startOfMonth();
        $months = [];
        $cursor = $first;
        $lastActiveMonth = null;

        while ($cursor->lte($last)) {
            $key = $cursor->format('Y-m');

            $month = [
                'key' => $key,
                'label' => $cursor->format('M'),
                'name' => $cursor->format('F'),
                'year' => $cursor->format('Y'),
                'minutes' => $monthTotals[$key] ?? 0,
                'total_label' => $this->formatMinutes($monthTotals[$key] ?? 0),
                'count' => $monthCounts[$key] ?? 0,
            ];

            $months[] = $month;
            $lastActiveMonth = $month['minutes'] > 0 ? $month : $lastActiveMonth;

            $cursor = $cursor->addMonthNoOverflow();
        }

        $now = CarbonImmutable::now();
        $nowIndex = (int) $now->format('Y') * 12 + (int) $now->format('n');
        $lastActiveIndex = $lastActiveMonth === null
            ? null
            : (int) substr($lastActiveMonth['key'], 0, 4) * 12 + (int) substr($lastActiveMonth['key'], 5, 2);

        $emptyMonthsSince = [];
        $cursor = $lastActiveMonth === null
            ? null
            : CarbonImmutable::createFromFormat('Y-m', $lastActiveMonth['key'])->addMonthNoOverflow()->startOfMonth();

        while ($cursor !== null && $cursor->lte($now->startOfMonth()) && count($emptyMonthsSince) < 3) {
            $emptyMonthsSince[] = $cursor->format('F Y');
            $cursor = $cursor->addMonthNoOverflow();
        }

        return [
            'months' => $months,
            'byType' => $minutesByType,
            'countsByType' => $countsByType,
            'labelsByType' => $labelsByType,
            'maxMinutes' => max(array_column($months, 'minutes')),
            'gaps' => array_values(array_filter($months, fn (array $month): bool => $month['minutes'] === 0)),
            'undatedCount' => $undatedCount,
            'periodLabel' => $first->format('M Y').' — '.$last->format('M Y'),
            'lastActiveMonth' => $lastActiveMonth,
            'monthsSinceLast' => $lastActiveIndex === null ? null : $nowIndex - $lastActiveIndex,
            'emptyMonthsSince' => $emptyMonthsSince,
            'busiestMonth' => collect($months)->sortByDesc('minutes')->first(),
        ];
    }

    /**
     * All-time totals grouped by the academic year stored on each entry.
     * Years are never inferred: entries without one bucket as "Not set".
     *
     * @param  array<string, Collection>  $liveEntries
     * @return list<array{year: ?string, label: string, count: int, minutes: int, hours_label: string}>
     */
    private function academicYearTotals(array $liveEntries): array
    {
        $buckets = [];

        foreach (IndexType::cases() as $type) {
            foreach ($liveEntries[$type->value] as $entry) {
                $year = filled($entry->academic_year) ? (string) $entry->academic_year : '';
                $buckets[$year] ??= ['year' => $year === '' ? null : $year, 'count' => 0, 'minutes' => 0];
                $buckets[$year]['count']++;
                $buckets[$year]['minutes'] += $entry->duration_minutes;
            }
        }

        $notSet = $buckets[''] ?? null;
        unset($buckets['']);

        $rows = array_values($buckets);
        usort($rows, fn (array $left, array $right): int => strcmp($left['year'], $right['year']));

        if ($notSet !== null) {
            $rows[] = $notSet;
        }

        return array_map(fn (array $row): array => $row + [
            'label' => $row['year'] ?? 'Not set',
            'hours_label' => $this->formatMinutes($row['minutes']),
        ], $rows);
    }

    /**
     * Newest live entries across the three indexes, with their saved report.
     *
     * @param  array<string, Collection>  $liveEntries
     * @param  array<string, ReportGroup>  $assignedReportLookup
     * @return Collection<int, array{type: IndexType, entry: Model, assignment: ?ReportGroup}>
     */
    private function recentEntries(array $liveEntries, array $assignedReportLookup): Collection
    {
        return collect(IndexType::cases())
            ->flatMap(fn (IndexType $type): array => $liveEntries[$type->value]
                ->map(fn ($entry): array => [
                    'type' => $type,
                    'entry' => $entry,
                    'assignment' => $assignedReportLookup[$type->value.':'.$entry->id] ?? null,
                ])
                ->all())
            ->sortByDesc(fn (array $row): array => [$row['entry']->served_on?->getTimestamp(), $row['entry']->source_order])
            ->take(5)
            ->values();
    }
}
