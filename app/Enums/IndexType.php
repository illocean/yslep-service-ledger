<?php

namespace App\Enums;

use App\Models\FormationEntry;
use App\Models\ParishInvolvementEntry;
use App\Models\SocialApostolateEntry;

enum IndexType: string
{
    case Formation = 'formation';
    case ParishInvolvement = 'parish_involvement';
    case SocialApostolate = 'social_apostolate';

    public function label(): string
    {
        return match ($this) {
            self::Formation => 'Formation',
            self::ParishInvolvement => 'Parish Involvement',
            self::SocialApostolate => 'Social Apostolate',
        };
    }

    public function cardTitle(): string
    {
        return match ($this) {
            self::Formation => 'Formation Index Card',
            self::ParishInvolvement => 'Parish Involvement Index Card',
            self::SocialApostolate => 'Social Apostolate Index Card',
        };
    }

    public function defaultFileName(): string
    {
        return match ($this) {
            self::Formation => 'FORMATION.md',
            self::ParishInvolvement => 'PARISH INVOLVEMENT.md',
            self::SocialApostolate => 'SOCIAL APOSTOLATE.md',
        };
    }

    public function modelClass(): string
    {
        return match ($this) {
            self::Formation => FormationEntry::class,
            self::ParishInvolvement => ParishInvolvementEntry::class,
            self::SocialApostolate => SocialApostolateEntry::class,
        };
    }

    public static function fromRouteValue(string $value): self
    {
        $normalized = str($value)
            ->lower()
            ->replace(['%20', '-', ' '], '_')
            ->value();

        return match ($normalized) {
            self::Formation->value => self::Formation,
            self::ParishInvolvement->value,
            'parishinvolvement' => self::ParishInvolvement,
            self::SocialApostolate->value,
            'socialapostolate' => self::SocialApostolate,
            default => throw new \ValueError("Unknown index type [{$value}]."),
        };
    }
}
