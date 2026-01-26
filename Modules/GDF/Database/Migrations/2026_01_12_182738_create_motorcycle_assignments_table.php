<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('motorcycle_assignments', function (Blueprint $table) {
      $table->id();

      $table->unsignedBigInteger('motorcycle_id')->nullable();
      $table->unsignedBigInteger('person_id');
      $table->unsignedBigInteger('area_id');
      $table->unsignedBigInteger('budget_item_id')->nullable();

      // IMPORTANT: custom short index name to avoid MySQL 64-char limit
      $table->nullableMorphs('travel_requestable', 'ma_travel_req');

      $table->timestamp('delivered_at')->nullable();
      $table->timestamp('returned_at')->nullable();

      $table->unsignedInteger('odometer_out')->nullable();
      $table->unsignedInteger('odometer_in')->nullable();

      $table->text('observations_out')->nullable();
      $table->text('observations_in')->nullable();

      $table->string('status', 20)->default('pending');
      // pending | approved | delivered | returned | cancelled

      $table->unsignedBigInteger('requested_by')->nullable();
      $table->unsignedBigInteger('approved_by')->nullable();
      $table->unsignedBigInteger('managed_by')->nullable();

      $table->timestamps();

      $table->foreign('motorcycle_id')->references('id')->on('motorcycles')->nullOnDelete();
      $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
      $table->foreign('area_id')->references('id')->on('areas')->restrictOnDelete();
      $table->foreign('budget_item_id')->references('id')->on('budget_items')->nullOnDelete();

      $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
      $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
      $table->foreign('managed_by')->references('id')->on('users')->nullOnDelete();

      $table->index(['motorcycle_id', 'status'], 'ma_motorcycle_status');
      $table->index(['person_id', 'status'], 'ma_person_status');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('motorcycle_assignments');
  }
};
