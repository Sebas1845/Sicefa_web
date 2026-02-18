<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCertificateRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            
            // Relación con la persona (siempre existe)
            $table->foreignId('person_id')
                ->constrained('people')
                ->onDelete('cascade');
            
            // Relación con el contrato (puede ser NULL si no existe aún)
            $table->foreignId('contractor_id')
                ->nullable()
                ->constrained('contractors')
                ->onDelete('set null');
            
            // Año del contrato solicitado
            $table->integer('contract_year');
            
            // Estado de la solicitud
            $table->enum('status', [
                'solicitado',
                'en_proceso',
                'aprobado',
                'rechazado'
            ])->default('solicitado');
            
            // Fechas
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            
            // Notas
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['person_id', 'contract_year', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificate_requests');
    }
}
