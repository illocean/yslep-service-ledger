<?php

namespace App\Http\Controllers;

use App\Enums\IndexType;
use App\Http\Requests\UpsertReportGroupItemRequest;
use App\Models\ReportGroup;
use App\Models\ReportGroupItem;
use App\Services\ReportGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportGroupItemController extends Controller
{
    public function store(
        UpsertReportGroupItemRequest $request,
        ReportGroup $reportGroup,
        ReportGroupService $reportGroupService,
    ): RedirectResponse {
        $item = $reportGroupService->addItem(
            $reportGroup,
            $request->indexType(),
            $request->recordPayload(),
        );

        if ($request->hasIndexReturnContext()) {
            return redirect()
                ->route('indexes.show', $request->indexRouteParameters($request->indexType()))
                ->with('status', $item->reportGroup->compact_label.' now includes a new '.$request->indexType()->label().' record.');
        }

        return redirect()
            ->route('reports.show', $reportGroup)
            ->with('status', $item->reportGroup->compact_label.' now includes a new '.$request->indexType()->label().' record.');
    }

    public function update(
        UpsertReportGroupItemRequest $request,
        ReportGroup $reportGroup,
        ReportGroupItem $reportGroupItem,
        ReportGroupService $reportGroupService,
    ): RedirectResponse {
        abort_unless($reportGroupItem->report_group_id === $reportGroup->id, 404);

        $reportGroupService->updateItem(
            $reportGroup,
            $reportGroupItem,
            $request->indexType(),
            $request->recordPayload(),
        );

        if ($request->hasIndexReturnContext()) {
            return redirect()
                ->route('indexes.show', $request->indexRouteParameters($request->indexType()))
                ->with('status', $request->indexType()->label().' record updated for '.$reportGroup->compact_label.'.');
        }

        return redirect()
            ->route('reports.show', $reportGroup)
            ->with('status', $request->indexType()->label().' record updated for '.$reportGroup->compact_label.'.');
    }

    public function destroy(
        Request $request,
        ReportGroup $reportGroup,
        ReportGroupItem $reportGroupItem,
        ReportGroupService $reportGroupService,
    ): RedirectResponse {
        abort_unless($reportGroupItem->report_group_id === $reportGroup->id, 404);

        $label = IndexType::from($reportGroupItem->index_type)->label();

        $reportGroupService->deleteItem($reportGroup, $reportGroupItem);

        if ($request->filled('return_type')) {
            $parameters = [
                'type' => $request->string('return_type')->toString(),
            ];

            if ($request->filled('return_scope')) {
                $parameters['scope'] = $request->string('return_scope')->toString();
            }

            if ($request->filled('return_report')) {
                $parameters['report'] = $request->string('return_report')->toString();
            }

            return redirect()
                ->route('indexes.show', $parameters)
                ->with('status', 'Removed the '.$label.' record from '.$reportGroup->compact_label.'.');
        }

        return redirect()
            ->route('reports.show', $reportGroup)
            ->with('status', 'Removed the '.$label.' record from '.$reportGroup->compact_label.'.');
    }
}
