<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada paciente pertenece a una sede (Envigado, Sabaneta, San Gil, Bucaramanga…).
 * A los que ya existen se les asigna la sede de su última cita; si no tienen, la de su último pago;
 * y si no tienen ninguna, la primera sede de la clínica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->after('clinic_id')->constrained('sedes')->nullOnDelete();
        });

        $firstSede = DB::table('sedes')->orderBy('id')->get(['id', 'clinic_id'])->groupBy('clinic_id')->map(fn ($s) => $s->first()->id);

        foreach (DB::table('patients')->whereNull('sede_id')->get(['id', 'clinic_id']) as $p) {
            $sede = DB::table('appointments')->where('patient_id', $p->id)->whereNotNull('sede_id')
                ->orderByDesc('date')->orderByDesc('id')->value('sede_id')
                ?? DB::table('payments')->where('patient_id', $p->id)->whereNotNull('sede_id')
                    ->orderByDesc('paid_on')->orderByDesc('id')->value('sede_id')
                ?? $firstSede[$p->clinic_id] ?? null;

            if ($sede) {
                DB::table('patients')->where('id', $p->id)->update(['sede_id' => $sede]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });
    }
};
