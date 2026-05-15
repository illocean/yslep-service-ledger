<?php

namespace App\Enums;

enum IndexScope: string
{
    case All = 'all';
    case Unsaved = 'unsaved';
    case Report = 'report';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Time',
            self::Unsaved => 'Unsaved Only',
            self::Report => 'Saved Report',
        };
    }

    public function isLive(): bool
    {
        return $this !== self::Report;
    }
}
