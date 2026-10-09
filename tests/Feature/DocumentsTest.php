<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Quote;
use App\Models\RadiologyOrder;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function order(array $c, Patient $p, array $extra = [])
    {
        return $this->actingAs($c['doctor'])->post(route('orders.store', $p), $extra + [
            'studies' => [
                ['name' => 'Radiografía panorámica', 'qty' => 1],
                ['zone' => '36', 'qty' => 1], // sin marcar: se ignora
                ['name' => 'Radiografía periapical', 'zone' => '36, 46', 'qty' => 2],
            ],
            'center' => 'Cero70',
            'indication' => 'Descartar lesión periapical',
            'delivery' => 'correo_doctora',
        ]);
    }

    public function test_doctor_creates_numbered_radiology_orders(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->order($c, $p)->assertRedirect(route('patients.orders', $p))->assertSessionHasNoErrors();
        $this->order($c, $p);

        $orders = RadiologyOrder::orderBy('id')->get();
        $this->assertSame(['RX-00001', 'RX-00002'], $orders->map->code()->all());
        $this->assertCount(2, $orders[0]->studies);
        $this->assertSame('Radiografía periapical (36, 46)', $orders[0]->studies[1]['name'].' ('.$orders[0]->studies[1]['zone'].')');

        $this->actingAs($c['assistant'])->get(route('patients.orders', $p))->assertOk()->assertSee('Radiografía panorámica');
    }

    public function test_assistant_cannot_create_orders(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['assistant'])->get(route('orders.create', $p))->assertForbidden();
        $this->actingAs($c['assistant'])->post(route('orders.store', $p), ['studies' => [['name' => 'X']], 'delivery' => 'paciente_recoge'])->assertForbidden();
        $this->assertSame(0, RadiologyOrder::count());
    }

    public function test_order_needs_studies_and_a_complete_patient(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->post(route('orders.store', $p), ['studies' => [['zone' => '1']], 'delivery' => 'paciente_recoge'])
            ->assertSessionHasErrors('studies');
        $this->order($c, $p, ['delivery' => 'virtual_paciente'])->assertSessionHasErrors('delivery'); // sin correo

        $prospect = Patient::create(['clinic_id' => $c['clinic']->id, 'name' => 'Sin datos', 'phone' => '3110001111', 'phone_key' => '3110001111']);
        $this->actingAs($c['doctor'])->get(route('orders.create', $prospect))->assertRedirect(route('patients.complete', $prospect));
    }

    public function test_order_pdf_downloads(): void
    {
        if (! class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('Dompdf no instalado');
        }
        $c = $this->clinic();
        $c['clinic']->update(['signature' => self::PNG, 'doctor_license' => '12345']);
        $p = $this->patient($c['clinic']);
        $this->order($c, $p);

        $res = $this->actingAs($c['assistant'])->get(route('orders.pdf', [$p, RadiologyOrder::first()]));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_orders_of_another_clinic_are_not_reachable(): void
    {
        $a = $this->clinic();
        $b = $this->clinic('Otra');
        $p = $this->patient($a['clinic']);
        $this->order($a, $p);
        $orderId = RadiologyOrder::firstOrFail()->id;

        $this->actingAs($b['doctor'])->get(route('orders.pdf', [$p->id, $orderId]))->assertNotFound();
    }

    public function test_doctor_saves_brand_and_signature(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['doctor'])->post(route('settings.brand'), [
            'doctor_name' => 'Dra. Prueba', 'doctor_title' => 'Odontóloga', 'doctor_license' => 'TP 999',
            'email' => 'dra@correo.com', 'brand_color' => '#5E0F5C',
            'radiology_centers' => "Cero70\n\n  Otro centro  ",
            'signature' => self::PNG,
        ])->assertSessionHasNoErrors();

        $clinic = $c['clinic']->fresh();
        $this->assertSame('TP 999', $clinic->doctor_license);
        $this->assertSame(['Cero70', 'Otro centro'], $clinic->radiology_centers);
        $this->assertSame(self::PNG, Brand::signature($clinic));

        $this->actingAs($c['doctor'])->post(route('settings.brand'), ['doctor_name' => 'Dra. Prueba', 'signature' => 'data:image/png;base64,nopng'])
            ->assertSessionHasErrors('signature');
        $this->actingAs($c['assistant'])->post(route('settings.brand'), ['doctor_name' => 'X'])->assertForbidden();
    }

    public function test_quote_items_priced_by_case_are_not_added_to_the_total(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['doctor'])->post('/cotizaciones', [
            'name' => 'Jose Morales', 'phone' => '3024272624',
            'items' => [
                ['description' => 'Corona sobre implante', 'quantity' => 1, 'unit_price' => '1.800.000'],
                ['description' => 'Endodoncia', 'quantity' => 1, 'unit_price' => '', 'price_options' => 'Uniradicular $400.000, Biradicular $450.000'],
            ],
        ])->assertSessionHasNoErrors();

        $q = Quote::with('items')->firstOrFail();
        $this->assertSame(1800000, $q->total);
        $this->assertSame(['Uniradicular $400.000', 'Biradicular $450.000'], $q->items[1]->priceOptionLines());
        $this->actingAs($c['doctor'])->get(route('quotes.show', $q))->assertSee('Biradicular $450.000');

        if (class_exists(\Dompdf\Dompdf::class)) {
            $this->actingAs($c['doctor'])->get(route('quotes.pdf', $q))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_brand_colors_fall_back_to_default(): void
    {
        $c = $this->clinic();
        $this->assertSame(Brand::DEFAULT_COLOR, Brand::colors($c['clinic'])['main']);
        $this->assertSame('#FFFFFF', Brand::mix('#000000', 0));
    }
}
