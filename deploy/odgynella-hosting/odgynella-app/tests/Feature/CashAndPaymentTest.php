<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function pay(array $c, int $patientId, $amount, string $method = 'Efectivo')
    {
        return $this->actingAs($c['assistant'])->post('/pagos', [
            'patient_id' => $patientId, 'amount' => $amount, 'method' => $method, 'concept' => 'Valoración',
        ]);
    }

    public function test_payment_requires_an_open_cash_register(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->pay($c, $p->id, 30000)->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::count());
    }

    public function test_receipts_are_consecutive_and_amounts_accept_formatting(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $this->actingAs($c['assistant'])->post('/caja/abrir', ['opening_amount' => '200.000']);

        $this->pay($c, $p->id, '$ 30.000')->assertRedirect();
        $this->pay($c, $p->id, '1.250.000', 'Transferencia')->assertRedirect();

        $this->assertSame([1, 2], Payment::orderBy('id')->pluck('receipt_number')->all());
        $this->assertSame([30000, 1250000], Payment::orderBy('id')->pluck('amount')->all());
        $this->assertSame('RC-00002', Payment::orderBy('id')->skip(1)->first()->receiptCode());
    }

    public function test_closing_compares_counted_cash_with_expected(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $this->actingAs($c['assistant'])->post('/caja/abrir', ['opening_amount' => '200000']);
        $this->pay($c, $p->id, 30000);
        $this->pay($c, $p->id, 500000, 'Nequi'); // no es efectivo: no entra al cuadre

        $this->actingAs($c['assistant'])->post('/caja/cerrar', ['counted_cash' => '220000'])->assertSessionHas('warn');

        $s = CashSession::firstOrFail();
        $this->assertSame(230000, $s->expected_cash);
        $this->assertSame(-10000, $s->difference());
    }

    public function test_only_the_doctor_can_void_and_the_payment_is_kept(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $this->actingAs($c['assistant'])->post('/caja/abrir', ['opening_amount' => '0']);
        $this->pay($c, $p->id, 30000);
        $payment = Payment::firstOrFail();

        $this->actingAs($c['assistant'])->post(route('payments.void', $payment), ['void_reason' => 'Se registró dos veces'])->assertForbidden();
        $this->actingAs($c['doctor'])->post(route('payments.void', $payment), ['void_reason' => 'Se registró dos veces'])->assertSessionHasNoErrors();

        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertSame(1, Payment::count());
        $this->assertSame(0, (int) Payment::valid()->sum('amount'));
    }

    public function test_cannot_void_after_the_cash_register_is_closed(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $this->actingAs($c['assistant'])->post('/caja/abrir', ['opening_amount' => '0']);
        $this->pay($c, $p->id, 30000);
        $this->actingAs($c['assistant'])->post('/caja/cerrar', ['counted_cash' => '30000']);

        $this->actingAs($c['doctor'])->post(route('payments.void', Payment::first()), ['void_reason' => 'Error de digitación'])->assertSessionHasErrors('void_reason');
    }
}
