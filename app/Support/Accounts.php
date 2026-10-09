<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Quote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Cartera: lo que los pacientes deben de tratamientos ya realizados. */
class Accounts
{
    public const BUCKETS = [
        'al_dia' => ['Al día', '0 a 29 días', 'c-done'],
        '30' => ['30 días', '30 a 59 días', 'c-noshow'],
        '60' => ['60 días', '60 a 89 días', 'c-cancel'],
        '90' => ['90 o más', 'más de 90 días', 'c-alert'],
    ];

    public static function bucket(int $days): string
    {
        return match (true) {
            $days >= 90 => '90',
            $days >= 60 => '60',
            $days >= 30 => '30',
            default => 'al_dia',
        };
    }

    /**
     * Una fila por cotización aceptada con deuda, la más vieja primero.
     *
     * "Desde" es la fecha más reciente entre el último pago y el primer procedimiento realizado:
     * cada abono reinicia el conteo, y no se cuenta tiempo antes de que hubiera algo hecho.
     *
     * @return Collection<int, array{patient: Patient, quote: Quote, account: array, since: Carbon, days: int, bucket: string}>
     */
    public static function receivables(): Collection
    {
        return Quote::with(['patient', 'items', 'payments'])
            ->where('status', 'aceptada')
            ->whereHas('items', fn ($q) => $q->whereNotNull('done_at'))
            ->get()
            ->map(function (Quote $q) {
                $account = $q->account();
                if ($account['owed'] <= 0) {
                    return null;
                }

                $firstDone = $q->items->whereNotNull('done_at')->min('done_at');
                $lastPaid = $q->payments->whereNull('voided_at')->max('paid_on');
                $since = Carbon::parse($lastPaid && $lastPaid->gt($firstDone) ? $lastPaid : $firstDone)->startOfDay();
                $days = (int) $since->diffInDays(today());

                return [
                    'patient' => $q->patient,
                    'quote' => $q,
                    'account' => $account,
                    'since' => $since,
                    'days' => $days,
                    'bucket' => self::bucket($days),
                ];
            })
            ->filter()
            ->sortByDesc('days')
            ->values();
    }

    public static function reminder(Patient $patient, Quote $quote, int $owed, Clinic $clinic): string
    {
        return "Hola {$patient->firstName()}, te saludamos del consultorio de la {$clinic->doctor_name}. "
            .'Te recordamos que tienes un saldo pendiente de '.Time::money($owed)
            ." por los procedimientos ya realizados de tu tratamiento ({$quote->code()}). "
            ."Puedes pagarlo por {$clinic->payment_options}. "
            .'Si ya lo pagaste, cuéntanos para actualizarlo. ¡Gracias!';
    }

    /** Enlace para abrir el chat de WhatsApp con el mensaje escrito. Asume Colombia si el número tiene 10 dígitos. */
    public static function whatsappLink(Patient $patient, string $text): ?string
    {
        if (! $patient->phone) {
            return null;
        }
        $digits = Patient::phoneKey($patient->phone);
        if (strlen($digits) === 10) {
            $digits = '57'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}
