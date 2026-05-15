<?php

namespace App\Models;

use App\Models\Concerns\HasDurationAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportGroupItem extends Model
{
    use HasDurationAttributes;

    protected $fillable = [
        'report_group_id',
        'index_type',
        'source_entry_id',
        'served_on',
        'time_start',
        'time_end',
        'cycle_code',
        'module_code',
        'title',
        'about',
        'source_order',
        'obsidian_record_uuid',
        'obsidian_note_path',
        'obsidian_note_hash',
        'obsidian_last_synced_at',
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
