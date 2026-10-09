<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Sede;
use App\Support\CurrentSede;
use Illuminate\View\View;

class TodayController extends Controller
{
    public function index(): View
    {
        $sede = CurrentSede::get();
        $user = auth()->user();
        $clinic = $user->clinic;

        $appointments = Appointment::with(['patient', 'service'])
            ->whereDate('date', today())
            ->where('sede_id', $sede?->id)
            ->orderBy('start_time')
            ->get();

        $count = fn (string ...$st) => $appointments->whereIn('status', $st)->count();

        $todaySedeId = $clinic->sedeIdForWeekday((int) now()->dayOfWeek);

        // Cotizaciones vigentes de prospectos que todavía no agendan: las que vencen primero arriba.
        $openQuotes = Quote::with('patient')
            ->where('status', 'vigente')
            ->whereDate('valid_until', '>=', today())
            ->whereHas('patient', fn ($q) => $q->where('stage', 'prospecto'))
            ->orderBy('valid_until')
            ->limit(6)
            ->get();

        return view('today', [
            'sede' => $sede,
            'appointments' => $appointments,
            'stats' => [
                'total' => $appointments->where('status', '!=', 'cancelada')->count(),
                'done' => $count('atendida'),
                'room' => $count('en_sala'),
                'pending' => $count('agendada', 'confirmada'),
                'confirmed' => $count('confirmada'),
            ],
            'collected' => (int) Payment::counted()->whereDate('paid_on', today())->where('sede_id', $sede?->id)->sum('amount'),
            'cashOpen' => $sede ? CashSession::openFor($sede->id) : null,
            'todaySede' => $todaySedeId ? Sede::find($todaySedeId) : null,
            'tomorrow' => Appointment::whereDate('date', today()->addDay())->whereIn('status', Appointment::OPEN_STATUSES)->count(),
            'openQuotes' => $openQuotes,
            'funnel' => $user->isDoctor() ? $this->funnel() : null,
        ]);
    }

    /**
     * Embudo de los últimos 90 días por origen: personas cotizadas, cuántas agendaron
     * valoración presencial y cuántas aceptaron tratamiento.
     *
     * @return array<string, array{quoted:int, booked:int, accepted:int}>
     */
    private function funnel(): array
    {
        $people = Patient::whereHas('quotes', fn ($q) => $q->where('historical', false)->whereDate('issued_on', '>=', today()->subDays(90)))
            ->get(['id', 'source', 'stage']);

        $rows = [];
        foreach ($people as $p) {
            $key = $p->source ?: 'Sin dato';
            $rows[$key] ??= ['quoted' => 0, 'booked' => 0, 'accepted' => 0];
            $rows[$key]['quoted']++;
            if (in_array($p->stage, ['agendado', 'en_tratamiento', 'terminado'], true)) {
                $rows[$key]['booked']++;
            }
            if (in_array($p->stage, ['en_tratamiento', 'terminado'], true)) {
                $rows[$key]['accepted']++;
            }
        }

        uasort($rows, fn ($a, $b) => $b['quoted'] <=> $a['quoted']);

        return $rows;
    }
}
