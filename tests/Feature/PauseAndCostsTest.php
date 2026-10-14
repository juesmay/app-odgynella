<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Quote;
use App\Support\Accounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PauseAndCostsTest extends TestCase
{
    use RefreshDatabase;

    /** Plan aceptado de 1.000.000 con dos ítems; el primero (400.000) ya hecho. */
    private function plan(array $c, $patient): Quote
    {
        $q = Quote::create(['clinic_id' => $c['clinic']->id, 'patient_id' => $patient->id, 'number' => null, 'issued_on' => today()->subDays(40),
            'valid_until' => today(), 'subtotal' => 1000000, 'discount_amount' => 0, 'total' => 1000000, 'status' => 'aceptada', 'created_by' => $c['doctor']->id]);
        $q->items()->create(['description' => 'Endodoncia', 'quantity' => 1, 'unit_price' => 400000, 'line_total' => 400000, 'position' => 0, 'done_at' => now()->subDays(35)]);
        $q->items()->create(['description' => 'Corona', 'quantity' => 1, 'unit_price' => 600000, 'line_total' => 600000, 'position' => 1]);
        $patient->update(['stage' => 'en_tratamiento']);

        return $q;
    }

    public function test_pause_freezes_cartera_and_payments_then_resume_same_plan(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $q = $this->plan($c, $p);
        Payment::create(['clinic_id' => $c['clinic']->id, 'patient_id' => $p->id, 'quote_id' => $q->id, 'sede_id' => $c['sede']->id, 'receipt_number' => 1,
            'paid_on' => today()->subDays(35), 'amount' => 100000, 'method' => 'Efectivo', 'concept' => 'Abono', 'created_by' => $c['doctor']->id]);

        $this->actingAs($c['doctor']);
        $this->assertSame(300000, Accounts::receivables()->first()['account']['owed']);

        $this->post(route('patients.pause', $p), ['reason' => 'Dinero', 'detail' => 'vuelve en enero'])->assertSessionHasNoErrors();
        $this->assertSame('en_pausa', $p->fresh()->stage);
        $this->assertTrue($q->fresh()->isPaused());
        $this->assertCount(0, Accounts::receivables());
        $this->assertSame(300000, Accounts::paused()->first()['owed']);
        $this->get(route('receivables.index'))->assertSee('1 tratamiento en pausa');

        // No se le registran abonos mientras está en pausa.
        $this->post(route('payments.store'), ['patient_id' => $p->id, 'quote_id' => $q->id, 'amount' => '50000', 'method' => 'Efectivo'])
            ->assertSessionHasErrors('quote_id');

        $this->post(route('patients.resume', $p))->assertSessionHasNoErrors();
        $this->assertSame('en_tratamiento', $p->fresh()->stage);
        $this->assertFalse($q->fresh()->isPaused());
        $this->assertCount(1, Accounts::receivables());
    }

    public function test_resume_with_new_quote_closes_the_old_plan(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $q = $this->plan($c, $p);
        // Pagó 500.000: 100.000 de anticipo sobre lo hecho (400.000).
        Payment::create(['clinic_id' => $c['clinic']->id, 'patient_id' => $p->id, 'quote_id' => $q->id, 'sede_id' => $c['sede']->id, 'receipt_number' => 1,
            'paid_on' => today()->subDays(35), 'amount' => 500000, 'method' => 'Efectivo', 'concept' => 'Abono', 'created_by' => $c['doctor']->id]);

        $this->actingAs($c['doctor'])->post(route('patients.pause', $p), ['reason' => 'Viaje o cambio de ciudad']);

        $res = $this->get(route('quotes.create', ['paciente' => $p->id, 'retomar' => 1]))->assertOk()->assertSee('Vuelve de una pausa');
        $this->assertSame(['Corona'], collect($res->viewData('items'))->pluck('description')->all());
        $this->assertSame(100000, $res->viewData('quote')->discount_amount); // el anticipo va como abono

        $this->post(route('quotes.store'), [
            'patient_id' => $p->id,
            'items' => [['description' => 'Corona', 'quantity' => 1, 'unit_price' => '650000']],
            'discount_amount' => '100000', 'discount_label' => 'Abono del plan anterior',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $new = Quote::orderByDesc('id')->first();
        $this->assertTrue($q->fresh()->isPaused()); // sigue congelado hasta que acepte

        $this->post(route('quotes.accept', $new))->assertSessionHasNoErrors();
        $old = $q->fresh();
        $this->assertTrue($old->isClosed());
        $this->assertSame(400000, $old->account()['total']);
        $this->assertSame(0, $old->account()['advance']);
        $this->assertSame(0, $old->account()['owed']);
        $this->assertSame('en_tratamiento', $p->fresh()->stage);
        $this->assertSame(550000, $new->fresh()->account()['balance']);
    }

    public function test_attended_appointment_records_room_time_as_patient_cost(): void
    {
        $c = $this->clinic();
        $c['sede']->update(['hourly_rate' => 30000]);
        $p = $this->patient($c['clinic']);
        $a = Appointment::create(['clinic_id' => $c['clinic']->id, 'patient_id' => $p->id, 'sede_id' => $c['sede']->id, 'service_id' => $c['service']->id,
            'date' => today(), 'start_time' => '09:00', 'end_time' => '10:30', 'status' => 'en_sala', 'created_by' => $c['assistant']->id]);

        $this->actingAs($c['assistant'])->patch(route('appointments.status', $a), ['status' => 'atendida'])->assertSessionHasNoErrors();

        $e = Expense::where('appointment_id', $a->id)->firstOrFail();
        $this->assertSame(45000, $e->amount);
        $this->assertSame(1.5, $e->hours);
        $this->assertSame('consultorio', $e->type);
        $this->assertSame($p->id, $e->patient_id);

        // Otro costo del paciente desde su cuenta y la ganancia.
        Payment::create(['clinic_id' => $c['clinic']->id, 'patient_id' => $p->id, 'sede_id' => $c['sede']->id, 'receipt_number' => 1,
            'paid_on' => today(), 'amount' => 300000, 'method' => 'Efectivo', 'concept' => 'Valoración', 'created_by' => $c['doctor']->id]);
        $lab = ExpenseCategory::where('name', 'Laboratorio dental')->first();
        $this->actingAs($c['doctor'])->post(route('expenses.store'), [
            'patient_id' => $p->id, 'volver' => 'paciente', 'category_id' => $lab->id, 'amount' => '100.000', 'detail' => 'Corona',
            'spent_on' => today()->toDateString(), 'method' => 'Transferencia',
        ])->assertRedirect(route('patients.account', $p));

        $this->get(route('patients.account', $p))->assertOk()->assertSee('Costo y ganancia')->assertSee('$155.000');
        $this->actingAs($c['assistant'])->get(route('patients.account', $p))->assertOk()->assertDontSee('Costo y ganancia');

        // Sin tarifa no se registra nada.
        $c['sede']->update(['hourly_rate' => 0]);
        $b = Appointment::create(['clinic_id' => $c['clinic']->id, 'patient_id' => $p->id, 'sede_id' => $c['sede']->id, 'service_id' => $c['service']->id,
            'date' => today(), 'start_time' => '11:00', 'end_time' => '11:30', 'status' => 'en_sala', 'created_by' => $c['assistant']->id]);
        $this->patch(route('appointments.status', $b), ['status' => 'atendida']);
        $this->assertFalse(Expense::where('appointment_id', $b->id)->exists());
    }

    public function test_personal_expenses_are_separate_and_private(): void
    {
        $c = $this->clinic();
        $food = ExpenseCategory::where('type', 'personal')->where('name', 'Alimentación')->firstOrFail();
        $ads = ExpenseCategory::where('name', 'Publicidad')->firstOrFail();
        $base = ['spent_on' => today()->toDateString(), 'method' => 'Transferencia', 'detail' => 'X'];

        $this->actingAs($c['doctor'])->post(route('expenses.store'), $base + ['category_id' => $food->id, 'amount' => '200000'])->assertSessionHasNoErrors();
        $this->post(route('expenses.store'), $base + ['category_id' => $ads->id, 'amount' => '300000'])->assertSessionHasNoErrors();
        $this->assertSame('personal', Expense::where('category', 'Alimentación')->first()->type);

        Payment::create(['clinic_id' => $c['clinic']->id, 'patient_id' => $this->patient($c['clinic'])->id, 'sede_id' => $c['sede']->id, 'receipt_number' => 1,
            'paid_on' => today(), 'amount' => 1000000, 'method' => 'Efectivo', 'concept' => 'Abono', 'created_by' => $c['doctor']->id]);

        $r = $this->get(route('reports.index'))->assertOk();
        $this->assertSame(300000, $r->viewData('spent'));
        $this->assertSame(700000, $r->viewData('profit'));
        $this->assertSame(200000, $r->viewData('personalSpent'));
        $this->assertSame(500000, $r->viewData('kept'));

        // El contador no recibe lo personal.
        $csv = $this->get(route('reports.export', ['desde' => today()->toDateString(), 'hasta' => today()->toDateString()]))->streamedContent();
        $this->assertStringContainsString('Publicidad', $csv);
        $this->assertStringNotContainsString('Alimentación', $csv);

        // La asistente no ve ni registra lo personal.
        $this->actingAs($c['assistant'])->post(route('expenses.store'), $base + ['category_id' => $food->id, 'amount' => '1000'])->assertSessionHasErrors('category_id');
        $this->get(route('expenses.index'))->assertDontSee('Alimentación');

        // La doctora agrega categorías propias.
        $this->actingAs($c['doctor'])->post(route('settings.categories.store'), ['type' => 'personal', 'name' => 'Colegio de los niños'])->assertSessionHasNoErrors();
        $this->assertTrue(ExpenseCategory::where('name', 'Colegio de los niños')->where('type', 'personal')->exists());
    }
}
