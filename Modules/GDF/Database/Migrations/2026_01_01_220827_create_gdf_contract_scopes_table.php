<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('gdf_contract_scopes', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('contractor_id');
      $table->unsignedBigInteger('area_id');
      $table->unsignedBigInteger('budget_item_id');

      $table->boolean('is_allowed')->default(true);

      // Opcionales (si quieres control de topes)
      $table->decimal('max_amount', 14, 2)->nullable();

      $table->timestamps();

      $table->unique(['contractor_id','area_id','budget_item_id'], 'uq_scope_contract_area_rubro');

      $table->foreign('contractor_id')->references('id')->on('contractors');
      $table->foreign('area_id')->references('id')->on('areas');
      $table->foreign('budget_item_id')->references('id')->on('budget_items');
    });
  }

  public function down(): void {
    Schema::dropIfExists('gdf_contract_scopes');
  }
};
