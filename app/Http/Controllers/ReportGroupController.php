<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportGroupRequest;
use App\Services\ReportGroupService;
use Illuminate\Http\RedirectResponse;

class ReportGroupController extends Controller
{
    public function store(StoreReportGroupRequest $request, ReportGroupService $reportGroupService): RedirectResponse
    {
        $reportGroup = $reportGroupService->create(
            $request->reportTitle(),
            $request->selectedEntryMap(),
        );

        return redirect()
            ->route('reports.show', $reportGroup)
            ->with('status', 'Report group saved as '.$reportGroup->display_label.'.');
    }
}
