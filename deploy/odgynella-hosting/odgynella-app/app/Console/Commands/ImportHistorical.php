<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Models\Expense;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Sede;
use App\Models\User;
use App\Support\Time;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carga la información histórica (antes de empezar a usar el sistema) desde historico.json.
 *
 *   php artisan historico:importar
 *   php artisan historico:importar ruta/al/historico.json --sede=Envigado
 *
 * Todo queda marcado como histórico: aparece en la cuenta de cada paciente y en la cartera,
 * pero no entra a Reportes, caja ni al archivo del contador, y no gasta números de recibo ni de cotización.
 */
class ImportHistorical extends Command
{
    protected $signature = 'historico:importar {archivo? : Ruta del JSON (por defecto storage/app/private/importacion/historico.json)}
                            {--sede=Envigado : Sede por defecto para pagos y alquiler de consultorio}';

    protected $description = 'Importa pacientes, tratamientos, pagos y gastos históricos';

    public function handle(): int
    {
        $path = $this->argument('archivo') ?: storage_path('app/private/importacion/historico.json');
        if (! is_file($path)) {
            $this->error("No encuentro el archivo: {$path}");

            return self::FAILURE;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['patients'], $data['plans'], $data['payments'], $data['expenses'])) {
            $this->error('El archivo no tiene el formato esperado.');

            return self::FAILURE;
        }

        $clinic = Clinic::orderBy('id')->firstOrFail();
        if (Patient::where('clinic_id', $clinic->id)->where('historical', true)->exists()) {
            $this->error('El histórico ya está importado. No se carga dos veces.');

            return self::FAILURE;
        }
        if (Patient::where('clinic_id', $clinic->id)->where('name', 'like', '%(ejemplo)%')->exists()) {
            $this->warn('Hay datos de ejemplo cargados. Lo normal es correr primero: php artisan migrate:fresh --seed');
            if (! $this->confirm('¿Importar de todas formas?')) {
                return self::FAILURE;
            }
        }

        $doctor = User::where('clinic_id', $clinic->id)->where('role', 'doctora')->orderBy('id')->firstOrFail();
        $sedes = Sede::where('clinic_id', $clinic->id)->pluck('id', 'name');
        $defaultSede = $sedes[$this->option('sede')] ?? $sedes->first();

        DB::transaction(function () use ($data, $clinic, $doctor, $sedes, $defaultSede) {
            $patients = [];
            foreach ($data['patients'] as $p) {
                $created = Carbon::parse($p['first_date'])->setTime(9, 0);
                $patient = new Patient([
                    'clinic_id' => $clinic->id, 'name' => $p['name'], 'phone' => null, 'phone_key' => null,
                    'stage' => $p['stage'], 'stage_changed_at' => $created, 'historical' => true, 'notes' => $p['notes'],
                    'created_by' => $doctor->id,
                ]);
                $patient->created_at = $created;
                $patient->save();
                $patients[$p['ref']] = $patient;
            }

            $plans = [];
            foreach ($data['plans'] as $pl) {
                $q = Quote::create([
                    'clinic_id' => $clinic->id, 'patient_id' => $patients[$pl['patient']]->id, 'number' => null,
                    'historical' => true, 'legacy_ref' => $pl['ref'], 'issued_on' => $pl['date'], 'valid_until' => $pl['date'],
                    'subtotal' => $pl['total'], 'discount_amount' => 0, 'total' => $pl['total'], 'status' => 'aceptada',
                    'internal_notes' => $pl['internal_notes'], 'created_by' => $doctor->id,
                ]);
                foreach ($pl['items'] as $i => $it) {
                    $q->items()->create([
                        'description' => mb_substr($it['description'], 0, 200), 'quantity' => 1, 'unit_price' => $it['amount'],
                        'line_total' => $it['amount'], 'position' => $i,
                        'done_at' => $it['done'] ? Carbon::parse($it['done_date'])->setTime(12, 0) : null,
                        'done_by' => $it['done'] ? $doctor->id : null,
                    ]);
                }
                $plans[$pl['ref']] = $q;
            }

            foreach ($data['payments'] as $pg) {
                Payment::create([
                    'clinic_id' => $clinic->id, 'patient_id' => $patients[$pg['patient']]->id, 'quote_id' => $plans[$pg['plan']]->id ?? null,
                    'sede_id' => ($pg['sede'] ?? null) ? ($sedes[$pg['sede']] ?? $defaultSede) : $defaultSede,
                    'receipt_number' => null, 'historical' => true, 'legacy_ref' => $pg['ref'], 'paid_on' => $pg['date'],
                    'amount' => $pg['amount'], 'method' => $pg['method'], 'concept' => mb_substr($pg['concept'], 0, 200),
                    'created_by' => $doctor->id,
                ]);
            }

            foreach ($data['expenses'] as $e) {
                Expense::create([
                    'clinic_id' => $clinic->id, 'sede_id' => $e['sede'] ? ($sedes[$e['sede']] ?? $defaultSede) : null,
                    'spent_on' => $e['date'], 'category' => $e['category'], 'amount' => $e['amount'], 'method' => 'Sin dato',
                    'detail' => $e['detail'], 'supplier' => null, 'historical' => true, 'legacy_ref' => $e['ref'], 'created_by' => $doctor->id,
                ]);
            }
        });

        $paid = array_sum(array_column($data['payments'], 'amount'));
        $total = array_sum(array_column($data['plans'], 'total'));
        $this->info('Histórico importado.');
        $this->table(['', 'Cantidad', 'Valor'], [
            ['Pacientes', count($data['patients']), ''],
            ['Tratamientos', count($data['plans']), Time::money($total)],
            ['Pagos', count($data['payments']), Time::money($paid)],
            ['Saldo de los tratamientos', '', Time::money($total - $paid)],
            ['Gastos', count($data['expenses']), Time::money(array_sum(array_column($data['expenses'], 'amount')))],
        ]);
        $this->line('Nada de esto entra a Reportes, caja ni al archivo del contador. Los saldos sí aparecen en Cartera.');

        return self::SUCCESS;
    }
}
