<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contract_amendments', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('contractor_id');
      $table->enum('type', ['ADDITION','EXTENSION','OTHER']);
      $table->decimal('amount_addition', 14, 2)->nullable();
      $table->date('new_end_date')->nullable();
      $table->text('notes')->nullable();
      $table->unsignedBigInteger('created_by')->nullable();
      $table->timestamps();

      $table->foreign('contractor_id')->references('id')->on('contractors');
      // created_by -> users.id (si aplica)
    });
  }

  public function down(): void {
    Schema::dropIfExists('contract_amendments');
  }
};
