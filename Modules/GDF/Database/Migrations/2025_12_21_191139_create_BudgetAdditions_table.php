<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBudgetAdditionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('budget_additions', function (Blueprint $table) {
            $table->id();

            // Presupuesto afectado
            $table->foreignId('budget_id')
                ->constrained('budgets')
                ->cascadeOnDelete();

            // Valor de la adición
            $table->decimal('amount', 14, 2);

            // Justificación
            $table->text('justification')->nullable();

            /**
             * 🔍 AUDITORÍA (CLAVE PARA REPORTES)
             */

            // Quién creó la adición (Apoyo)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Desde qué área se creó (académica / campesena)
            $table->foreignId('created_area_id')
                ->nullable()
                ->constrained('areas')
                ->nullOnDelete();

            /**
             * ✅ APLICACIÓN DE LA ADICIÓN
             * (cuando se suma al saldo real)
             */

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            /**
             * 📌 ÍNDICES PARA CONSULTAS Y REPORTES
             */
            $table->index(['budget_id', 'approved_at']);
            $table->index('created_by');
            $table->index('created_area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_additions');
    }
}
