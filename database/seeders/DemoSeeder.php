<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Evolution;
use App\Models\Expense;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Photo;
use App\Models\Quote;
use App\Models\RadiologyOrder;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use App\Support\Time;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Datos de EJEMPLO para recorrer todo el sistema. Todo lo que crea dice "(ejemplo)".
 * No usar con pacientes reales. Para empezar limpio después:  php artisan migrate:fresh --seed
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private Clinic $clinic;

    private User $doctor;

    private User $assistant;

    /** @var array<string, Sede> */
    private array $sedes = [];

    /** @var array<string, Service> */
    private array $services = [];

    private string $signature;

    public function run(): void
    {
        $this->clinic = Clinic::firstOrFail();

        if (Patient::where('clinic_id', $this->clinic->id)->where('name', 'like', '%(ejemplo)%')->exists()) {
            throw new RuntimeException('Los datos de ejemplo ya están cargados. Para cargarlos de nuevo: php artisan migrate:fresh --seed y luego este comando.');
        }

        $this->doctor = User::where('clinic_id', $this->clinic->id)->where('role', 'doctora')->firstOrFail();
        $this->assistant = User::where('clinic_id', $this->clinic->id)->where('role', 'asistente')->firstOrFail();
        $this->sedes = Sede::where('clinic_id', $this->clinic->id)->get()->keyBy('name')->all();
        $this->services = Service::where('clinic_id', $this->clinic->id)->get()->keyBy('name')->all();
        $this->signature = 'data:image/png;base64,'.base64_encode(file_get_contents(database_path('seeders/assets/demo-firma.png')));

        // En qué sede atiende cada día (0 = domingo).
        $s = fn ($n) => $this->sedes[$n]->id;
        $this->clinic->update(['schedule' => [
            '0' => null, '1' => $s('Envigado'), '2' => $s('Sabaneta'), '3' => $s('Envigado'),
            '4' => $s('Sabaneta'), '5' => $s('Envigado'), '6' => $s('Bucaramanga'),
        ]]);

        $this->patients();
        $this->expenses();
    }

    // ------------------------------------------------------------------
    // Pacientes, cada uno muestra una parte del sistema
    // ------------------------------------------------------------------

    private function patients(): void
    {
        $today = $this->sedeToday();

        // 1. En tratamiento con todo: historia, odontograma, evoluciones, fotos, consentimiento, orden. Debe hace 30+ días.
        $maria = $this->person('María Fernanda Ríos', '3001110001', 'Facebook', 'en_tratamiento', 80, [
            'birth_date' => '1990-04-12', 'email' => 'mariaf.ejemplo@correo.com',
            'exam' => ['blandos' => 'Sin alteraciones', 'periodontal' => 'Gingivitis leve generalizada', 'oclusion' => 'Clase I', 'atm' => 'Sin ruidos ni dolor', 'higiene' => 'Regular', 'otros' => ''],
            'family_history' => ['conditions' => ['Diabetes'], 'detail' => 'Madre con diabetes tipo 2'],
        ]);
        $q = $this->plan($maria, ['Prótesis', 'Microdiseño'], 150000, 70, 'Promoción de temporada');
        $this->done($q, 0, 45);
        $this->pay($maria, $q, 500000, 50, 'Transferencia');
        $this->diagnose($maria, [['K02.1', 'Caries de la dentina', '36'], ['K05.1', 'Gingivitis crónica', null], ['K08.1', 'Pérdida de dientes por extracción', '46']]);
        $this->teeth($maria, [
            36 => [[['type' => 'caries', 'surfaces' => ['O', 'D']]], 'Caries ocluso distal profunda'],
            46 => [[['type' => 'ausente', 'surfaces' => []]], 'Perdido hace 5 años'],
            11 => [[['type' => 'obturacion', 'surfaces' => ['M']], ['type' => 'fractura', 'surfaces' => ['O']]], 'Fractura del borde incisal'],
            21 => [[['type' => 'obturacion', 'surfaces' => ['M', 'V']]], null],
            26 => [[['type' => 'endodoncia', 'surfaces' => []], ['type' => 'corona', 'surfaces' => []]], 'Corona en buen estado'],
        ]);
        $this->evolution($maria, 'valoracion', 75, [
            'motivo' => 'Quiere mejorar la sonrisa y reponer el molar que le falta.',
            'hallazgos' => 'Ausencia del 46, caries en 36, fractura incisal del 11. Encías levemente inflamadas.',
            'diagnostico' => "K02.1 Caries de la dentina (36)\nK05.1 Gingivitis crónica\nK08.1 Pérdida de dientes (46)",
            'plan' => 'Prótesis para el 46 y microdiseño de sonrisa en el sector anterior.',
        ]);
        $this->evolution($maria, 'sesion', 45, [
            'procedimiento' => 'Toma de impresiones y prueba de la prótesis del 46.',
            'anestesia' => 'No requirió',
            'evolucion' => 'Paciente tolera bien. Ajuste de oclusión.',
            'indicaciones' => 'Evitar alimentos duros por 48 horas.',
            'proxima' => 'Microdiseño en 3 semanas',
        ], ['Prótesis']);
        $this->photo($maria, 'antes', 75, 'demo-antes.jpg', 'Sonrisa al llegar');
        $this->photo($maria, 'despues', 40, 'demo-despues.jpg', 'Después de la prótesis');
        $this->consent($maria, 74);
        $this->order($maria, 76, [['Radiografía panorámica', null, 1], ['Radiografía periapical', '36, 46', 2]], 'Valoración para plan de rehabilitación.', 'correo_doctora');
        $this->appointment($maria, 'Microdiseño', $today, '16:00', 'confirmada');

        // 2. Valoración presencial hoy, en sala. Alergia: sale la alerta roja.
        $laura = $this->person('Laura Gómez Castaño', '3001110002', 'Instagram', 'agendado', $this->r(6), [
            'birth_date' => '1995-09-30', 'anamnesis' => ['alergia' => true], 'alert_text' => 'Alergia a la penicilina',
        ]);
        $this->quote($laura, [['Aclaramiento', null, 1], ['Limpieza', null, 1]], 'vigente', $this->r(6));
        $this->appointment($laura, 'Valoración presencial', $today, '10:00', 'en_sala');
        $this->order($laura, 0, [['Radiografía panorámica', null, 1]], 'Valoración inicial.', 'paciente_recoge');

        // 3. En tratamiento, debe poco y reciente (al día). Toma anticoagulantes.
        $carolina = $this->person('Carolina Restrepo', '3001110003', 'Referido', 'en_tratamiento', 30, [
            'birth_date' => '1982-01-20', 'anamnesis' => ['anticoagulantes' => true, 'hipertension' => true],
        ]);
        $q = $this->plan($carolina, ['Aclaramiento', 'Limpieza'], 0, 25);
        $this->done($q, 1, $this->r(6));
        $this->done($q, 0, $this->r(4));
        $this->pay($carolina, null, 30000, 26, 'Efectivo', 'Valoración');
        $this->pay($carolina, $q, 200000, $this->r(4), 'Nequi');
        $this->appointment($carolina, 'Control', $today, '11:00', 'confirmada');

        // 4. Turismo dental: Canadá, pasaporte, valoración hoy.
        $jennifer = $this->person('Jennifer Smith', '14165550104', 'Google', 'agendado', $this->r(3), [
            'doc_type' => 'Pasaporte', 'doc_number' => 'AB1234567', 'city' => 'Toronto, Canadá', 'email' => 'jennifer.example@mail.com',
        ]);
        $this->quote($jennifer, [['Diseño de sonrisa completo', '14 a 24', 1], ['Limpieza', null, 1]], 'vigente', $this->r(3));
        $this->appointment($jennifer, 'Valoración presencial', $today, '14:00', 'agendada');

        // 5. Prospecto recién cotizado en la asesoría virtual (sin documento todavía).
        $valentina = $this->person('Valentina Ochoa', '3001110005', 'Instagram', 'prospecto', 2, ['doc_type' => null, 'doc_number' => null, 'data_consent_at' => null, 'city' => 'Sabaneta']);
        $this->quote($valentina, [['Diseño de sonrisa completo', '13 a 23', 1], ['Limpieza', null, 1]], 'vigente', 2,
            internal: 'Quiere dientes más blancos y parejos para su matrimonio en marzo.');

        // 6. Prospecto con endodoncia de "valor según el caso".
        $camilo = $this->person('Camilo Herrera', '3001110006', 'Facebook', 'prospecto', 5, ['doc_type' => null, 'doc_number' => null, 'data_consent_at' => null]);
        $this->quote($camilo, [
            ['Paquete de diseño de sonrisa (limpieza profunda, aclaramiento y diseño en resina de alta estética)', null, 1, 1800000],
            ['Corona sobre implante en zirconio', null, 1, 1800000],
            ['Endodoncia', null, 1, 0, 'Uniradicular $400.000, Biradicular $450.000, Multiradicular $700.000'],
        ], 'vigente', 5);

        // 7. Terminado y pagado completo. Fotos antes y después, control.
        $andres = $this->person('Andrés Felipe Zapata', '3001110007', 'Referido', 'terminado', 60, ['birth_date' => '1988-07-03']);
        $q = $this->plan($andres, ['Limpieza', 'Aclaramiento'], 15000, 55, 'Descuento por referido');
        $this->done($q, 0, 50);
        $this->done($q, 1, $this->r(5));
        $this->pay($andres, $q, 250000, today()->day + 12, 'Tarjeta (datáfono)');
        $this->pay($andres, $q, 235000, $this->r(5), 'Transferencia');
        $this->evolution($andres, 'control', $this->r(2), ['evolucion' => 'Color estable, sin sensibilidad.', 'indicaciones' => 'Evitar café y vino tinto las primeras semanas.', 'proxima' => 'Control en 6 meses']);
        $this->photo($andres, 'antes', 55, 'demo-antes.jpg', null);
        $this->photo($andres, 'despues', $this->r(5), 'demo-despues.jpg', 'Resultado final');

        // 8. Debe hace más de 90 días. Antecedente oncológico e infeccioso (este último solo lo ve la doctora).
        $sandra = $this->person('Sandra Milena Arango', '3001110008', 'Facebook', 'en_tratamiento', 140, [
            'birth_date' => '1975-11-02', 'infectious' => ['Hepatitis B'],
            'oncology' => ['status' => 'previo', 'ended_on' => '2023-05-10', 'treatments' => ['quimioterapia']],
        ]);
        $q = $this->plan($sandra, ['Diseño de sonrisa completo', 'Limpieza'], 140000, 130, 'Promoción');
        $this->done($q, 1, 125);
        $this->done($q, 0, 120);
        $this->pay($sandra, $q, 1000000, 110, 'Transferencia');

        // 9. Debe hace unos 60 días.
        $diego = $this->person('Diego Alejandro Torres', '3001110009', 'Google', 'en_tratamiento', 90, ['birth_date' => '1992-02-14']);
        $q = $this->plan($diego, ['Prótesis'], 0, 85);
        $this->done($q, 0, 70);
        $this->pay($diego, $q, 600000, 65, 'Daviplata');

        // 10. No continuó (precio). Cotización no aceptada.
        $paula = $this->person('Paula Andrea Cardona', '3001110010', 'Facebook', 'no_continuo', 40, ['lost_reason' => 'Precio']);
        $this->quote($paula, [['Diseño de sonrisa completo', null, 1]], 'no_aceptada', 40);
        $this->pay($paula, null, 30000, today()->day + 8, 'Efectivo', 'Valoración');

        // 13. Aceptó este mes y dejó un anticipo (pagó antes de que se hiciera el tratamiento).
        $natalia = $this->person('Natalia Vélez', '3001110013', 'Instagram', 'en_tratamiento', $this->r(5), ['birth_date' => '1998-05-21', 'email' => 'natalia.ejemplo@correo.com']);
        $q = $this->plan($natalia, ['Limpieza', 'Diseño de sonrisa completo'], 0, $this->r(5));
        $this->done($q, 0, $this->r(1));
        $this->pay($natalia, $q, 800000, $this->r(1), 'Transferencia');
        $this->appointment($natalia, 'Diseño de sonrisa completo', today()->addDays(3), '10:00', 'agendada');

        // 11. Cotización vencida, sigue como prospecto.
        $daniela = $this->person('Daniela Ospina', '3001110011', 'Instagram', 'prospecto', 45, ['doc_type' => null, 'doc_number' => null, 'data_consent_at' => null]);
        $this->quote($daniela, [['Aclaramiento', null, 1]], 'vigente', 45);

        // 12. No vino a su cita la semana pasada y reagendó para mañana.
        $santiago = $this->person('Santiago Mejía', '3001110012', 'Google', 'agendado', 15, ['birth_date' => '2000-12-01']);
        $this->appointment($santiago, 'Valoración presencial', $this->pastWeekday(6), '09:00', 'no_vino');
        $this->appointment($santiago, 'Valoración presencial', today()->addDay(), '09:00', 'agendada');

        // 14. Niño de 7 años: odontograma de dentición mixta (permanentes y temporales).
        $samuel = $this->person('Samuel Ríos', '3001110014', 'Referido', 'en_tratamiento', 20, [
            'birth_date' => today()->subYears(7)->subMonths(3)->toDateString(), 'doc_type' => 'T.I.', 'doc_number' => '1034998877',
        ]);
        $this->teeth($samuel, [
            16 => [[['type' => 'sellante', 'surfaces' => ['O']]], null],
            26 => [[['type' => 'sellante', 'surfaces' => ['O']]], null],
            36 => [[['type' => 'mancha_blanca', 'surfaces' => ['O']]], 'Controlar en 3 meses'],
            46 => [[['type' => 'sellante', 'surfaces' => ['O']]], null],
            11 => [[['type' => 'erupcion', 'surfaces' => []]], null],
            21 => [[['type' => 'sin_erupcionar', 'surfaces' => []]], null],
            51 => [[['type' => 'ausente', 'surfaces' => []]], 'Exfoliado'],
            61 => [[['type' => 'movilidad', 'surfaces' => []]], 'Próximo a exfoliar'],
            54 => [[['type' => 'caries', 'surfaces' => ['O', 'D']]], null],
            74 => [[['type' => 'caries', 'surfaces' => ['O']]], null],
            84 => [[['type' => 'pulpotomia', 'surfaces' => []], ['type' => 'corona_acero', 'surfaces' => []]], 'Pulpotomía y corona de acero'],
            85 => [[['type' => 'extraccion', 'surfaces' => []]], 'Absceso, extraer y poner mantenedor'],
        ]);
        $this->diagnose($samuel, [['K02.1', 'Caries de la dentina', '54, 74'], ['K04.7', 'Absceso periapical sin fístula', '85']]);
        $this->evolution($samuel, 'valoracion', 20, [
            'motivo' => 'Control de rutina. La mamá nota un hueco en una muela.',
            'hallazgos' => 'Dentición mixta. Caries en 54 y 74, absceso en 85, mancha blanca en 36.',
            'diagnostico' => "K02.1 Caries de la dentina (54, 74)\nK04.7 Absceso periapical sin fístula (85)",
            'plan' => 'Resinas en 54 y 74, extracción del 85 con mantenedor de espacio, sellantes en molares permanentes.',
        ]);
        $this->appointment($samuel, 'Control', today()->addDays(2), '15:00', 'agendada');

        // 15. Niña de 4 años: odontograma solo de temporales.
        $sofia = $this->person('Sofía Ríos', '3001110015', 'Referido', 'agendado', 20, [
            'birth_date' => today()->subYears(4)->subMonths(2)->toDateString(), 'doc_type' => 'R.C.', 'doc_number' => '1034998878',
        ]);
        $this->teeth($sofia, [
            52 => [[['type' => 'mancha_blanca', 'surfaces' => ['V']]], null],
            62 => [[['type' => 'mancha_blanca', 'surfaces' => ['V']]], null],
            75 => [[['type' => 'caries', 'surfaces' => ['O', 'M']]], null],
            65 => [[['type' => 'sellante', 'surfaces' => ['O']]], null],
        ]);

        // Citas atendidas en días anteriores (alimentan reportes e inasistencias).
        foreach ([[$carolina, 'Limpieza', $this->r(6)], [$carolina, 'Aclaramiento', $this->r(4)], [$andres, 'Control', $this->r(2)], [$diego, 'Control', 3], [$natalia, 'Limpieza', $this->r(1)]] as [$p, $svc, $days]) {
            $this->appointment($p, $svc, $this->pastWeekday($days), '15:00', 'atendida');
        }
        $this->appointment($maria, 'Control', today()->addDays(2), '08:30', 'agendada');

        // Valoraciones cobradas y cierres de caja de días anteriores.
        $this->pay($santiago, null, 30000, 6, 'Efectivo', 'Valoración');
        $this->pay($jennifer, null, 30000, 4, 'Efectivo', 'Valoración (abono de reserva)');
        $this->closeCashDays();
    }

    // ------------------------------------------------------------------
    // Gastos de este mes y del anterior (para comparar en Reportes)
    // ------------------------------------------------------------------

    private function expenses(): void
    {
        $env = $this->sedes['Envigado']->id;
        $sab = $this->sedes['Sabaneta']->id;
        $thisMonth = [
            ['Arriendo', 2800000, 'Arriendo consultorio Envigado', $env, 1],
            ['Arriendo', 1200000, 'Arriendo consultorio Sabaneta', $sab, 1],
            ['Nómina', 1600000, 'Pago asistente primera quincena', null, 2],
            ['Materiales e insumos', 420000, 'Resinas, anestesia y guantes', $env, 3],
            ['Laboratorio dental', 650000, 'Coronas en zirconio', $env, 4],
            ['Publicidad', 300000, 'Pauta en Instagram', null, 5],
            ['Servicios públicos', 185000, 'Energía y agua', $env, 6],
        ];
        foreach ($thisMonth as [$cat, $amount, $detail, $sede, $day]) {
            $this->expense($cat, $amount, $detail, $sede, $this->thisMonth($day));
        }

        $prev = today()->startOfMonth()->subMonth();
        foreach ([
            ['Arriendo', 2800000, 'Arriendo consultorio Envigado', $env, 1],
            ['Arriendo', 1200000, 'Arriendo consultorio Sabaneta', $sab, 1],
            ['Nómina', 3200000, 'Pago asistente', null, 28],
            ['Materiales e insumos', 610000, 'Insumos del mes', $env, 10],
            ['Publicidad', 450000, 'Pauta en Facebook e Instagram', null, 5],
            ['Software', 120000, 'Suscripciones', null, 15],
        ] as [$cat, $amount, $detail, $sede, $day]) {
            $this->expense($cat, $amount, $detail, $sede, $prev->copy()->addDays($day - 1));
        }
    }

    // ------------------------------------------------------------------
    // Ayudantes
    // ------------------------------------------------------------------

    private function person(string $name, string $phone, string $source, string $stage, int $daysAgo, array $extra = []): Patient
    {
        $created = now()->subDays($daysAgo)->setTime(10, 0);

        $p = Patient::create(array_merge([
            'clinic_id' => $this->clinic->id, 'name' => $name.' (ejemplo)',
            'doc_type' => 'C.C.', 'doc_number' => (string) (1000000000 + (int) substr($phone, -4) * 137),
            'phone' => $phone, 'phone_key' => Patient::phoneKey($phone), 'city' => 'Medellín', 'source' => $source,
            'data_consent_at' => $created, 'photo_consent' => true, 'stage' => $stage, 'stage_changed_at' => $created,
            'created_by' => $this->assistant->id,
        ], $extra));
        $p->forceFill(['created_at' => $created, 'updated_at' => $created])->save();

        return $p;
    }

    private function quote(Patient $p, array $rows, string $status, int $daysAgo, ?string $internal = null): Quote
    {
        $this->clinic->refresh();
        $items = [];
        foreach ($rows as $i => $r) {
            [$name, $teeth, $qty] = $r;
            $svc = $this->services[$name] ?? null;
            $price = $r[3] ?? ($svc?->price ?? 0);
            $items[] = [
                'service_id' => $svc?->id, 'description' => $name, 'teeth' => $teeth, 'quantity' => $qty,
                'unit_price' => $price, 'line_total' => $price * $qty, 'price_options' => $r[4] ?? null, 'position' => $i,
            ];
        }
        $subtotal = array_sum(array_column($items, 'line_total'));
        $issued = today()->subDays($daysAgo);

        $q = Quote::create([
            'clinic_id' => $this->clinic->id, 'patient_id' => $p->id, 'number' => $this->clinic->next_quote,
            'issued_on' => $issued, 'valid_until' => $issued->copy()->addDays($this->clinic->quote_validity_days ?: 30),
            'subtotal' => $subtotal, 'discount_amount' => 0, 'total' => $subtotal, 'status' => $status,
            'internal_notes' => $internal, 'created_by' => $this->doctor->id,
        ]);
        $q->items()->createMany($items);
        $this->clinic->increment('next_quote');

        return $q;
    }

    /** Cotización aceptada (plan de tratamiento) con descuento opcional. */
    private function plan(Patient $p, array $services, int $discount, int $daysAgo, ?string $label = null): Quote
    {
        $q = $this->quote($p, array_map(fn ($s) => [$s, null, 1], $services), 'aceptada', $daysAgo);
        if ($discount) {
            $q->update(['discount_amount' => $discount, 'discount_label' => $label ?? 'Descuento', 'total' => $q->subtotal - $discount]);
        }

        return $q->fresh('items');
    }

    private function done(Quote $q, int $position, int $daysAgo): void
    {
        $q->items()->where('position', $position)->update(['done_at' => now()->subDays($daysAgo)->setTime(11, 0), 'done_by' => $this->doctor->id]);
    }

    private function pay(Patient $p, ?Quote $q, int $amount, int $daysAgo, string $method, ?string $concept = null): void
    {
        $this->clinic->refresh();
        Payment::create([
            'clinic_id' => $this->clinic->id, 'patient_id' => $p->id, 'quote_id' => $q?->id,
            'sede_id' => $this->sedes['Envigado']->id, 'receipt_number' => $this->clinic->next_receipt,
            'paid_on' => today()->subDays($daysAgo), 'amount' => $amount, 'method' => $method,
            'concept' => $concept ?? 'Abono a tratamiento '.$q->code(), 'created_by' => $this->assistant->id,
        ]);
        $this->clinic->increment('next_receipt');
    }

    private function appointment(Patient $p, string $service, Carbon $date, string $time, string $status): void
    {
        $svc = $this->services[$service];
        $sedeId = $this->clinic->sedeIdForWeekday((int) $date->dayOfWeek) ?? $this->sedes['Envigado']->id;
        Appointment::create([
            'clinic_id' => $this->clinic->id, 'patient_id' => $p->id, 'sede_id' => $sedeId, 'service_id' => $svc->id,
            'date' => $date->toDateString(), 'start_time' => $time,
            'end_time' => Time::fromMinutes(Time::toMinutes($time) + max(30, $svc->duration_min)), 'status' => $status,
            'created_by' => $this->assistant->id,
        ]);
    }

    private function diagnose(Patient $p, array $rows): void
    {
        foreach ($rows as [$code, $name, $tooth]) {
            $p->diagnoses()->create(['code' => $code, 'name' => $name, 'tooth' => $tooth, 'created_by' => $this->doctor->id]);
        }
    }

    private function teeth(Patient $p, array $teeth): void
    {
        foreach ($teeth as $tooth => [$findings, $note]) {
            $p->teeth()->create(['tooth' => $tooth, 'findings' => $findings, 'note' => $note, 'updated_by' => $this->doctor->id]);
        }
    }

    private function evolution(Patient $p, string $type, int $daysAgo, array $fields, array $procedures = []): void
    {
        Evolution::create([
            'clinic_id' => $this->clinic->id, 'patient_id' => $p->id, 'type' => $type, 'fields' => $fields,
            'procedures' => $procedures ?: null, 'signed_by' => $this->doctor->id, 'signed_name' => $this->clinic->doctor_name,
            'signed_at' => now()->subDays($daysAgo)->setTime(11, 30),
        ]);
    }

    private function photo(Patient $p, string $stage, int $daysAgo, string $asset, ?string $note): void
    {
        $path = 'photos/'.$this->clinic->id.'/'.$p->id.'/'.$stage.'-'.$p->id.'.jpg';
        Storage::disk('local')->put($path, file_get_contents(database_path('seeders/assets/'.$asset)));
        Photo::create([
            'clinic_id' => $this->clinic->id, 'patient_id' => $p->id, 'path' => $path, 'stage' => $stage,
            'taken_on' => today()->subDays($daysAgo), 'note' => $note, 'uploaded_by' => $this->doctor->id,
        ]);
    }

    private function consent(Patient $p, int $daysAgo): void
    {
        $t = ConsentTemplate::where('clinic_id', $this->clinic->id)->orderBy('id')->first();
        if (! $t) {
            return;
        }
        Consent::create([
            'clinic_id' => $this->clinic->id, 'patient_id' => $p->id, 'consent_template_id' => $t->id,
            'title' => $t->name, 'body' => $t->render($p, $this->clinic->doctor_name),
            'patient_signature' => $this->signature, 'doctor_signature' => $this->signature,
            'doctor_name' => $this->clinic->doctor_name, 'signed_by' => $this->doctor->id, 'signed_at' => now()->subDays($daysAgo),
        ]);
    }

    private function order(Patient $p, int $daysAgo, array $studies, string $indication, string $delivery): void
    {
        $this->clinic->refresh();
        RadiologyOrder::create([
            'clinic_id' => $this->clinic->id, 'patient_id' => $p->id, 'number' => $this->clinic->next_order,
            'issued_on' => today()->subDays($daysAgo), 'center' => ($this->clinic->radiology_centers ?? [])[0] ?? null,
            'studies' => array_map(fn ($s) => ['name' => $s[0], 'zone' => $s[1], 'qty' => $s[2]], $studies),
            'indication' => $indication, 'delivery' => $delivery, 'created_by' => $this->doctor->id,
        ]);
        $this->clinic->increment('next_order');
    }

    private function expense(string $cat, int $amount, string $detail, ?int $sede, Carbon $date): void
    {
        Expense::create([
            'clinic_id' => $this->clinic->id, 'sede_id' => $sede, 'spent_on' => $date, 'category' => $cat,
            'amount' => $amount, 'method' => 'Transferencia', 'detail' => $detail.' (ejemplo)', 'created_by' => $this->doctor->id,
        ]);
    }

    /** Abre y cierra la caja de cada día pasado que tuvo pagos en efectivo. Uno de los cierres tiene un faltante. */
    private function closeCashDays(): void
    {
        $days = Payment::where('clinic_id', $this->clinic->id)->whereDate('paid_on', '<', today())->get()->groupBy(fn ($p) => $p->paid_on->toDateString());
        $i = 0;
        foreach ($days as $day => $payments) {
            $opened = Carbon::parse($day)->setTime(8, 0);
            $session = CashSession::create([
                'clinic_id' => $this->clinic->id, 'sede_id' => $this->sedes['Envigado']->id, 'opened_at' => $opened,
                'opened_by' => $this->assistant->id, 'opening_amount' => 200000,
            ]);
            Payment::whereIn('id', $payments->pluck('id'))->update(['cash_session_id' => $session->id]);
            $expected = $session->expectedNow();
            $session->update([
                'closed_at' => $opened->copy()->setTime(18, 30), 'closed_by' => $this->assistant->id,
                'expected_cash' => $expected, 'counted_cash' => $expected - ($i++ === 1 ? 10000 : 0),
            ]);
        }
    }

    /** Días atrás sin salirse de este mes (para que Reportes tenga movimiento aunque el mes vaya empezando). */
    private function r(int $days): int
    {
        return max(0, min($days, today()->day - 1));
    }

    /** Fecha de este mes sin pasar de hoy. */
    private function thisMonth(int $day): Carbon
    {
        return today()->startOfMonth()->addDays(min($day, today()->day) - 1);
    }

    /** Un día hábil hace más o menos $days días. */
    private function pastWeekday(int $days): Carbon
    {
        $d = today()->subDays($days);
        while ($d->isSunday()) {
            $d->subDay();
        }

        return $d;
    }

    /** Hoy, o el próximo día hábil si hoy es domingo. */
    private function sedeToday(): Carbon
    {
        return today()->isSunday() ? today()->addDay() : today();
    }
}
