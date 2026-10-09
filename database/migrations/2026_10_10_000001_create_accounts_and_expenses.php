<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3: los números.
 * - Cada pago puede ligarse a la cotización aceptada (plan de tratamiento) para calcular el saldo.
 * - Gastos por categoría y sede, con soporte opcional. Si se pagan con el efectivo de la caja, se descuentan del cuadre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on');
            $table->string('category', 60);
            $table->unsignedBigInteger('amount');
            $table->string('method', 40);
            $table->string('detail', 200);
            $table->string('supplier', 120)->nullable();
            $table->string('support_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_id');
        });
    }
};
