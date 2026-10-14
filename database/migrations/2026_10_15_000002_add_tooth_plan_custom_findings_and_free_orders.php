<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Tratamiento indicado por diente en el odontograma.
 * - Hallazgos propios de la clínica para el odontograma (Configuración).
 * - Órdenes en blanco (receta, laboratorio, remisión…) y plantillas para no escribirlas cada vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_teeth', function (Blueprint $table) {
            $table->json('plan')->nullable(); // [{service_id, name}]
        });

        Schema::create('odontogram_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('abbr', 4);
            $table->boolean('faces')->default(false);
            $table->string('color', 7)->default('#7A5A48');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('radiology_orders', function (Blueprint $table) {
            $table->string('kind', 12)->default('radiologia')->after('number');
            $table->string('title', 150)->nullable()->after('kind');
            $table->text('body')->nullable()->after('title');
            $table->json('studies')->nullable()->change();
            $table->string('delivery', 30)->nullable()->change();
        });

        Schema::create('order_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_templates');
        Schema::table('radiology_orders', fn (Blueprint $t) => $t->dropColumn(['kind', 'title', 'body']));
        Schema::dropIfExists('odontogram_findings');
        Schema::table('patient_teeth', fn (Blueprint $t) => $t->dropColumn('plan'));
    }
};
