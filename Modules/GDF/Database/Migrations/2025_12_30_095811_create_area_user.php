<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gdf_area_user', function (Blueprint $table) {
            $table->id();

            // Usuario del sistema (App\Models\User)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Área GDF (tu tabla areas)
            $table->foreignId('area_id')
                ->constrained('areas')
                ->cascadeOnDelete();

            // Quién asignó (opcional pero recomendado para auditoría)
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Estado de asignación
            $table->boolean('active')->default(true);

            // Opcional: "tipo" de vínculo (si quieres diferenciar soporte/coordinador/instructor)
            // si no lo necesitas ahora, déjalo y listo
            $table->string('scope', 30)->nullable(); // ej: coordinator|support|instructor

            $table->timestamps();

            // Evita duplicados
            $table->unique(['user_id', 'area_id'], 'gdf_area_user_unique');
            $table->index(['area_id', 'active']);
            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdf_area_user');
    }
};
