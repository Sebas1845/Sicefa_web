<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('motorcycle_assignments', function (Blueprint $table) {
      $table->id();

      // ✅ Vigencia / año de la asignación (CLAVE para cupos por año)
      // YEAR en MySQL funciona, pero para compatibilidad puedes usar smallInteger.
      $table->unsignedSmallInteger('assignment_year')->index(); // ej: 2026

      $table->unsignedBigInteger('motorcycle_id')->nullable();
      $table->unsignedBigInteger('person_id');
      $table->unsignedBigInteger('area_id');
      $table->unsignedBigInteger('budget_item_id')->nullable();

      // Directa o por solicitud (TR u otro)
      $table->nullableMorphs('travel_requestable', 'ma_travel_req');

      // ✅ Rango reservado (para solicitudes)
      $table->timestamp('start_at')->nullable();
      $table->timestamp('end_at')->nullable();

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

      // FKs
      $table->foreign('motorcycle_id')->references('id')->on('motorcycles')->nullOnDelete();
      $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
      $table->foreign('area_id')->references('id')->on('areas')->restrictOnDelete();
      $table->foreign('budget_item_id')->references('id')->on('budget_items')->nullOnDelete();

      $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
      $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
      $table->foreign('managed_by')->references('id')->on('users')->nullOnDelete();

      // Índices útiles
      $table->index(['assignment_year', 'area_id', 'status'], 'ma_year_area_status');
      $table->index(['motorcycle_id', 'status'], 'ma_motorcycle_status');
      $table->index(['person_id', 'status'], 'ma_person_status');
      $table->index(['motorcycle_id', 'start_at', 'end_at'], 'ma_moto_range');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('motorcycle_assignments');
  }
};
