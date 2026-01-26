<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('motorcycles', function (Blueprint $table) {
      $table->id();

      $table->string('plate', 30)->unique();
      $table->string('brand', 60)->nullable();
      $table->string('model', 80)->nullable();
      $table->date('entry_date')->nullable();

      // Current owner area (Subdirection assigns the motorcycle to an area)
      $table->unsignedBigInteger('current_area_id')->nullable();

      // Odometer
      $table->unsignedInteger('current_odometer')->default(0);

      // Status
      $table->string('status', 20)->default('available');
      // available | assigned | maintenance | retired

      $table->unsignedBigInteger('created_by')->nullable(); // users.id (subdirection)
      $table->timestamps();

      // NOTE: We keep referencing your existing tables
      $table->foreign('current_area_id')->references('id')->on('areas')->nullOnDelete();
      $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

      $table->index(['status', 'current_area_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('motorcycles');
  }
};
