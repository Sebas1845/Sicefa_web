<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTravelRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('area_id')->constrained('areas');
            $table->foreignId('budget_item_id')->constrained('budget_items');
            $table->foreignId('budget_id')->nullable()->constrained('budgets');

            // Person references (SICEFA)
            $table->foreignId('person_id')->constrained('people');

            $table->enum('person_type', ['staff', 'contractor']); // staff=planta

            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors');

            $table->enum('request_type', ['travel', 'training', 'event']);

            $table->string('origin', 150);
            $table->string('destination', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();

            $table->enum('status', ['draft','submitted','returned','rejected','approved','executed','cancelled'])
                  ->default('draft');

            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->decimal('total_transport', 14, 2)->default(0);
            $table->decimal('total_per_diem', 14, 2)->default(0);
            $table->decimal('total_other', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['area_id', 'status']);
            $table->index(['person_id', 'person_type']);
            $table->index(['start_date', 'end_date']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('travel_requests');
    }
}