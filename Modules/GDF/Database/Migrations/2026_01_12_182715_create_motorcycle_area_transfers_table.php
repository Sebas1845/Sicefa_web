<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('motorcycle_area_transfers', function (Blueprint $table) {
      $table->id();

      $table->unsignedBigInteger('motorcycle_id');
      $table->unsignedBigInteger('from_area_id')->nullable();
      $table->unsignedBigInteger('to_area_id');

      $table->unsignedBigInteger('assigned_by')->nullable(); // users.id (subdirection)
      $table->timestamp('assigned_at')->useCurrent();

      $table->text('notes')->nullable();
      $table->timestamps();

      $table->foreign('motorcycle_id')->references('id')->on('motorcycles')->cascadeOnDelete();
      $table->foreign('from_area_id')->references('id')->on('areas')->nullOnDelete();
      $table->foreign('to_area_id')->references('id')->on('areas')->restrictOnDelete();
      $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();

      $table->index(['motorcycle_id', 'assigned_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('motorcycle_area_transfers');
  }
};
