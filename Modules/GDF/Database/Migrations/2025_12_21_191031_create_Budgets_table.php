<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBudgetsTable extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('year')->index();
            $table->foreignId('area_id')->constrained('areas');
            $table->foreignId('budget_item_id')->constrained('budget_items');

            $table->decimal('initial_amount', 14, 2)->default(0);
            $table->decimal('current_amount', 14, 2)->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['year', 'area_id', 'budget_item_id']);
            $table->index(['active']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
}
