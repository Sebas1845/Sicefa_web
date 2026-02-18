<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_request_documents', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación principal
            $table->unsignedBigInteger('travel_request_id');

            // Tipo lógico del documento
            // ejemplo: agenda, invitacion, autorizacion, justificacion, otro
            $table->string('document_type', 50)->index();

            // Archivo
            $table->string('title', 150)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('stored_name', 255)->nullable();
            $table->string('path', 500);
            $table->string('disk', 50)->default('public');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // Estado del documento
            $table->enum('status', [
                'draft',        // borrador (autosave)
                'submitted',    // subido por instructor
                'approved',     // validado por apoyo / coordinación
                'rejected'      // rechazado
            ])->default('submitted')->index();

            // Observaciones
            $table->text('notes')->nullable();         // instructor
            $table->text('review_notes')->nullable();  // apoyo / coordinación

            // Auditoría (USAMOS PEOPLE)
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Si es obligatorio para cerrar/enviar
            $table->boolean('is_required')->default(false)->index();

            $table->timestamps();
            $table->softDeletes();

            // =====================
            // Foreign Keys
            // =====================
            $table->foreign('travel_request_id')
                ->references('id')->on('travel_requests')
                ->cascadeOnDelete();

            $table->foreign('uploaded_by')
                ->references('id')->on('people')
                ->nullOnDelete();

            $table->foreign('reviewed_by')
                ->references('id')->on('people')
                ->nullOnDelete();

            // Si quieres 1 documento por tipo por solicitud, descomenta:
            // $table->unique(['travel_request_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_request_documents');
    }
};
