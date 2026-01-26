<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contract_documents', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('contractor_id');
      $table->unsignedBigInteger('amendment_id')->nullable();

      $table->string('name', 255);
      $table->string('type', 80)->nullable(); // 'contract','policy','amendment','act', etc.
      $table->string('path', 255);
      $table->unsignedBigInteger('uploaded_by')->nullable();
      $table->timestamps();

      $table->foreign('contractor_id')->references('id')->on('contractors');
      $table->foreign('amendment_id')->references('id')->on('contract_amendments')->nullOnDelete();
    });
  }

  public function down(): void {
    Schema::dropIfExists('contract_documents');
  }
};
