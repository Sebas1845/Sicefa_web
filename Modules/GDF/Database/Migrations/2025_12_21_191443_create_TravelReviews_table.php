<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTravelReviewsTable extends Migration
{
    public function up(): void
    {
        Schema::create('travel_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')
                ->constrained('travel_requests')
                ->cascadeOnDelete();

            $table->foreignId('reviewer_id')->constrained('users');

            $table->enum('action', ['submitted','returned','rejected','approved']);
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->index(['travel_request_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_reviews');
    }
}
