<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Pausa: un tratamiento en pausa congela su cobro (sale de cartera) hasta que el paciente vuelva.
 *   Si vuelve con cotización nueva, el plan viejo se cierra con lo que ya se hizo.
 * - Gastos por tipo (consultorio, negocio, personal) con categorías que la doctora edita.
 * - Costo por paciente: tiempo de consultorio por hora (tarifa por sede) y otros costos ligados al paciente o la cita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_total')->nullable();
        });

        Schema::table('sedes', function (Blueprint $table) {
            $table->unsignedInteger('hourly_rate')->default(0); // tarifa del consultorio por hora
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12); // consultorio, negocio, personal
            $table->string('name', 80);
            $table->boolean('private')->default(false); // la asistente no la ve
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['clinic_id', 'type', 'name']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('type', 12)->default('negocio')->after('sede_id');
            $table->foreignId('patient_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
            $table->decimal('hours', 5, 2)->nullable()->after('amount');
        });

        $defaults = self::defaults();
        $now = now();
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            foreach ($defaults as $type => $names) {
                foreach ($names as $name) {
                    DB::table('expense_categories')->insert([
                        'clinic_id' => $clinicId, 'type' => $type, 'name' => $name, 'private' => $name === 'Nómina',
                        'active' => true, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        // Gastos que ya existen: tipo según su categoría.
        DB::table('expenses')->whereIn('category', $defaults['consultorio'])->update(['type' => 'consultorio']);
    }

    /** @return array<string, list<string>> */
    public static function defaults(): array
    {
        return [
            'consultorio' => ['Tiempo de consultorio', 'Arriendo', 'Servicios públicos', 'Materiales e insumos', 'Laboratorio dental',
                'Especialistas', 'Mantenimiento de equipos', 'Nómina'],
            'negocio' => ['Publicidad', 'Software', 'Impuestos', 'Contador y asesorías', 'Transporte', 'Capacitación', 'Otros'],
            'personal' => ['Alimentación', 'Hogar', 'Transporte personal', 'Salud', 'Cuidado personal', 'Entretenimiento', 'Otros personales'],
        ];
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropConstrainedForeignId('patient_id');
            $table->dropColumn(['type', 'hours']);
        });
        Schema::dropIfExists('expense_categories');
        Schema::table('sedes', fn (Blueprint $t) => $t->dropColumn('hourly_rate'));
        Schema::table('quotes', fn (Blueprint $t) => $t->dropColumn(['paused_at', 'closed_at', 'closed_total']));
    }
};
