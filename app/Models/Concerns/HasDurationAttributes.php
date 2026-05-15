<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasDurationAttributes
{
    protected function durationMinutes(): Attribute
    {
        return Attribute::get(function (mixed $value, array $attributes): int {
            $timeStart = $this->normalizeStoredTime($attributes['time_start'] ?? null);
            $timeEnd = $this->normalizeStoredTime($attributes['time_end'] ?? null);

            if ($timeStart === null || $timeEnd === null) {
                return 0;
            }

            $start = CarbonImmutable::createFromFormat('H:i:s', $timeStart, config('app.timezone'));
            $end = CarbonImmutable::createFromFormat('H:i:s', $timeEnd, config('app.timezone'));

            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            return $start->diffInMinutes($end);
        });
    }

    protected function durationHours(): Attribute
    {
        return Attribute::get(fn (): float => round($this->duration_minutes / 60, 2));
    }

    protected function durationLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $minutes = $this->duration_minutes;
            $hours = intdiv($minutes, 60);
            $remainingMinutes = $minutes % 60;

            if ($hours === 0) {
                return $remainingMinutes.' min';
            }

            if ($remainingMinutes === 0) {
                return $hours.' hr';
            }

            return sprintf('%d hr %02d min', $hours, $remainingMinutes);
        });
    }

    protected function servedOnLabel(): Attribute
    {
        return Attribute::get(function (mixed $value, array $attributes): ?string {
            if (blank($attributes['served_on'] ?? null)) {
                return null;
            }

            return CarbonImmutable::parse($attributes['served_on'], config('app.timezone'))->format('F j, Y');
        });
    }

    protected function timeStartLabel(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes): ?string => $this->formatStoredTime($attributes['time_start'] ?? null));
    }

    protected function timeEndLabel(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes): ?string => $this->formatStoredTime($attributes['time_end'] ?? null));
    }

    protected function normalizeStoredTime(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized.':00';
        }

        return $normalized;
    }

    protected function formatStoredTime(?string $value): ?string
    {
        $normalized = $this->normalizeStoredTime($value);

        if ($normalized === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat('H:i:s', $normalized, config('app.timezone'))->format('g:i A');
    }
}
