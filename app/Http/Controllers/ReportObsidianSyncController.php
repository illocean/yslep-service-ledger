<?php

namespace App\Http\Controllers;

use App\Services\ObsidianSyncService;
use App\Services\ReportGroupService;
use App\Services\ReportGroupVaultSyncService;
use Illuminate\Http\RedirectResponse;

class ReportObsidianSyncController extends Controller
{
    public function store(
        ReportGroupVaultSyncService $reportGroupVaultSyncService,
        ReportGroupService $reportGroupService,
        ObsidianSyncService $obsidianSyncService,
    ): RedirectResponse {
        $changedReports = $reportGroupVaultSyncService->pullFromVault();

        if ($changedReports > 0) {
            $reportGroupService->syncMarkdown();
            $obsidianSyncService->refreshAssignmentFormatting();
        }

        return redirect()
            ->route('reports.index')
            ->with(
                'status',
                $changedReports > 0
                    ? 'Pulled '.$changedReports.' saved report note update(s) from Obsidian.'
                    : 'No saved report note changes were detected in Obsidian.',
            );
    }
}
