<?php

namespace App\Http\Controllers;

use App\Enums\IndexType;
use App\Http\Controllers\Concerns\BuildsReportScopeData;
use App\Http\Requests\StoreAcademicYearSnapshotRequest;
use App\Models\AcademicYearSnapshot;
use App\Services\AcademicYearSnapshotService;
use App\Services\ReportGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicYearSnapshotController extends Controller
{
    use BuildsReportScopeData;

    public function index(
        Request $request,
        AcademicYearSnapshotService $snapshotService,
        ReportGroupService $reportGroupService,
    ): View {
        $reportGroups = $reportGroupService->all();
        $snapshots = $snapshotService->all();
        $assignedSnapshotLookup = $snapshotService->assignedSnapshotLookup();
        $availableReportGroups = $reportGroups
            ->reject(fn ($reportGroup): bool => array_key_exists($reportGroup->id, $assignedSnapshotLookup))
            ->values();
        $archivedReportGroups = $reportGroups
            ->filter(fn ($reportGroup): bool => array_key_exists($reportGroup->id, $assignedSnapshotLookup))
            ->map(function ($reportGroup) use ($assignedSnapshotLookup): array {
                return [
                    'reportGroup' => $reportGroup,
                    'snapshot' => $assignedSnapshotLookup[$reportGroup->id],
                ];
            })
            ->values();

        $selection = $request->session()->hasOldInput()
            ? old('selected_report_groups', [])
            : explode(',', (string) $request->query('prefill', ''));
        $prefilledReportGroupIds = collect($selection)
            ->map(fn ($id): int => (int) $id)
            ->intersect($availableReportGroups->pluck('id'))
            ->unique()
            ->values();

        return view('academic-year-snapshots.index', [
            'reportGroups' => $reportGroups,
            'availableReportGroups' => $availableReportGroups,
            'archivedReportGroups' => $archivedReportGroups,
            'snapshots' => $snapshots,
            'snapshotFilePath' => $snapshotService->snapshotPath(),
            'prefilledReportGroupIds' => $prefilledReportGroupIds,
        ]);
    }

    public function show(AcademicYearSnapshot $academicYearSnapshot, AcademicYearSnapshotService $snapshotService): View
    {
        $academicYearSnapshot->load(['items.reportGroup']);
        $cards = [];

        foreach (IndexType::cases() as $type) {
            $cards[$type->value] = $this->summaryForType($type, $academicYearSnapshot->itemsFor($type->value));
        }

        $grandTotalMinutes = collect($cards)->sum('total_minutes');

        return view('academic-year-snapshots.show', [
            'academicYearSnapshot' => $academicYearSnapshot,
            'cards' => $cards,
            'grandTotalLabel' => $this->formatMinutes($grandTotalMinutes),
            'snapshotFilePath' => $snapshotService->snapshotPath(),
        ]);
    }

    public function store(
        StoreAcademicYearSnapshotRequest $request,
        AcademicYearSnapshotService $snapshotService,
    ): RedirectResponse {
        $snapshot = $snapshotService->create(
            $request->snapshotTitle(),
            $request->academicYear(),
            $request->selectedReportGroupIds(),
        );

        return redirect()
            ->route('academic-year-snapshots.show', $snapshot)
            ->with('status', 'Academic year snapshot saved as '.$snapshot->display_label.'.');
    }

    public function destroy(
        AcademicYearSnapshot $academicYearSnapshot,
        AcademicYearSnapshotService $snapshotService,
    ): RedirectResponse {
        $label = $academicYearSnapshot->display_label;
        $snapshotService->delete($academicYearSnapshot);

        return redirect()
            ->route('academic-year-snapshots.index')
            ->with('status', 'Academic year snapshot '.$label.' was deleted.');
    }
}
