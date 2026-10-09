<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base multi-consultorio: cada registro pertenece a una clínica (clinic_id).
     */
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('doctor_name');
            // Día de la semana (0 = domingo ... 6 = sábado) => id de la sede donde atiende.
            $table->json('schedule')->nullable();
            $table->unsignedInteger('next_receipt')->default(1);
            $table->timestamps();
        });

        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('duration_min')->default(30);
            $table->unsignedBigInteger('price')->default(0); // pesos colombianos, sin decimales
            $table->boolean('is_package')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('doc_type', 20);
            $table->string('doc_number', 40);
            $table->string('phone', 40);
            // Solo dígitos: llave única por consultorio, para enlazar con GoHighLevel.
            $table->string('phone_key', 30);
            $table->date('birth_date')->nullable();
            $table->string('city')->nullable();
            $table->string('source', 30)->nullable();
            $table->timestamp('data_consent_at')->nullable();
            $table->boolean('photo_consent')->default(false);
            $table->json('anamnesis')->nullable();
            $table->string('alert_text')->nullable();
            $table->string('ghl_contact_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'phone_key']);
            $table->index(['clinic_id', 'name']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->date('date');
            $table->string('start_time', 5); // HH:MM
            $table->string('end_time', 5);
            $table->string('status', 20)->default('agendada');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'date']);
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained();
            $table->timestamp('opened_at');
            $table->foreignId('opened_by')->constrained('users');
            $table->unsignedBigInteger('opening_amount');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('expected_cash')->nullable();
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('sede_id')->constrained();
            $table->foreignId('cash_session_id')->nullable()->constrained();
            $table->unsignedInteger('receipt_number');
            $table->date('paid_on');
            $table->unsignedBigInteger('amount');
            $table->string('method', 30);
            $table->string('concept');
            $table->foreignId('created_by')->constrained('users');
            // Nada se borra: un pago equivocado se anula y queda el rastro.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'receipt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('services');
        Schema::dropIfExists('sedes');
        Schema::dropIfExists('clinics');
    }
};
