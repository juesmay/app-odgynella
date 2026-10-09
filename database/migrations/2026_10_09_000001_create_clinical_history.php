<?php

use App\Support\DefaultConsents;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2: historia clínica. Antecedentes ampliados, examen, diagnósticos CIE-10,
     * odontograma por caras, evoluciones firmadas, consentimientos con firma y fotos.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->json('infectious')->nullable();     // VIH, hepatitis B/C, tuberculosis, sífilis (solo la doctora lo ve)
            $table->json('oncology')->nullable();       // {status, ended_on, treatments[]}
            $table->json('family_history')->nullable(); // {conditions[], detail}
            $table->text('hereditary_history')->nullable();
            $table->json('exam')->nullable();           // examen clínico
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12)->nullable();
            $table->string('name');
            $table->string('tooth', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes(); // si se descarta queda en el historial
        });

        Schema::create('patient_teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tooth');   // numeración FDI: 11 a 48
            $table->json('findings')->nullable();   // [{type, surfaces:[O,M,D,V,L]}]
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_id', 'tooth']);
        });

        Schema::create('evolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // valoracion, sesion, control
            $table->json('fields');
            $table->json('procedures')->nullable(); // procedimientos marcados como realizados
            $table->foreignId('signed_by')->constrained('users');
            $table->string('signed_name');
            $table->timestamp('signed_at');
            $table->timestamps();
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('evolution_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('consent_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consent_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body'); // copia del texto tal como se firmó
            $table->longText('patient_signature');
            $table->longText('doctor_signature');
            $table->string('doctor_name');
            $table->foreignId('signed_by')->constrained('users');
            $table->timestamp('signed_at');
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('stage', 20); // antes, durante, despues, control
            $table->date('taken_on');
            $table->string('note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Plantillas de consentimiento iniciales para los consultorios que ya existen.
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            foreach (DefaultConsents::all() as [$name, $body]) {
                DB::table('consent_templates')->insert([
                    'clinic_id' => $clinicId, 'name' => $name, 'body' => $body, 'active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('consent_templates');
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evolution_id');
            $table->dropConstrainedForeignId('done_by');
            $table->dropColumn('done_at');
        });
        Schema::dropIfExists('evolutions');
        Schema::dropIfExists('patient_teeth');
        Schema::dropIfExists('diagnoses');
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['infectious', 'oncology', 'family_history', 'hereditary_history', 'exam']);
        });
    }
};
