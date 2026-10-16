<?php

use App\Support\DefaultConsents;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consentimientos de la doctora con campos para llenar en pantalla:
 * - consent_templates.form: qué formulario usa (endodoncia, extracción…). Vacío = plantilla de texto libre.
 * - consents.form_data: lo que se llenó (datos, decisión, representante, asentimiento del menor, testigo).
 * Carga los 9 consentimientos y desactiva las plantillas de ejemplo que nunca se completaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_templates', function (Blueprint $table) {
            $table->string('form', 40)->nullable()->after('name');
        });
        Schema::table('consents', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('body');
        });

        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            DefaultConsents::seedForms($clinicId);
            DB::table('consent_templates')->where('clinic_id', $clinicId)->whereNull('form')
                ->where('body', 'like', '%[Escriba aquí%')->update(['active' => false]);
        }
    }

    public function down(): void
    {
        DB::table('consent_templates')->whereNotNull('form')->delete();
        Schema::table('consents', fn (Blueprint $t) => $t->dropColumn('form_data'));
        Schema::table('consent_templates', fn (Blueprint $t) => $t->dropColumn('form'));
    }
};
