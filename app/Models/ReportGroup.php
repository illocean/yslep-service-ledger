<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportGroup extends Model
{
    protected $fillable = [
        'tag',
        'title',
        'obsidian_directory',
        'obsidian_index_note_path',
        'obsidian_last_synced_at',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReportGroupItem::class)->orderBy('served_on')->orderBy('source_order');
    }

    public function getDisplayLabelAttribute(): string
    {
        if (filled($this->title)) {
            return $this->title.' ('.$this->tag.')';
        }

        return $this->tag;
    }

    public function getCompactLabelAttribute(): string
    {
        return filled($this->title) ? $this->title : $this->tag;
    }

    public function itemsFor(string $indexType): Collection
    {
        return $this->items->where('index_type', $indexType)->values();
    }

    public function getRouteKeyName(): string
    {
        return 'tag';
    }

    protected function casts(): array
    {
        return [
            'obsidian_last_synced_at' => 'immutable_datetime',
        ];
    }
}
