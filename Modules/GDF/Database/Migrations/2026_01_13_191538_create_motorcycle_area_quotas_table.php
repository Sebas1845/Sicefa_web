<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('motorcycle_area_quotas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('area_id');

            // Si quieres cupo por año (recomendado)
            $table->year('year')->nullable();

            $table->unsignedInteger('quota_total')->default(0);

            $table->boolean('active')->default(true);

            // Quién definió/actualizó el cupo (Subdirección o superadmin)
            $table->unsignedBigInteger('set_by')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('area_id')->references('id')->on('areas')->restrictOnDelete();
            $table->foreign('set_by')->references('id')->on('users')->nullOnDelete();

            // Evita duplicados del mismo cupo para el mismo año/área
            $table->unique(['area_id', 'year'], 'maq_area_year_unique');

            $table->index(['active', 'year'], 'maq_active_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycle_area_quotas');
    }
};
