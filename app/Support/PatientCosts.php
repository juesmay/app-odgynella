<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Patient;
use App\Models\User;

/** Costo de atender a cada paciente: horas de consultorio por cita y otros costos ligados a él. */
class PatientCosts
{
    /**
     * Al quedar "atendida", la cita registra su tiempo de consultorio: duración × tarifa por hora de la sede.
     * No hace nada si la sede no tiene tarifa o si ya se registró.
     */
    public static function recordRoomTime(Appointment $appointment, User $by): ?Expense
    {
        $appointment->loadMissing(['sede', 'service']);
        $rate = (int) ($appointment->sede?->hourly_rate ?? 0);
        $minutes = $appointment->durationMinutes();

        if ($appointment->status !== 'atendida' || $rate <= 0 || $minutes <= 0) {
            return null;
        }
        if (Expense::where('appointment_id', $appointment->id)->where('category', ExpenseCategory::ROOM_TIME)->whereNull('voided_at')->exists()) {
            return null;
        }

        $hours = round($minutes / 60, 2);

        return Expense::create([
            'clinic_id' => $appointment->clinic_id,
            'sede_id' => $appointment->sede_id,
            'type' => 'consultorio',
            'category' => ExpenseCategory::ROOM_TIME,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'hours' => $hours,
            'amount' => (int) round($rate * $minutes / 60),
            'method' => 'Por pagar al consultorio',
            'spent_on' => $appointment->date,
            'detail' => ($appointment->service->name ?? 'Cita').' · '.$appointment->start_time.' a '.$appointment->end_time,
            'created_by' => $by->id,
        ]);
    }

    /**
     * Lo que ha pagado el paciente, lo que ha costado atenderlo y lo que queda.
     *
     * @return array{paid: int, cost: int, profit: int, margin: ?int, room: int, other: int}
     */
    public static function summary(Patient $patient): array
    {
        $paid = (int) $patient->payments()->whereNull('voided_at')->sum('amount');
        $costs = Expense::where('patient_id', $patient->id)->whereNull('voided_at')->get(['category', 'amount']);
        $cost = (int) $costs->sum('amount');
        $room = (int) $costs->where('category', ExpenseCategory::ROOM_TIME)->sum('amount');

        return [
            'paid' => $paid,
            'cost' => $cost,
            'profit' => $paid - $cost,
            'margin' => $paid > 0 ? (int) round(($paid - $cost) * 100 / $paid) : null,
            'room' => $room,
            'other' => $cost - $room,
        ];
    }
}
