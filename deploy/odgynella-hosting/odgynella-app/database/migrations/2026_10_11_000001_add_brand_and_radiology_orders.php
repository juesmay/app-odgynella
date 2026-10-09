<?php

use App\Support\Brand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Datos de la doctora para los documentos: logo, contacto, registro profesional, firma y color.
 * - Órdenes de radiografía con numeración propia.
 * - Correo del paciente (lo piden los centros radiológicos).
 * - Ítems de cotización con "valor según el caso" (ej. endodoncia uni, bi o multirradicular).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('doctor_title', 60)->nullable();
            $table->string('doctor_license', 60)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('instagram', 60)->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->longText('signature')->nullable();
            $table->json('radiology_centers')->nullable();
            $table->string('payment_note', 400)->nullable();
            $table->unsignedInteger('next_order')->default(1);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->after('phone_key');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('price_options', 300)->nullable()->after('line_total');
        });

        Schema::create('radiology_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->date('issued_on');
            $table->string('center', 150)->nullable();
            $table->json('studies');
            $table->string('indication', 500)->nullable();
            $table->string('delivery', 30);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'number']);
        });

        // Consultorio de la Dra. Gynella: carga sus datos y su logo si aún no los tiene.
        foreach (DB::table('clinics')->where('name', 'Odgynella')->whereNull('phone')->get() as $clinic) {
            $data = Brand::odgynellaDefaults($clinic->id);
            $data['radiology_centers'] = json_encode($data['radiology_centers'], JSON_UNESCAPED_UNICODE);
            DB::table('clinics')->where('id', $clinic->id)->update($data);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_orders');
        Schema::table('quote_items', fn (Blueprint $t) => $t->dropColumn('price_options'));
        Schema::table('patients', fn (Blueprint $t) => $t->dropColumn('email'));
        Schema::table('clinics', fn (Blueprint $t) => $t->dropColumn([
            'doctor_title', 'doctor_license', 'phone', 'email', 'instagram', 'brand_color', 'logo_path', 'signature', 'radiology_centers', 'payment_note', 'next_order',
        ]));
    }
};
