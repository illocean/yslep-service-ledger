<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AcademicYearSnapshot extends Model
{
    protected $fillable = [
        'tag',
        'academic_year',
        'title',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AcademicYearSnapshotItem::class)->orderBy('served_on')->orderBy('source_order');
    }

    public function itemsFor(string $indexType): Collection
    {
        return $this->items->where('index_type', $indexType)->values();
    }

    public function reportGroups(): Collection
    {
        return $this->items
            ->map(fn (AcademicYearSnapshotItem $item) => $item->reportGroup)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function includedReportLabels(): Collection
    {
        return $this->items
            ->map(fn (AcademicYearSnapshotItem $item): string => $item->source_report_display_label)
            ->filter()
            ->unique()
            ->values();
    }

    public function getDisplayLabelAttribute(): string
    {
        return filled($this->title) ? $this->title : $this->academic_year;
    }

    public function getCompactLabelAttribute(): string
    {
        return filled($this->title) ? $this->title : $this->academic_year;
    }

    public function getRouteKeyName(): string
    {
        return 'tag';
    }
}
