<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBudgetItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();

            $table->boolean('allow_staff')->default(true);      // planta
            $table->boolean('allow_contractors')->default(true); // contratista
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['active']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
}
