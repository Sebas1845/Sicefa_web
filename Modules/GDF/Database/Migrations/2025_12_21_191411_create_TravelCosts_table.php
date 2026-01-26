<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTravelCostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('travel_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')
                ->constrained('travel_requests')
                ->cascadeOnDelete();

            // fuel para moto (gasolina como viático)
            $table->enum('cost_type', ['transport','lodging','meals','fuel','other']);
            $table->string('description', 180)->nullable();

            $table->decimal('amount', 14, 2)->default(0);
            $table->enum('applies_to', ['staff', 'both'])->default('both');

            $table->timestamps();

            $table->index(['travel_request_id', 'cost_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_costs');
    }
}
