<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProgramRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('program_requests', function (Blueprint $table) {
            $table->id();

            // Dueño / contexto
            $table->foreignId('person_id')
                ->constrained()
                ->cascadeOnDelete();

            // Área (snapshot operativo)
            $table->foreignId('area_id')
                ->constrained('areas')
                ->restrictOnDelete();

            // Rubro seleccionado
            $table->foreignId('budget_item_id')
                ->constrained('budget_items')
                ->restrictOnDelete();

            // Programa
            $table->foreignId('program_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('special_program_id')
                ->constrained()
                ->cascadeOnDelete();

            // Empresa (normalizado)
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            // Ubicación / ejecución
            $table->foreignId('municipality_id')
                ->constrained('municipalities')
                ->restrictOnDelete();

            // NUEVO: Vereda (opcional, dependiente del municipio)
            $table->foreignId('village_id')
                ->nullable()
                ->constrained('villages')
                ->restrictOnDelete();

            // (Opcional pero recomendado) Tipo de lugar para dejar trazabilidad
            // Si NO quieres guardar el tipo, puedes borrar este campo y derivar:
            // village_id != null => vereda, else => municipio.
            $table->enum('place_type', ['municipio', 'vereda'])
                ->default('municipio');

            $table->integer('hours');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('quotas');

            $table->string('address')->nullable();
            $table->text('observation')->nullable();

            /**
             * Contacto del solicitante (no de la empresa)
             * Nota: Si estos datos son de la empresa, lo ideal es tomarlos desde companies.
             */
            $table->string('applicant')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone', 50)->nullable();

            // Caracterización / códigos
            $table->date('date_characterization')->nullable();
            $table->integer('code_empresa')->nullable();
            $table->integer('code_course')->nullable();
            $table->date('date_inscription')->nullable();

            $table->enum('state', ['Confirmado','Pendiente','Cancelado','Preconfirmado'])
                ->default('Pendiente');

            $table->softDeletes();
            $table->timestamps();

            // Índices típicos para dashboard
            $table->index(['area_id', 'budget_item_id', 'state']);
            $table->index(['special_program_id']);
            $table->index(['company_id']);

            // Recomendados por filtrado frecuente
            $table->index(['municipality_id', 'village_id']);
            $table->index(['place_type']);

            // (Opcional) acelerador para rangos
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('program_requests');
        Schema::enableForeignKeyConstraints();
    }
}
