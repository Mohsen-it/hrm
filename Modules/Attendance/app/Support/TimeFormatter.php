<?php

namespace Modules\Attendance\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * TimeFormatter — small helper that produces stable, locale-friendly strings
 * for the minutes / times the Attendance module deals with.
 *
 * Centralising the formatting here means the Vue side (which relies on the
 * shared `useTranslations` composable) can render a uniform presentation
 * without each page re-implementing the same conversion logic.
 */
class TimeFormatter
{
    /**
     * Format an integer number of minutes as `H:MM` (e.g. 95 → "1:35").
     *
     * Negative inputs are clamped to zero — the Attendance domain only
     * produces non-negative values, and the helper is a pure presentational
     * layer.
     */
    public static function minutesToHourMinute(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return sprintf('%d:%02d', $h, $m);
    }

    /**
     * Format an integer number of minutes as `Xh Ym` (e.g. 95 → "1h 35m").
     *
     * Used by dashboards and notifications that want a more verbose label.
     */
    public static function minutesToHuman(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h === 0) {
            return sprintf('%dm', $m);
        }

        if ($m === 0) {
            return sprintf('%dh', $h);
        }

        return sprintf('%dh %dm', $h, $m);
    }

    /**
     * Format a date-time (or `H:i:s` / `H:i` string) as `H:i`.
     *
     * Used by the Vue pages to render expected shift slots (e.g. "08:30").
     */
    public static function timeOf(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        $value = (string) $value;

        if (preg_match('/^(\d{2}:\d{2})(:\d{2})?$/', $value, $m) === 1) {
            return $m[1];
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $value);

        return $parsed ? $parsed->format('H:i') : null;
    }

    /**
     * Format a date-time (or string) as `Y-m-d` — i.e. the calendar date.
     *
     * Returns null for unparseable input.
     */
    public static function dateOf(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $parsed ? $parsed->format('Y-m-d') : null;
    }

    /**
     * Format a date-time as a full `Y-m-d H:i:s` string.
     */
    public static function dateTimeOf(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $parsed ? $parsed->format('Y-m-d H:i:s') : null;
    }
}
