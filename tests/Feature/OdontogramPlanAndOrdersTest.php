<?php

namespace Tests\Feature;

use App\Models\OdontogramFinding;
use App\Models\OrderTemplate;
use App\Models\RadiologyOrder;
use App\Support\Clinical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdontogramPlanAndOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_tooth_keeps_planned_treatment_and_builds_a_quote(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->put(route('patients.odontogram.update', [$p, 36]), [
            'findings' => ['caries'], 'surfaces' => ['caries' => ['O']],
            'plan' => [['service_id' => $c['service']->id, 'name' => 'Valoración'], ['service_id' => '', 'name' => 'Resina']],
        ])->assertSessionHasNoErrors();
        $this->put(route('patients.odontogram.update', [$p, 46]), [
            'plan' => [['service_id' => '', 'name' => 'Resina']],
        ])->assertSessionHasNoErrors();

        $t = $p->teeth()->where('tooth', 36)->first();
        $this->assertSame(['Valoración', 'Resina'], $t->planNames());
        $this->assertNotNull($p->teeth()->where('tooth', 46)->first()); // solo con tratamiento también se guarda

        $this->get(route('patients.odontogram', $p))->assertOk()->assertSee('Pasar a cotización (2 dientes)');

        $res = $this->get(route('quotes.create', ['paciente' => $p->id, 'desde' => 'odontograma']))->assertOk();
        $items = $res->viewData('items');
        $resina = collect($items)->firstWhere('description', 'Resina');
        $this->assertSame('36, 46', $resina['teeth']);
        $this->assertSame(2, $resina['quantity']);
        $this->assertSame(30000, collect($items)->firstWhere('description', 'Valoración')['unit_price']);

        // Quitar todo borra el diente.
        $this->put(route('patients.odontogram.update', [$p, 46]), [])->assertSessionHasNoErrors();
        $this->assertNull($p->teeth()->where('tooth', 46)->first());

        // Un servicio de otra clínica no se acepta.
        $other = $this->clinic('Otra');
        $this->put(route('patients.odontogram.update', [$p, 11]), ['plan' => [['service_id' => $other['service']->id, 'name' => 'X']]])
            ->assertSessionHasErrors('plan.0.service_id');
    }

    public function test_doctor_adds_custom_findings_used_in_the_odontogram(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->post(route('settings.findings.store'), ['name' => 'Amalgama en mal estado', 'abbr' => 'AM', 'color' => '#6b7280', 'faces' => '1'])
            ->assertSessionHasNoErrors();
        $this->post(route('settings.findings.store'), ['name' => 'Caries', 'abbr' => 'C', 'color' => '#000000'])->assertSessionHasErrors('name');
        $this->actingAs($c['assistant'])->post(route('settings.findings.store'), ['name' => 'X', 'abbr' => 'X', 'color' => '#000000'])->assertForbidden();

        $f = OdontogramFinding::firstOrFail();
        $this->assertSame('#6B7280', $f->color);
        Clinical::forgetFindings();

        $this->actingAs($c['doctor'])->put(route('patients.odontogram.update', [$p, 16]), [
            'findings' => [$f->key()], 'surfaces' => [$f->key() => ['O']],
        ])->assertSessionHasNoErrors();

        $tooth = $p->teeth()->where('tooth', 16)->first();
        $this->assertSame(['O' => $f->key()], $tooth->faceColors());
        $this->get(route('patients.odontogram', $p))->assertSee('Amalgama en mal estado O')->assertSee('fill:#6B7280', false);

        // Desactivado: sigue describiéndose en el diente que ya lo tiene.
        $this->put(route('settings.findings.update', $f), ['name' => $f->name, 'abbr' => 'AM', 'color' => '#6B7280'])->assertSessionHasNoErrors();
        $this->assertFalse($f->fresh()->active);
        Clinical::forgetFindings();
        $this->get(route('patients.odontogram', $p))->assertSee('Amalgama en mal estado O');

        // Otra clínica no ve ese hallazgo.
        $other = $this->clinic('Otra');
        Clinical::forgetFindings();
        $this->actingAs($other['doctor'])->get(route('settings'))->assertDontSee('value="Amalgama en mal estado"', false);
    }

    public function test_blank_orders_with_templates(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->post(route('settings.order-templates.store'), ['title' => 'Fórmula post extracción', 'body' => 'Ibuprofeno 400 mg cada 8 horas'])
            ->assertSessionHasNoErrors();
        $this->get(route('orders.create', [$p, 'tipo' => 'libre']))->assertOk()->assertSee('Fórmula post extracción');

        $this->post(route('orders.store', $p), ['kind' => 'libre', 'title' => 'Orden de laboratorio', 'body' => ''])->assertSessionHasErrors('body');
        $this->post(route('orders.store', $p), ['kind' => 'libre', 'title' => 'Orden de laboratorio', 'body' => "Hemograma\nTiempos de coagulación"])
            ->assertSessionHasNoErrors();

        $o = RadiologyOrder::firstOrFail();
        $this->assertTrue($o->isFree());
        $this->assertSame('OR-00001', $o->code());
        $this->assertSame('Orden de laboratorio', $o->summary());
        $this->get(route('patients.orders', $p))->assertSee('Orden de laboratorio')->assertSee('Orden en blanco');

        // La radiografía sigue funcionando y comparte el consecutivo.
        $this->post(route('orders.store', $p), ['kind' => 'radiologia', 'studies' => [['name' => 'Radiografía panorámica', 'qty' => 1]], 'delivery' => 'paciente_recoge'])
            ->assertSessionHasNoErrors();
        $this->assertSame('RX-00002', RadiologyOrder::orderByDesc('id')->first()->code());

        $this->actingAs($c['assistant'])->post(route('orders.store', $p), ['kind' => 'libre', 'title' => 'X', 'body' => 'Y'])->assertForbidden();
        $this->actingAs($c['assistant'])->delete(route('settings.order-templates.destroy', OrderTemplate::first()))->assertForbidden();
    }
}
