<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTravelLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('travel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')->constrained('travel_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->string('action', 60); // CREATED, UPDATED, SUBMITTED, APPROVED, etc.
            $table->text('description')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['travel_request_id', 'action']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('travel_logs');
    }
}
