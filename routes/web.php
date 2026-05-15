<?php

use App\Http\Controllers\AcademicYearSnapshotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IndexPageController;
use App\Http\Controllers\ObsidianSyncController;
use App\Http\Controllers\ReportGroupController;
use App\Http\Controllers\ReportGroupItemController;
use App\Http\Controllers\ReportObsidianSyncController;
use App\Http\Controllers\SavedReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/indexes/{type}', [IndexPageController::class, 'show'])->name('indexes.show');
Route::get('/reports', [SavedReportController::class, 'index'])->name('reports.index');
Route::get('/reports/{reportGroup}', [SavedReportController::class, 'show'])->name('reports.show');
Route::patch('/reports/{reportGroup}', [SavedReportController::class, 'update'])->name('reports.update');
Route::delete('/reports/{reportGroup}', [SavedReportController::class, 'destroy'])->name('reports.destroy');
Route::post('/reports/sync-from-obsidian', [ReportObsidianSyncController::class, 'store'])->name('reports.sync-from-obsidian');
Route::post('/reports/{reportGroup}/records', [ReportGroupItemController::class, 'store'])->name('reports.records.store');
Route::patch('/reports/{reportGroup}/records/{reportGroupItem}', [ReportGroupItemController::class, 'update'])->name('reports.records.update');
Route::delete('/reports/{reportGroup}/records/{reportGroupItem}', [ReportGroupItemController::class, 'destroy'])->name('reports.records.destroy');
Route::get('/academic-year-snapshots', [AcademicYearSnapshotController::class, 'index'])->name('academic-year-snapshots.index');
Route::get('/academic-year-snapshots/{academicYearSnapshot}', [AcademicYearSnapshotController::class, 'show'])->name('academic-year-snapshots.show');
Route::post('/academic-year-snapshots', [AcademicYearSnapshotController::class, 'store'])->name('academic-year-snapshots.store');
Route::delete('/academic-year-snapshots/{academicYearSnapshot}', [AcademicYearSnapshotController::class, 'destroy'])->name('academic-year-snapshots.destroy');
Route::post('/entries', [ObsidianSyncController::class, 'store'])->name('entries.store');
Route::patch('/entries/{entry}', [ObsidianSyncController::class, 'update'])->whereNumber('entry')->name('entries.update');
Route::delete('/entries/{entry}', [ObsidianSyncController::class, 'destroy'])->whereNumber('entry')->name('entries.destroy');
Route::post('/report-groups', [ReportGroupController::class, 'store'])->name('report-groups.store');
