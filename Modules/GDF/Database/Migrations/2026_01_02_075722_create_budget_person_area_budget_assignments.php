<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('person_area_budget_assignments', function (Blueprint $table) {
            $table->id();

            // Persona (base común para contratistas y planta)
            $table->foreignId('person_id')
                ->constrained('people')   // Ajusta si tu tabla se llama persons/people
                ->cascadeOnDelete();

            // Opcional: si quieres amarrar a un contrato específico (solo contratistas)
            $table->foreignId('contractor_id')
                ->nullable()
                ->constrained('contractors')
                ->nullOnDelete();

            // Área y Rubro
            $table->foreignId('area_id')
                ->constrained('areas')
                ->restrictOnDelete();

            $table->foreignId('budget_item_id')
                ->constrained('budget_items')
                ->restrictOnDelete();

            // Opcional: supervisor/responsable (si lo requieres para “préstamos” y trazabilidad)
            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Vigencia de la asignación (muy útil para histórico y “contrato antiguo”)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Estado operativo
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // Índices útiles
            $table->index(['person_id', 'area_id']);
            $table->index(['person_id', 'budget_item_id']);
            $table->index(['contractor_id']);

            // Regla anti-duplicados (mismo contrato/persona con misma asignación)
            $table->unique(
                ['person_id', 'contractor_id', 'area_id', 'budget_item_id'],
                'uq_person_contract_area_budget'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_area_budget_assignments');
    }
};
