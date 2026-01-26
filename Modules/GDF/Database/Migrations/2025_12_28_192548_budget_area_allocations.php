<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_area_allocations', function (Blueprint $table) {

            $table->id();

            // Relación con el presupuesto base
            $table->foreignId('budget_id')
                ->constrained('budgets')
                ->cascadeOnDelete();

            // Área a la que se le asigna el dinero
            $table->foreignId('area_id')
                ->constrained('areas');

            // Porcentaje del presupuesto para esta área (0 - 100)
            $table->decimal('percentage', 5, 2)->default(0);

            // Valor calculado (referencia, se recalcula si cambia el budget)
            $table->decimal('allocated_amount', 14, 2)->default(0);

            // Control lógico
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Evita duplicar área dentro del mismo budget
            $table->unique(['budget_id', 'area_id'], 'budget_area_unique');

            // Índices útiles
            $table->index(['budget_id', 'active']);
            $table->index(['area_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_area_allocations');
    }
};
