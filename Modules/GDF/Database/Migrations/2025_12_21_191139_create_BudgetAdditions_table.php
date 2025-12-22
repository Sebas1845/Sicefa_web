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
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->text('justification')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['budget_id', 'approved_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('budget_additions');
    }
}