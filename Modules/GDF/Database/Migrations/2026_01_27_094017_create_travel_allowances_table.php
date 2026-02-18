<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_allowances', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación con la solicitud principal
            $table->unsignedBigInteger('travel_request_id')->index();

            // Tipo de viático (1 fila por tipo)
            $table->enum('allowance_type', [
                'lodging',   // alojamiento
                'meals',     // alimentación
                'per_diem',  // viático diario
                'other',     // otros
            ])->index();

            // Estado (flujo)
            $table->enum('status', [
                'draft',
                'liquidated',
                'approved',
                'rejected',
            ])->default('draft')->index();

            // Monto unitario y unidades
            $table->decimal('unit_amount', 12, 2)->default(0);
            $table->unsignedInteger('units')->default(1);

            // Total calculado y total aprobado (si se ajusta)
            $table->decimal('calculated_amount', 12, 2)->default(0);
            $table->decimal('approved_amount', 12, 2)->nullable();

            // Auditoría / explicación
            $table->string('description', 255)->nullable();

            // Solo planta (si en el futuro amplías, cambias enum)
            $table->enum('applies_to', ['staff'])->default('staff');

            // Control presupuestal (nullable por compatibilidad)
            $table->unsignedBigInteger('budget_item_id')->nullable()->index();

            // Auditoría opcional
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // FKs
            $table->foreign('travel_request_id')
                ->references('id')->on('travel_requests')
                ->onDelete('cascade');

            // Si existe users:
            // $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            // Si existe budget_items:
            // $table->foreign('budget_item_id')->references('id')->on('budget_items')->nullOnDelete();

            // Evita repetir el mismo tipo de viático por solicitud
            $table->unique(['travel_request_id', 'allowance_type'], 'uq_travel_allowances_request_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_allowances');
    }
};
