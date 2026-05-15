<?php

use App\Services\ObsidianSyncService;
use App\Services\ReportGroupService;
use App\Services\ReportGroupVaultSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('obsidian:sync-report-notes', function () {
    $changedReports = app(ReportGroupVaultSyncService::class)->pullFromVault();

    if ($changedReports > 0) {
        app(ReportGroupService::class)->syncMarkdown();
        app(ObsidianSyncService::class)->refreshAssignmentFormatting();
    }

    $this->info(
        $changedReports > 0
            ? 'Synced '.$changedReports.' saved report note update(s) from Obsidian.'
            : 'No saved report note changes were detected.',
    );
})->purpose('Pull saved report record changes from Obsidian note files into Laravel.');
