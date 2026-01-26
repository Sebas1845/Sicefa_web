<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_item_years', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('budget_item_id');
            $table->year('year');

            // Rubro habilitado para usarse/seleccionarse en ese año
            $table->boolean('active')->default(true);

            // Opcional: si quieres acotar por fechas dentro del año
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->foreign('budget_item_id')->references('id')->on('budget_items')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['budget_item_id', 'year'], 'biy_item_year_unique');
            $table->index(['year', 'active'], 'biy_year_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_item_years');
    }
};
