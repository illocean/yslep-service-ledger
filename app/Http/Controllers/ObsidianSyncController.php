<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyIndexEntryRequest;
use App\Http\Requests\StoreIndexEntryRequest;
use App\Services\ObsidianSyncService;
use Illuminate\Http\RedirectResponse;

class ObsidianSyncController extends Controller
{
    public function store(StoreIndexEntryRequest $request, ObsidianSyncService $syncService): RedirectResponse
    {
        $type = $request->indexType();
        $syncService->appendRecord($type, $request->recordPayload());

        return redirect()
            ->route('indexes.show', $request->indexRouteParameters($type))
            ->with('status', $type->label().' entry added and synced to Obsidian.');
    }

    public function update(
        StoreIndexEntryRequest $request,
        int $entry,
        ObsidianSyncService $syncService,
    ): RedirectResponse {
        $type = $request->indexType();
        $syncService->updateRecord($type, $entry, $request->recordPayload());

        return redirect()
            ->route('indexes.show', $request->indexRouteParameters($type))
            ->with('status', $type->label().' entry updated and synced to Obsidian.');
    }

    public function destroy(
        DestroyIndexEntryRequest $request,
        int $entry,
        ObsidianSyncService $syncService,
    ): RedirectResponse {
        $type = $request->indexType();
        $syncService->deleteRecord($type, $entry);

        return redirect()
            ->route('indexes.show', $request->indexRouteParameters($type))
            ->with('status', $type->label().' entry removed and synced to Obsidian.');
    }
}
