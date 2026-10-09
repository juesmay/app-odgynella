<?php

namespace App\Support;

use App\Models\Appointment;

/**
 * Calcula los espacios libres de la doctora. Una doctora no puede estar en dos
 * citas a la vez, así que los cruces se revisan en todas las sedes del consultorio.
 */
class Scheduler
{
    public const DAY_START = '08:00';

    public const DAY_END = '19:00';

    public const STEP = 30;

    /** @return list<array{0:int,1:int}> rangos ocupados en minutos */
    public static function busy(string $date, ?int $ignoreId = null): array
    {
        return Appointment::query()
            ->whereDate('date', $date)
            ->whereIn('status', Appointment::BLOCKING_STATUSES)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get(['start_time', 'end_time'])
            ->map(fn ($a) => [Time::toMinutes($a->start_time), Time::toMinutes($a->end_time)])
            ->all();
    }

    public static function isFree(string $date, string $start, int $duration, ?int $ignoreId = null): bool
    {
        $from = Time::toMinutes($start);
        $to = $from + $duration;

        foreach (self::busy($date, $ignoreId) as [$bFrom, $bTo]) {
            if ($from < $bTo && $to > $bFrom) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> horas de inicio libres ("HH:MM") */
    public static function freeSlots(string $date, int $duration): array
    {
        $busy = self::busy($date);
        $slots = [];
        $end = Time::toMinutes(self::DAY_END);

        for ($m = Time::toMinutes(self::DAY_START); $m + $duration <= $end; $m += self::STEP) {
            $free = true;
            foreach ($busy as [$bFrom, $bTo]) {
                if ($m < $bTo && $m + $duration > $bFrom) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                $slots[] = Time::fromMinutes($m);
            }
        }

        return $slots;
    }
}
