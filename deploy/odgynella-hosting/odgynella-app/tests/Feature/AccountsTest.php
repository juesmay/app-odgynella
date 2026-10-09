<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\Expense;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Quote;
use App\Support\Accounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    /** Plan aceptado: dos ítems de 600.000 y 400.000 con 100.000 de descuento → total 900.000. */
    private function plan(Clinic $clinic, Patient $p, int $discount = 100000): Quote
    {
        $q = Quote::create([
            'clinic_id' => $clinic->id, 'patient_id' => $p->id, 'number' => Quote::count() + 1,
            'issued_on' => today(), 'valid_until' => today()->addDays(30),
            'subtotal' => 1000000, 'discount_amount' => $discount, 'discount_label' => 'Promoción', 'total' => 1000000 - $discount, 'status' => 'aceptada',
        ]);
        $q->items()->create(['description' => 'Prótesis', 'quantity' => 1, 'unit_price' => 600000, 'line_total' => 600000, 'position' => 1]);
        $q->items()->create(['description' => 'Limpieza', 'quantity' => 1, 'unit_price' => 400000, 'line_total' => 400000, 'position' => 2]);

        return $q;
    }

    private function openCash(array $c): void
    {
        $this->actingAs($c['assistant'])->post('/caja/abrir', ['opening_amount' => '200000']);
    }

    public function test_payment_on_a_plan_updates_the_balance(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $q = $this->plan($c['clinic'], $p);
        $this->openCash($c);

        $this->actingAs($c['assistant'])->post('/pagos', [
            'patient_id' => $p->id, 'quote_id' => $q->id, 'amount' => '300.000', 'method' => 'Efectivo',
        ])->assertSessionHasNoErrors();

        $pay = Payment::firstOrFail();
        $this->assertSame($q->id, $pay->quote_id);
        $this->assertSame('Abono a tratamiento '.$q->code(), $pay->concept);

        $a = $q->fresh()->account();
        $this->assertSame(900000, $a['total']);
        $this->assertSame(300000, $a['paid']);
        $this->assertSame(600000, $a['balance']);
        $this->assertSame(0, $a['owed']);
        $this->assertSame(300000, $a['advance']); // nada hecho todavía: es anticipo

        $this->actingAs($c['assistant'])->get(route('payments.receipt', $pay))->assertSee('Saldo de tu tratamiento: $600.000');
    }

    public function test_done_work_is_valued_with_the_discount_and_creates_debt(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $q = $this->plan($c['clinic'], $p);
        $q->items()->where('description', 'Prótesis')->update(['done_at' => now()]);

        $a = $q->fresh()->account();
        $this->assertSame(540000, $a['done']); // 600.000 × 900/1000
        $this->assertSame(540000, $a['owed']);

        $q->items()->update(['done_at' => now()]);
        $this->assertSame(900000, $q->fresh()->doneValue()); // todo hecho = total exacto
    }

    public function test_cannot_pay_more_than_the_balance_or_on_someone_elses_plan(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $other = $this->patient($c['clinic'], '3110000000', 'Otra Persona');
        $q = $this->plan($c['clinic'], $p);
        $this->openCash($c);

        $this->actingAs($c['assistant'])->post('/pagos', ['patient_id' => $p->id, 'quote_id' => $q->id, 'amount' => '900001', 'method' => 'Efectivo'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($c['assistant'])->post('/pagos', ['patient_id' => $other->id, 'quote_id' => $q->id, 'amount' => '1000', 'method' => 'Efectivo'])
            ->assertSessionHasErrors('quote_id');
        $this->assertSame(0, Payment::count());
    }

    public function test_voided_payments_do_not_count(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $q = $this->plan($c['clinic'], $p);
        $this->openCash($c);
        $this->actingAs($c['assistant'])->post('/pagos', ['patient_id' => $p->id, 'quote_id' => $q->id, 'amount' => '500000', 'method' => 'Efectivo']);

        $this->actingAs($c['doctor'])->post(route('payments.void', Payment::first()), ['void_reason' => 'Se registró dos veces']);

        $this->assertSame(900000, $q->fresh()->balance());
    }

    public function test_receivables_age_from_the_last_payment(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $q = $this->plan($c['clinic'], $p);
        $q->items()->update(['done_at' => now()->subDays(70)]);
        Payment::create([
            'clinic_id' => $c['clinic']->id, 'patient_id' => $p->id, 'quote_id' => $q->id, 'sede_id' => $c['sede']->id,
            'receipt_number' => 1, 'paid_on' => today()->subDays(40), 'amount' => 400000, 'method' => 'Efectivo', 'concept' => 'Abono', 'created_by' => $c['assistant']->id,
        ]);

        $this->actingAs($c['assistant']);
        $rows = Accounts::receivables();
        $this->assertCount(1, $rows);
        $this->assertSame(500000, $rows[0]['account']['owed']);
        $this->assertSame(40, $rows[0]['days']);
        $this->assertSame('30', $rows[0]['bucket']);

        $this->get('/cartera')->assertOk()
            ->assertSee('Paciente Prueba')
            ->assertSee('$500.000')
            ->assertSee('https://wa.me/573001234567', false);
    }

    public function test_account_tab_shows_the_plan(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $this->plan($c['clinic'], $p);

        $this->actingAs($c['assistant'])->get(route('patients.account', $p))
            ->assertOk()->assertSee('Total de tratamientos')->assertSee('$900.000')->assertSee('Registrar abono');
    }

    public function test_cash_expense_reduces_the_expected_cash(): void
    {
        $c = $this->clinic();
        $this->openCash($c);

        $this->actingAs($c['assistant'])->post('/gastos', [
            'spent_on' => today()->toDateString(), 'category' => 'Materiales e insumos', 'amount' => '45.000',
            'method' => 'Efectivo de la caja', 'detail' => 'Guantes',
        ])->assertSessionHasNoErrors();

        $e = Expense::firstOrFail();
        $this->assertSame($c['sede']->id, $e->sede_id);
        $this->assertNotNull($e->cash_session_id);
        $this->assertSame(155000, CashSession::openFor($c['sede']->id)->expectedNow());
    }

    public function test_cash_expense_needs_an_open_register(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['assistant'])->post('/gastos', [
            'spent_on' => today()->toDateString(), 'category' => 'Otros', 'amount' => '10000',
            'method' => 'Efectivo de la caja', 'detail' => 'Algo',
        ])->assertSessionHasErrors('method');
        $this->assertSame(0, Expense::count());
    }

    public function test_assistant_sees_only_her_expenses_and_never_payroll(): void
    {
        $c = $this->clinic();
        $this->actingAs($c['doctor'])->post('/gastos', [
            'spent_on' => today()->toDateString(), 'category' => 'Nómina', 'amount' => '2000000', 'method' => 'Transferencia', 'detail' => 'Sueldo de recepción',
        ])->assertSessionHasNoErrors();
        $this->actingAs($c['doctor'])->post('/gastos', [
            'spent_on' => today()->toDateString(), 'category' => 'Arriendo', 'amount' => '3000000', 'method' => 'Transferencia', 'detail' => 'Arriendo local',
        ]);

        $this->actingAs($c['assistant'])->get('/gastos')->assertOk()->assertDontSee('Sueldo de recepción')->assertDontSee('Arriendo local');
        $this->actingAs($c['assistant'])->post('/gastos', [
            'spent_on' => today()->toDateString(), 'category' => 'Nómina', 'amount' => '1', 'method' => 'Transferencia', 'detail' => 'x',
        ])->assertSessionHasErrors('category');

        $this->actingAs($c['doctor'])->get('/gastos')->assertSee('Sueldo de recepción')->assertSee('Arriendo local');
    }

    public function test_only_the_doctor_voids_expenses(): void
    {
        $c = $this->clinic();
        $this->actingAs($c['assistant'])->post('/gastos', [
            'spent_on' => today()->toDateString(), 'category' => 'Otros', 'amount' => '10000', 'method' => 'Transferencia', 'detail' => 'Algo',
        ]);
        $e = Expense::firstOrFail();

        $this->actingAs($c['assistant'])->post(route('expenses.void', $e), ['void_reason' => 'Me equivoqué'])->assertForbidden();
        $this->actingAs($c['doctor'])->post(route('expenses.void', $e), ['void_reason' => 'Me equivoqué'])->assertSessionHasNoErrors();

        $this->assertNotNull($e->fresh()->voided_at);
        $this->assertSame(1, Expense::count());
    }

    public function test_expense_support_is_private(): void
    {
        Storage::fake('local');
        $c = $this->clinic();
        $other = $this->clinic('Otra');

        $this->actingAs($c['assistant'])->post('/gastos', [
            'spent_on' => today()->toDateString(), 'category' => 'Laboratorio dental', 'amount' => '250000', 'method' => 'Transferencia',
            'detail' => 'Corona', 'support' => UploadedFile::fake()->image('factura.jpg'),
        ])->assertSessionHasNoErrors();
        $e = Expense::firstOrFail();

        $this->actingAs($c['doctor'])->get(route('expenses.support', $e))->assertOk();
        $this->actingAs($other['doctor'])->get(route('expenses.support', $e))->assertNotFound();
    }

    public function test_reports_are_for_the_doctor_and_add_up(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $q = $this->plan($c['clinic'], $p);
        $q->items()->where('description', 'Prótesis')->update(['done_at' => now()]);
        $this->openCash($c);
        $this->actingAs($c['assistant'])->post('/pagos', ['patient_id' => $p->id, 'quote_id' => $q->id, 'amount' => '500000', 'method' => 'Transferencia']);
        $this->actingAs($c['assistant'])->post('/gastos', ['spent_on' => today()->toDateString(), 'category' => 'Publicidad', 'amount' => '120000', 'method' => 'Tarjeta', 'detail' => 'Pauta']);

        $this->actingAs($c['assistant'])->get('/reportes')->assertForbidden();
        $this->actingAs($c['assistant'])->get('/reportes/exportar?desde=2026-01-01&hasta=2026-12-31')->assertForbidden();

        $r = $this->actingAs($c['doctor'])->get('/reportes')->assertOk();
        $r->assertViewHas('income', 500000);
        $r->assertViewHas('spent', 120000);
        $r->assertViewHas('profit', 380000);
        $r->assertViewHas('production', 540000);
        $r->assertViewHas('receivable', 40000);
    }

    public function test_export_for_the_accountant(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $this->openCash($c);
        $this->actingAs($c['assistant'])->post('/pagos', ['patient_id' => $p->id, 'amount' => '30000', 'method' => 'Efectivo', 'concept' => 'Valoración']);
        $this->actingAs($c['assistant'])->post('/pagos', ['patient_id' => $p->id, 'amount' => '99999', 'method' => 'Efectivo', 'concept' => 'Error']);
        $this->actingAs($c['doctor'])->post(route('payments.void', Payment::orderBy('id')->skip(1)->first()), ['void_reason' => 'Valor equivocado']);
        $this->actingAs($c['doctor'])->post('/gastos', ['spent_on' => today()->toDateString(), 'category' => 'Arriendo', 'amount' => '3000000', 'method' => 'Transferencia', 'detail' => 'Arriendo octubre']);

        $res = $this->actingAs($c['doctor'])->get('/reportes/exportar?desde='.today()->startOfMonth()->toDateString().'&hasta='.today()->toDateString());
        $res->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(today()->format('d/m/Y').';Ingreso;RC-00001;"Paciente Prueba";"C.C. 123";Valoración', $csv);
        $this->assertStringContainsString('RC-00002', $csv);
        $this->assertStringContainsString('Anulado', $csv);
        $this->assertStringContainsString('Arriendo octubre', $csv);
        $this->assertStringContainsString(';TOTALES;;;;30000;3000000', $csv);
    }

    public function test_clinics_do_not_see_each_others_numbers(): void
    {
        $a = $this->clinic();
        $b = $this->clinic('Otra');
        $this->actingAs($a['doctor'])->post('/gastos', ['spent_on' => today()->toDateString(), 'category' => 'Arriendo', 'amount' => '777777', 'method' => 'Transferencia', 'detail' => 'Arriendo A']);

        $this->actingAs($b['doctor'])->get('/gastos')->assertDontSee('Arriendo A');
        $this->actingAs($b['doctor'])->get('/reportes')->assertViewHas('spent', 0);
    }
}
