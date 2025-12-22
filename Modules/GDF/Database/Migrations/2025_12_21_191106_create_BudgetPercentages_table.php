<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBudgetPercentagesTable extends Migration
{
    public function up(): void
    {
        Schema::create('budget_percentages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();

            $table->decimal('percentage', 5, 2); // 0..100
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['budget_id', 'active']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('budget_percentages');
    }
}
