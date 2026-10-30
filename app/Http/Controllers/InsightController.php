<?php

namespace App\Http\Controllers;

use App\Enums\IndexType;
use App\Http\Controllers\Concerns\BuildsReportScopeData;
use App\Models\AcademicYearSnapshot;
use App\Models\ReportGroup;
use App\Services\ObsidianSyncService;
use Illuminate\View\View;

class InsightController extends Controller
{
    use BuildsReportScopeData;

    public function __invoke(
        ObsidianSyncService $syncService,
    ): View {
        $syncService->syncAll();

        $reportGroups = ReportGroup::query()
            ->with(['items' => fn ($query) => $query->orderBy('served_on')->orderBy('source_order')])
            ->orderBy('tag')
            ->get();

        $snapshots = AcademicYearSnapshot::query()
            ->with(['items' => fn ($query) => $query->orderBy('served_on')->orderBy('source_order')])
            ->orderByDesc('academic_year')
            ->orderBy('title')
            ->get();

        // Which saved report sits inside which academic year. A report belongs
        // to at most one year: the snapshot layer is the authority, never a guess.
        $snapshotByReportId = [];

        foreach ($snapshots as $snapshot) {
            foreach ($snapshot->items as $item) {
                if ($item->report_group_id === null) {
                    continue;
                }

                if (array_key_exists($item->report_group_id, $snapshotByReportId)) {
                    continue;
                }

                $snapshotByReportId[$item->report_group_id] = $snapshot;
            }
        }

        $reportCards = $reportGroups->map(function (ReportGroup $reportGroup) use ($snapshotByReportId): array {
            return $this->reportCard($reportGroup, $snapshotByReportId[$reportGroup->id] ?? null);
        })->values();

        // One group per academic year, descending. Empty years (no reports) are omitted.
        $yearGroups = $snapshots->map(function (AcademicYearSnapshot $snapshot) use ($reportCards): array {
            return [
                'snapshot' => $snapshot,
                'label' => $snapshot->display_label,
                'academic_year' => $snapshot->academic_year,
                'reports' => $reportCards
                    ->filter(fn (array $card): bool => $card['snapshot_id'] === $snapshot->id)
                    ->values(),
            ];
        })->filter(fn (array $group): bool => $group['reports']->isNotEmpty())->values();

        // Reports no saved academic year has claimed yet.
        $unfiledReports = $reportCards
            ->filter(fn (array $card): bool => $card['snapshot_id'] === null)
            ->values();

        return view('insight.index', [
            'yearGroups' => $yearGroups,
            'unfiledReports' => $unfiledReports,
            'reportCards' => $reportCards,
            'grandTotalHours' => $this->formatMinutes($reportCards->sum('total_minutes')),
            'indexTypes' => IndexType::cases(),
            'hasAnyReport' => $reportCards->isNotEmpty(),
        ]);
    }

    /**
     * Reduce one saved report to its three index totals.
     *
     * @return array{
     *   reportGroup: ReportGroup,
     *   snapshot_id: ?int,
     *   snapshot_label: ?string,
     *   totals: array<string, array{count: int, minutes: int, label: string}>,
     *   total_minutes: int,
     *   total_label: string,
     *   first_served_on: mixed,
     *   last_served_on: mixed
     * }
     */
    private function reportCard(ReportGroup $reportGroup, ?AcademicYearSnapshot $snapshot): array
    {
        $totals = [];

        foreach (IndexType::cases() as $type) {
            $entries = $reportGroup->itemsFor($type->value);
            $minutes = (int) $entries->sum('duration_minutes');

            $totals[$type->value] = [
                'count' => $entries->count(),
                'minutes' => $minutes,
                'label' => $this->formatMinutes($minutes),
            ];
        }

        $totalMinutes = (int) array_sum(array_column($totals, 'minutes'));
        $servedDates = $reportGroup->items
            ->map(fn ($item) => $item->served_on)
            ->filter()
            ->sort()
            ->values();

        return [
            'reportGroup' => $reportGroup,
            'snapshot_id' => $snapshot?->id,
            'snapshot_label' => $snapshot?->display_label,
            'totals' => $totals,
            'total_minutes' => $totalMinutes,
            'total_label' => $this->formatMinutes($totalMinutes),
            'first_served_on' => $servedDates->first(),
            'last_served_on' => $servedDates->last(),
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($minutes === 0) {
            return '0 hr';
        }

        if ($remainingMinutes === 0) {
            return $hours.' hr';
        }

        return sprintf('%d hr %02d min', $hours, $remainingMinutes);
    }
}
