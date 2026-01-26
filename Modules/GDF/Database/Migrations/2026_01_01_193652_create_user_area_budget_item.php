<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * user_area_budget_items
     *
     * Propósito:
     * - Asignar a un usuario (instructor / apoyo / coordinación) su alcance de operación
     *   por Área y Rubro (budget_item).
     *
     * Casos soportados:
     * 1) Instructor en un área con un rubro específico:
     *    - area_id = X, budget_item_id = Y, applies_all_budget_items = 0
     *
     * 2) Usuario que puede manejar TODOS los rubros del área:
     *    - area_id = X, budget_item_id = NULL, applies_all_budget_items = 1
     *
     * 3) Rol de Apoyo que puede manejar una o ambas áreas:
     *    - Para una sola área: area_id = X
     *    - Para ambas áreas: applies_all_areas = 1 y area_id = NULL
     *
     * Nota:
     * - Esta tabla NO reemplaza roles globales (gdf.support, gdf.coord, etc.); los complementa
     *   con “scopes” (alcances) por área/rubro.
     */
    public function up(): void
    {
        Schema::create('user_area_budget_items', function (Blueprint $table) {
            $table->id();

            // Usuario al que se le asigna el alcance
            $table->unsignedBigInteger('user_id');

            /**
             * Área:
             * - Si applies_all_areas = 0 => area_id requerido (no null)
             * - Si applies_all_areas = 1 => area_id debe ser NULL (aplica a todas las áreas)
             */
            $table->unsignedBigInteger('area_id')->nullable();

            /**
             * Rubro:
             * - Si applies_all_budget_items = 0 => budget_item_id requerido (no null)
             * - Si applies_all_budget_items = 1 => budget_item_id debe ser NULL (aplica a todos los rubros del área)
             */
            $table->unsignedBigInteger('budget_item_id')->nullable();

            /**
             * Tipo de alcance funcional dentro de esa asignación:
             * - support: apoyo (puede registrar / gestionar según tu policy)
             * - coord  : coordinación (revisión/aprobación/gestión)
             * - instructor: ejecución/operación como instructor
             *
             * Si prefieres, puedes cambiarlo por role_slug y guardar 'gdf.support', etc.
             */
            $table->enum('scope_role', ['support', 'coord', 'instructor'])->default('instructor');

            // Flags de “globalidad”
            $table->boolean('applies_all_areas')->default(false);
            $table->boolean('applies_all_budget_items')->default(false);

            // Estado
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // FKs (ajusta nombres de tablas si en tu proyecto son diferentes)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('cascade');
            $table->foreign('budget_item_id')->references('id')->on('budget_items')->onDelete('cascade');

            // Índices para consultas frecuentes
            $table->index(['user_id', 'is_active']);
            $table->index(['area_id', 'budget_item_id']);
            $table->index(['scope_role', 'is_active']);

            /**
             * Unicidad práctica:
             * Evita duplicados idénticos por usuario/área/rubro/rol.
             * - Para casos "all areas" y/o "all rubros", el NULL participa (MySQL permite múltiples NULL),
             *   así que esta unique es “semi-efectiva”.
             *
             * Si quieres unicidad estricta con NULL, se resuelve con columnas “normalizadas”
             * (area_key, budget_key) o con validación en el controlador.
             */
            $table->unique(
                ['user_id', 'area_id', 'budget_item_id', 'scope_role'],
                'uq_user_area_budget_role'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_area_budget_items');
    }
};
