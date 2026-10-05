<?php

namespace App\Models;

use App\Models\Concerns\HasDurationAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportGroupItem extends Model
{
    use HasDurationAttributes;
    use SoftDeletes;

    protected $fillable = [
        'report_group_id',
        'index_type',
        'source_entry_id',
        'served_on',
        'academic_year',
        'time_start',
        'time_end',
        'cycle_code',
        'module_code',
        'title',
        'about',
        'role_in_activity',
        'source_order',
        'obsidian_record_uuid',
        'obsidian_note_path',
        'obsidian_note_hash',
        'obsidian_last_synced_at',
        'obsidian_conflict',
    ];

    protected function casts(): array
    {
        return [
            'served_on' => 'date',
            'source_entry_id' => 'integer',
            'obsidian_last_synced_at' => 'immutable_datetime',
        ];
    }

    public function reportGroup(): BelongsTo
    {
        return $this->belongsTo(ReportGroup::class);
    }
}
