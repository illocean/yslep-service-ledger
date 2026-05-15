<?php

namespace App\Models;

use App\Models\Concerns\HasDurationAttributes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FormationEntry extends Model
{
    use HasDurationAttributes;

    protected $fillable = [
        'served_on',
        'cycle_code',
        'module_code',
        'title',
        'time_start',
        'time_end',
        'source_order',
        'obsidian_record_uuid',
    ];

    protected function casts(): array
    {
        return [
            'served_on' => 'date',
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
