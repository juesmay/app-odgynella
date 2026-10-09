<?php

namespace App\Support;

class Time
{
    public static function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** "14:30" => "2:30 p.m." */
    public static function human(string $hhmm): string
    {
        $m = self::toMinutes($hhmm);
        $h = intdiv($m, 60);
        $suffix = $h < 12 ? 'a.m.' : 'p.m.';
        $h12 = $h % 12 ?: 12;

        return sprintf('%d:%02d %s', $h12, $m % 60, $suffix);
    }

    public static function money(int|float|null $amount): string
    {
        return '$'.number_format((float) $amount, 0, ',', '.');
    }

    /** Convierte "1.250.000" o "$ 1250000" en 1250000. */
    public static function parseMoney(mixed $value): int
    {
        return (int) preg_replace('/\D+/', '', (string) $value);
    }
}
