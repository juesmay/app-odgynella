<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prospectos (personas en cotización) y cotizaciones con PDF.
     * Un prospecto solo necesita nombre y WhatsApp; el documento y la autorización
     * de datos se piden cuando agenda la valoración presencial.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('stage', 30)->default('prospecto');
            $table->timestamp('stage_changed_at')->nullable();
            $table->string('lost_reason')->nullable();
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->string('doc_type', 20)->nullable()->change();
            $table->string('doc_number', 40)->nullable()->change();
        });

        // Quienes ya estaban registrados completos (fase 1) quedan con valoración agendada.
        DB::table('patients')->whereNotNull('data_consent_at')->update(['stage' => 'agendado']);

        Schema::table('clinics', function (Blueprint $table) {
            $table->unsignedInteger('next_quote')->default(1);
            $table->unsignedSmallInteger('quote_validity_days')->default(30);
            $table->string('payment_options')->default('Efectivo, transferencia, tarjeta y financiado');
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->date('issued_on');
            $table->date('valid_until');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->string('discount_label')->nullable();
            $table->unsignedBigInteger('total');
            $table->text('patient_notes')->nullable();  // sale en el PDF
            $table->text('internal_notes')->nullable(); // lo que contó la paciente; no sale en el PDF
            $table->string('status', 20)->default('vigente'); // vigente, aceptada, no_aceptada
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'number']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('teeth', 60)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['next_quote', 'quote_validity_days', 'payment_options']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['stage', 'stage_changed_at', 'lost_reason']);
        });
    }
};
