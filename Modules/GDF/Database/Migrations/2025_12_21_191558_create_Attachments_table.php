<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAttachmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')
                ->constrained('travel_requests')
                ->cascadeOnDelete();

            $table->string('file_path', 255);
            $table->string('file_type', 60)->nullable();

            // Clasificación / control de obligatoriedad
            $table->enum('category', ['ticket','invoice','fuel','lodging','supporting'])->default('supporting');
            $table->enum('required_for', ['submit','execute'])->nullable(); // si es obligatorio para enviar o ejecutar
            $table->boolean('approved')->default(false);
            $table->decimal('amount', 14, 2)->nullable(); // opcional: valor del comprobante

            $table->foreignId('uploaded_by')->constrained('users');

            $table->timestamps();

            $table->index(['travel_request_id']);
            $table->index(['category']);
            $table->index(['required_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
}
