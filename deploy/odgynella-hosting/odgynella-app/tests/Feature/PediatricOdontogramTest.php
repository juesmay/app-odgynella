<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientTooth;
use App\Support\Clinical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PediatricOdontogramTest extends TestCase
{
    use RefreshDatabase;

    private function child(array $c, int $years): Patient
    {
        $p = $this->patient($c['clinic'], '3009990000', 'Niño Prueba');
        $p->update(['birth_date' => today()->subYears($years)->subMonth()]);

        return $p->fresh();
    }

    public function test_dentition_is_suggested_by_age(): void
    {
        $this->assertSame('temporal', Clinical::dentitionForAge(4));
        $this->assertSame('mixta', Clinical::dentitionForAge(8));
        $this->assertSame('permanente', Clinical::dentitionForAge(30));
        $this->assertSame('permanente', Clinical::dentitionForAge(null));

        $this->assertCount(2, Clinical::chart('temporal'));
        $this->assertCount(4, Clinical::chart('mixta'));
        $this->assertSame([55, 54, 53, 52, 51], Clinical::chart('temporal')[0]['quads'][0]);
        $this->assertContains(85, Clinical::allTeeth());
        $this->assertTrue(Clinical::mesialOnRight(84));
        $this->assertTrue(Clinical::isUpper(63));
    }

    public function test_child_sees_mixed_chart_with_primary_teeth(): void
    {
        $c = $this->clinic();
        $p = $this->child($c, 7);

        $this->actingAs($c['assistant'])->get(route('patients.odontogram', $p))
            ->assertOk()->assertViewHas('dentition', 'mixta')
            ->assertSee('data-tooth="85"', false)->assertSee('data-tooth="16"', false)
            ->assertSee('ceo-d')->assertSee('COP-D');
    }

    public function test_doctor_marks_primary_teeth_and_adult_only_findings_are_ignored(): void
    {
        $c = $this->clinic();
        $p = $this->child($c, 4);

        $this->actingAs($c['doctor'])->put(route('patients.odontogram.update', [$p, 84]), [
            'findings' => ['pulpotomia', 'corona_acero', 'implante'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($c['doctor'])->put(route('patients.odontogram.update', [$p, 54]), [
            'findings' => ['caries'], 'surfaces' => ['caries' => ['O', 'D']],
        ])->assertSessionHasNoErrors();

        $t = PatientTooth::where('tooth', 84)->firstOrFail();
        $this->assertSame(['pulpotomia', 'corona_acero'], collect($t->findings)->pluck('type')->all());

        $this->actingAs($c['assistant'])->put(route('patients.odontogram.update', [$p, 55]), ['findings' => ['caries']])->assertForbidden();
        $this->actingAs($c['doctor'])->put(route('patients.odontogram.update', [$p, 59]), ['findings' => ['caries']])->assertNotFound();
    }

    public function test_caries_indexes(): void
    {
        $c = $this->clinic();
        $p = $this->child($c, 8);
        foreach ([[54, 'caries'], [74, 'extraccion'], [84, 'pulpotomia'], [36, 'caries'], [46, 'ausente'], [16, 'sellante']] as [$n, $type]) {
            $p->teeth()->create(['tooth' => $n, 'findings' => [['type' => $type, 'surfaces' => $type === 'caries' || $type === 'sellante' ? ['O'] : []]]]);
        }

        $i = Clinical::cariesIndex($p->teeth()->get());
        $this->assertSame(['c' => 1, 'e' => 1, 'o' => 1, 'total' => 3], $i['ceo']);
        $this->assertSame(['c' => 1, 'o' => 0, 'p' => 1, 'total' => 2], $i['cop']);
    }

    public function test_doctor_can_change_dentition_and_assistant_cannot(): void
    {
        $c = $this->clinic();
        $p = $this->child($c, 7);

        $this->actingAs($c['assistant'])->put(route('patients.odontogram.dentition', $p), ['dentition' => 'temporal'])->assertForbidden();
        $this->actingAs($c['doctor'])->put(route('patients.odontogram.dentition', $p), ['dentition' => 'temporal'])->assertRedirect();

        $this->assertSame('temporal', $p->fresh()->dentitionShown());
        $this->actingAs($c['assistant'])->get(route('patients.odontogram', [$p, 'denticion' => 'permanente']))->assertViewHas('dentition', 'permanente');
    }
}
