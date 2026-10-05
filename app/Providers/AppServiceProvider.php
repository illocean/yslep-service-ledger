<?php

namespace App\Providers;

use App\Enums\IndexType;
use App\Models\AcademicYearSnapshot;
use App\Models\ReportGroupItem;
use App\Services\ObsidianSyncService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('type', static fn (string $value): IndexType => IndexType::fromRouteValue($value));

        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $sync = app(ObsidianSyncService::class);
            $years = collect(IndexType::cases())
                ->flatMap(fn (IndexType $type): array => $sync->cardMeta($type)['entry_options']['academic_years'] ?? [])
                ->merge(AcademicYearSnapshot::query()->pluck('academic_year'))
                ->merge(ReportGroupItem::query()->whereNotNull('academic_year')->distinct()->pluck('academic_year'))
                ->filter()->unique()->sortDesc()->values();
            $view->with('entryAcademicYears', $years);
        });
    }
}
