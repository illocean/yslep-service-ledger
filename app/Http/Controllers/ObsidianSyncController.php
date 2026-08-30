<?php

namespace App\Http\Controllers;

use App\Enums\IndexType;
use App\Http\Requests\DestroyIndexEntryRequest;
use App\Http\Requests\StoreIndexEntryRequest;
use App\Services\ObsidianSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function acceptVault(int $entry, Request $request, ObsidianSyncService $syncService): RedirectResponse
    {
        $type = IndexType::from($request->input('type'));
        $syncService->resolveConflictAcceptVault($type, $entry);

        return redirect()
            ->route('indexes.show', ['type' => $type->value, 'scope' => $request->input('scope', 'all')])
            ->with('status', 'Conflict resolved: accepted vault version.');
    }

    public function acceptDb(int $entry, Request $request, ObsidianSyncService $syncService): RedirectResponse
    {
        $type = IndexType::from($request->input('type'));
        $syncService->resolveConflictAcceptDb($type, $entry);

        return redirect()
            ->route('indexes.show', ['type' => $type->value, 'scope' => $request->input('scope', 'all')])
            ->with('status', 'Conflict resolved: accepted database version.');
    }

    public function merge(int $entry, Request $request, ObsidianSyncService $syncService): RedirectResponse
    {
        $type = IndexType::from($request->input('type'));
        $mergedData = $request->except(['_token', 'type', 'scope']);
        $syncService->resolveConflictMerge($type, $entry, $mergedData);

        return redirect()
            ->route('indexes.show', ['type' => $type->value, 'scope' => $request->input('scope', 'all')])
            ->with('status', 'Conflict resolved: merged changes.');
    }
}
