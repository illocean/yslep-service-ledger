<?php

namespace App\Models;

use App\Models\Concerns\HasDurationAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicYearSnapshotItem extends Model
{
    use HasDurationAttributes;
    use SoftDeletes;

    protected $fillable = [
        'academic_year_snapshot_id',
        'report_group_id',
        'report_group_item_id',
        'source_report_tag',
        'source_report_label',
        'index_type',
        'served_on',
        'time_start',
        'time_end',
        'cycle_code',
        'module_code',
        'title',
        'about',
        'role_in_activity',
        'source_order',
        'obsidian_conflict',
    ];

    protected function casts(): array
    {
        return [
            'served_on' => 'date',
            'report_group_id' => 'integer',
            'report_group_item_id' => 'integer',
        ];
    }

    public function academicYearSnapshot(): BelongsTo
    {
        return $this->belongsTo(AcademicYearSnapshot::class);
    }

    public function reportGroup(): BelongsTo
    {
        return $this->belongsTo(ReportGroup::class);
    }

    public function reportGroupItem(): BelongsTo
    {
        return $this->belongsTo(ReportGroupItem::class);
    }

    public function getSourceReportDisplayLabelAttribute(): string
    {
        return $this->reportGroup?->compact_label
            ?? $this->source_report_label
            ?? $this->source_report_tag
            ?? 'Unknown';
    }
}
