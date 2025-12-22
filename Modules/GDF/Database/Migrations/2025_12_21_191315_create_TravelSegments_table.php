<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTravelSegmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('travel_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')->constrained('travel_requests')->cascadeOnDelete();

            $table->dateTime('departure_at');
            $table->dateTime('return_at');

            $table->string('origin_place', 150);
            $table->string('destination_place', 150);

            $table->enum('destination_type', ['municipality', 'village', 'other'])->default('other');

            $table->foreignId('municipality_rate_id')->nullable()->constrained('municipality_rates');
            $table->foreignId('village_rate_id')->nullable()->constrained('village_rates');

            $table->string('transport_type', 80)->nullable();

            $table->decimal('transport_cost', 14, 2)->default(0);
            $table->decimal('per_diem_cost', 14, 2)->default(0);
            $table->decimal('other_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['travel_request_id']);
            $table->index(['departure_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('travel_segments');
    }
}
