<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBudgetMovementsTable extends Migration
{
    public function up(): void
    {
        Schema::create('budget_movements', function (Blueprint $table) {
            $table->id();

            // Budget dueño del movimiento
            $table->foreignId('budget_id')
                ->constrained('budgets')
                ->cascadeOnDelete();

            // Área sobre la cual se ejecuta (para sumar ejecutado por área)
            // Nullable para movimientos globales (si existieran)
            $table->foreignId('area_id')
                ->nullable()
                ->constrained('areas')
                ->nullOnDelete();

            // Solicitud asociada (si aplica)
            $table->foreignId('travel_request_id')
                ->nullable()
                ->constrained('travel_requests')
                ->nullOnDelete();

            // Módulo (para dividir el cupo por GDF/SITRAV)
            // Lo dejo string para no pelear con enum en mysql si cambias a futuro
            $table->string('module', 20)->nullable(); // gdf|sitrav

            // Tipo de movimiento
            // Se agrega execute para tus descuentos por solicitud
            $table->enum('type', [
                'addition',     // suma (adición)
                'commitment',   // compromiso/reserva (si lo usas)
                'reversal',     // reversión
                'adjustment',   // ajuste manual
                'execute',      // EJECUCIÓN real (descuento)
            ]);

            $table->decimal('amount', 14, 2);
            $table->text('description')->nullable();

            // Usuario que creó el movimiento
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            // Mantienes created_at manual como lo tenías
            $table->timestamp('created_at')->useCurrent();

            // Índices para consultas típicas
            $table->index(['budget_id', 'type'], 'idx_budget_mov_budget_type');
            $table->index(['budget_id', 'area_id'], 'idx_budget_mov_budget_area');
            $table->index(['budget_id', 'module'], 'idx_budget_mov_budget_module');
            $table->index(['budget_id', 'area_id', 'module', 'type'], 'idx_budget_mov_budget_area_module_type');
            $table->index(['travel_request_id'], 'idx_budget_mov_travel_request');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_movements');
    }
}
