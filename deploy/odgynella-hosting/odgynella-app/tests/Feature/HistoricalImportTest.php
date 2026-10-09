<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Quote;
use App\Support\Accounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalImportTest extends TestCase
{
    use RefreshDatabase;

    private function import(): array
    {
        $c = $this->clinic();
        $this->artisan('historico:importar', ['archivo' => base_path('tests/fixtures/historico-prueba.json')])->assertSuccessful();

        return $c;
    }

    public function test_imports_everything_as_historical_without_using_numbering(): void
    {
        $this->import();

        $this->assertSame(2, Patient::where('historical', true)->whereNull('phone')->count());
        $this->assertSame(2, Quote::where('historical', true)->whereNull('number')->count());
        $this->assertSame(2, Payment::where('historical', true)->whereNull('receipt_number')->count());
        $this->assertSame(1, Expense::where('historical', true)->count());
        $this->assertSame('HIST-P2', Quote::where('legacy_ref', 'P2')->first()->code());
        $this->assertSame('HIST-PAY1', Payment::where('legacy_ref', 'PAY1')->first()->receiptCode());
        $this->assertSame('2026-07-02', Patient::where('name', 'Ana Prueba')->first()->created_at->toDateString());

        $this->artisan('historico:importar', ['archivo' => base_path('tests/fixtures/historico-prueba.json')])->assertFailed();
        $this->assertSame(2, Patient::count());
    }

    public function test_historical_stays_out_of_reports_but_debts_show_in_receivables(): void
    {
        $c = $this->import();

        $r = $this->actingAs($c['doctor'])->get('/reportes?mes=2026-07')->assertOk();
        $r->assertViewHas('income', 0);
        $r->assertViewHas('spent', 0);
        $r->assertViewHas('production', 0);
        $r->assertViewHas('newPeople', 0);
        $r->assertViewHas('receivable', 300000); // Beto: 400.000 hecho - 100.000 pagado

        $csv = $this->actingAs($c['doctor'])->get('/reportes/exportar?desde=2026-06-01&hasta=2026-07-31')->streamedContent();
        $this->assertStringNotContainsString('HIST-', $csv);

        $this->actingAs($c['assistant']);
        $this->assertSame(300000, Accounts::receivables()->first()['account']['owed']);
        $this->get('/cartera')->assertOk()->assertSee('Beto Prueba')->assertSee('Sin WhatsApp');
    }

    public function test_new_payment_on_a_historical_plan_counts_from_today(): void
    {
        $c = $this->import();
        $this->actingAs($c['assistant'])->post('/caja/abrir', ['opening_amount' => '0']);
        $plan = Quote::where('legacy_ref', 'P2')->first();
        $beto = Patient::where('name', 'Beto Prueba')->first();

        $this->actingAs($c['assistant'])->post('/pagos', ['patient_id' => $beto->id, 'quote_id' => $plan->id, 'amount' => '300000', 'method' => 'Nequi'])
            ->assertSessionHasNoErrors();

        $new = Payment::where('historical', false)->firstOrFail();
        $this->assertSame('RC-00001', $new->receiptCode());
        $this->assertSame(600000, $plan->fresh()->balance());
        $this->actingAs($c['doctor'])->get('/reportes')->assertViewHas('income', 300000);
    }

    public function test_patient_without_whatsapp_can_be_opened_and_completed(): void
    {
        $c = $this->import();
        $ana = Patient::where('name', 'Ana Prueba')->first();

        $this->actingAs($c['assistant'])->get(route('patients.show', $ana))->assertOk()->assertSee('Sin WhatsApp')->assertSee('Registro histórico de prueba.');
        $this->actingAs($c['assistant'])->get(route('patients.account', $ana))->assertOk()->assertSee('HIST-P1');
        $this->actingAs($c['assistant'])->get('/pacientes')->assertOk()->assertSee('histórico');

        $this->actingAs($c['assistant'])->put(route('patients.update', $ana), ['name' => 'Ana Prueba', 'phone' => '300 555 1234'])->assertSessionHasNoErrors();
        $this->assertSame('3005551234', $ana->fresh()->phone_key);
    }

    public function test_historical_payments_cannot_be_voided(): void
    {
        $c = $this->import();
        $p = Payment::where('legacy_ref', 'PAY1')->first();

        $this->actingAs($c['doctor'])->post(route('payments.void', $p), ['void_reason' => 'Prueba de anulación'])->assertSessionHasErrors('void_reason');
        $this->assertNull($p->fresh()->voided_at);
    }
}
