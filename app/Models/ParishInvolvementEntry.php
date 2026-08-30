<?php

namespace App\Models;

use App\Models\Concerns\HasDurationAttributes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParishInvolvementEntry extends Model
{
    use HasDurationAttributes;
    use SoftDeletes;

    protected $fillable = [
        'served_on',
        'time_start',
        'time_end',
        'source_order',
        'obsidian_record_uuid',
        'obsidian_content_hash',
        'obsidian_last_synced_at',
        'obsidian_last_source',
        'obsidian_conflict',
        'role_in_activity',
    ];

    protected function casts(): array
    {
        return [
            'served_on' => 'date',
            'obsidian_last_synced_at' => 'immutable_datetime',
        ];
    }

    public function scopeForMonth(Builder $query, CarbonInterface $month): Builder
    {
        return $query->whereBetween('served_on', [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ]);
    }
}
