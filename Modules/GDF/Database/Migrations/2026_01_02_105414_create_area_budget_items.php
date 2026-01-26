<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivote: qué rubros (budget_items) puede ejecutar cada área (areas).
     * Subdirección administra esto. Coordinación/Apoyos solo consumen.
     */
    public function up(): void
    {
        Schema::create('area_budget_items', function (Blueprint $table) {
            $table->id();

            // Relación Área -> Rubro permitido
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('budget_item_id');

            // Control de habilitado/deshabilitado
            $table->boolean('active')->default(true);

            // Auditoría básica (opcional pero recomendado)
            $table->unsignedBigInteger('created_by')->nullable(); // user_id que creó la relación
            $table->unsignedBigInteger('updated_by')->nullable(); // user_id que la actualizó

            $table->timestamps();

            // Índices y unicidad (evita duplicados área+rubro)
            $table->unique(['area_id', 'budget_item_id'], 'uniq_area_budget_item');
            $table->index(['area_id'], 'idx_abi_area');
            $table->index(['budget_item_id'], 'idx_abi_budget_item');
            $table->index(['active'], 'idx_abi_active');

            // Foreign keys
            $table->foreign('area_id')
                ->references('id')->on('areas')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('budget_item_id')
                ->references('id')->on('budget_items')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Auditoría a users (si tu tabla es users y existe)
            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_budget_items');
    }
};
