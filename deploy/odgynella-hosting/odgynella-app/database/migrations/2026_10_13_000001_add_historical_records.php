<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Información histórica (antes de empezar a usar el sistema).
 * Queda en la cuenta de cada paciente y en la cartera, pero no entra a Reportes, caja ni al archivo del contador.
 * - Pacientes históricos pueden no tener WhatsApp todavía.
 * - Cotizaciones, pagos y gastos históricos no gastan numeración (COT, RC) y guardan su código de origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->change();
            $table->string('phone_key', 30)->nullable()->change();
            $table->boolean('historical')->default(false);
            $table->text('notes')->nullable();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->change();
            $table->boolean('historical')->default(false);
            $table->string('legacy_ref', 30)->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('receipt_number')->nullable()->change();
            $table->boolean('historical')->default(false);
            $table->string('legacy_ref', 30)->nullable();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('historical')->default(false);
            $table->string('legacy_ref', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn(['historical', 'legacy_ref']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['historical', 'legacy_ref']));
        Schema::table('quotes', fn (Blueprint $t) => $t->dropColumn(['historical', 'legacy_ref']));
        Schema::table('patients', fn (Blueprint $t) => $t->dropColumn(['historical', 'notes']));
    }
};
