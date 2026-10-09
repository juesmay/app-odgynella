<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Quote;
use App\Support\QuotePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private function quotePayload(array $over = []): array
    {
        return array_merge([
            'name' => 'Laura Gómez', 'phone' => '300 555 1234', 'source' => 'Facebook',
            'items' => [
                ['description' => 'Diseño de sonrisa', 'teeth' => '13 a 23', 'quantity' => '1', 'unit_price' => '2.000.000'],
                ['description' => 'Limpieza', 'quantity' => '2', 'unit_price' => '140000'],
                ['description' => '', 'unit_price' => ''], // fila vacía: se ignora
            ],
            'discount_label' => 'Promoción 2x1',
            'discount_amount' => '$ 280.000',
            'internal_notes' => 'Quiere dientes más blancos.',
        ], $over);
    }

    public function test_doctor_quotes_a_new_prospect_with_only_name_and_whatsapp(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload())->assertSessionHasNoErrors()->assertRedirect();

        $p = Patient::firstOrFail();
        $this->assertSame('prospecto', $p->stage);
        $this->assertNull($p->doc_number);
        $this->assertFalse($p->isComplete());

        $q = Quote::with('items')->firstOrFail();
        $this->assertSame(1, $q->number);
        $this->assertSame('COT-00001', $q->code());
        $this->assertCount(2, $q->items);
        $this->assertSame(2280000, $q->subtotal);
        $this->assertSame(280000, $q->discount_amount);
        $this->assertSame(2000000, $q->total);
        $this->assertSame(today()->addDays(30)->toDateString(), $q->valid_until->toDateString());
    }

    public function test_existing_whatsapp_reuses_the_person(): void
    {
        $c = $this->clinic();
        $existing = $this->patient($c['clinic'], '3005551234', 'Laura existente');

        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload(['phone' => '(300) 555-1234']))->assertSessionHasNoErrors();

        $this->assertSame(1, Patient::count());
        $this->assertSame($existing->id, Quote::first()->patient_id);
    }

    public function test_quote_needs_items_and_a_discount_below_subtotal(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload(['items' => [['description' => '']]]))->assertSessionHasErrors('items');
        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload(['discount_amount' => '9.000.000']))->assertSessionHasErrors('discount_amount');
        $this->assertSame(0, Quote::count());
    }

    public function test_prospect_must_complete_data_before_booking_then_becomes_scheduled(): void
    {
        $c = $this->clinic();
        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload());
        $p = Patient::firstOrFail();

        $booking = [
            'patient_id' => $p->id, 'service_id' => $c['service']->id, 'sede_id' => $c['sede']->id,
            'date' => today()->addDay()->toDateString(), 'start_time' => '09:00',
        ];

        $this->actingAs($c['assistant'])->post('/citas', $booking)->assertRedirect(route('patients.complete', $p));
        $this->assertSame(0, Appointment::count());

        $this->put(route('patients.complete.store', $p), ['doc_type' => 'C.C.', 'doc_number' => '1036000', 'data_consent' => '1'])
            ->assertRedirect(route('appointments.create', ['paciente' => $p->id]));

        $this->post('/citas', $booking)->assertSessionHasNoErrors();
        $this->assertSame('agendado', $p->fresh()->stage);
        $this->assertSame(1, Appointment::count());
    }

    public function test_completing_data_requires_consent(): void
    {
        $c = $this->clinic();
        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload());
        $p = Patient::firstOrFail();

        $this->put(route('patients.complete.store', $p), ['doc_type' => 'C.C.', 'doc_number' => '1036000'])->assertSessionHasErrors('data_consent');
        $this->assertNull($p->fresh()->data_consent_at);
    }

    public function test_accepting_a_quote_moves_the_person_to_treatment(): void
    {
        $c = $this->clinic();
        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload());
        $q = Quote::firstOrFail();

        $this->post(route('quotes.accept', $q))->assertSessionHasNoErrors();

        $this->assertSame('aceptada', $q->fresh()->status);
        $this->assertSame('en_tratamiento', $q->patient->fresh()->stage);
        $this->get(route('quotes.edit', $q))->assertRedirect(route('quotes.show', $q));
    }

    public function test_lost_prospect_keeps_the_reason_and_can_come_back(): void
    {
        $c = $this->clinic();
        $this->actingAs($c['assistant'])->post('/cotizaciones', $this->quotePayload());
        $p = Patient::firstOrFail();

        $this->post(route('patients.lost', $p), ['reason' => 'Precio', 'detail' => 'Le pareció caro'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('no_continuo', $p->stage);
        $this->assertSame('Precio: Le pareció caro', $p->lost_reason);
        $this->assertSame('no_aceptada', Quote::first()->status);

        $this->post(route('patients.reactivate', $p));
        $this->assertSame('prospecto', $p->fresh()->stage);
        $this->assertNull($p->fresh()->lost_reason);
    }

    public function test_quote_numbers_are_per_clinic_and_hidden_from_other_clinics(): void
    {
        $a = $this->clinic('A');
        $b = $this->clinic('B');

        $this->actingAs($a['doctor'])->post('/cotizaciones', $this->quotePayload());
        $this->actingAs($b['doctor'])->post('/cotizaciones', $this->quotePayload());

        $this->assertSame([1, 1], Quote::withoutGlobalScopes()->orderBy('id')->pluck('number')->all());

        $quoteOfA = Quote::withoutGlobalScopes()->where('clinic_id', $a['clinic']->id)->first();
        $this->actingAs($b['doctor'])->get(route('quotes.show', $quoteOfA->id))->assertNotFound();
        $this->actingAs($b['doctor'])->get(route('quotes.pdf', $quoteOfA->id))->assertNotFound();
    }

    public function test_pages_render(): void
    {
        $c = $this->clinic();
        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload());
        $q = Quote::firstOrFail();

        $this->get('/cotizaciones/nueva')->assertOk();
        $this->get(route('quotes.show', $q))->assertOk()->assertSee('COT-00001')->assertSee('Quiere dientes más blancos.');
        $this->get(route('quotes.edit', $q))->assertOk();
        $this->get('/cotizaciones')->assertOk()->assertSee('Laura Gómez');
        $this->get(route('patients.show', $q->patient_id))->assertOk()->assertSee('En cotización');
        $this->get('/')->assertOk()->assertSee('De la asesoría al tratamiento');
    }

    public function test_pdf_downloads(): void
    {
        if (! QuotePdf::available()) {
            $this->markTestSkipped('Instala el generador de PDF: composer require dompdf/dompdf');
        }

        $c = $this->clinic();
        $this->actingAs($c['doctor'])->post('/cotizaciones', $this->quotePayload());

        $res = $this->get(route('quotes.pdf', Quote::first()));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}
