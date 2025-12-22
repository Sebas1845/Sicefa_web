<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBudgetMovementsTable extends Migration
{
    public function up(): void
    {
        Schema::create('budget_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();

            $table->foreignId('travel_request_id')->nullable()
                  ->constrained('travel_requests')->nullOnDelete();

            $table->enum('type', ['addition', 'commitment', 'reversal', 'adjustment']);

            $table->decimal('amount', 14, 2);
            $table->text('description')->nullable();

            $table->foreignId('created_by')->constrained('users');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['budget_id', 'type']);
            $table->index(['travel_request_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('budget_movements');
    }
}
