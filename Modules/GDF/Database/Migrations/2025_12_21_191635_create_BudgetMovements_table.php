<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_movements', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('budget_id');
            $table->unsignedBigInteger('area_id')->nullable();          // área ORIGEN que hizo el movimiento
            $table->unsignedBigInteger('travel_request_id')->nullable();

            $table->string('module', 20)->nullable(); // gdf|sitrav|etc

            $table->enum('type', ['addition','commitment','reversal','adjustment','execute']);

            $table->decimal('amount', 14, 2);
            $table->text('description')->nullable();

            // quién hizo el movimiento
            $table->unsignedBigInteger('created_by');

            // Referencia genérica del origen (para adiciones y otros)
            $table->string('source_type', 30)->nullable(); // budget_addition|travel_request|manual|etc
            $table->unsignedBigInteger('source_id')->nullable();

            // Solo created_at (como tu tabla)
            $table->timestamp('created_at')->useCurrent();

            // Índices
            $table->index('budget_id');
            $table->index('area_id');
            $table->index('travel_request_id');
            $table->index('created_by');
            $table->index(['source_type', 'source_id']);

            // Foreign keys (SIN nombre manual)
            $table->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');

            // Ajusta tabla areas si aplica
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('set null');

            // Ajusta tabla travel_requests si aplica
            $table->foreign('travel_request_id')->references('id')->on('travel_requests')->onDelete('set null');

            // Ajusta si created_by apunta a people
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_movements');
    }
};
