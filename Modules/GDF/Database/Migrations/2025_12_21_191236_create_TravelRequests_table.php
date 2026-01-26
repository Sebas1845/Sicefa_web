<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTravelRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();

            // Unificación por módulo
            $table->enum('module', ['gdf','sitrav'])->default('gdf')->index();
            $table->enum('source', ['manual','sigac'])->default('manual')->index();

            // Referencia a SIGAC (program_requests.id) - SIN FK para no acoplar módulos
            $table->unsignedBigInteger('source_request_id')->nullable()->index();

            // Radicación (consecutivo interno SICEFA / trámite)
            $table->string('radicado_code', 80)->nullable()->index();
            $table->timestamp('radicated_at')->nullable();
            $table->foreignId('radicated_by')->nullable()->constrained('users');

            // Relaciones presupuestales / área
            $table->foreignId('area_id')->constrained('areas');
            $table->foreignId('budget_item_id')->constrained('budget_items');
            $table->foreignId('budget_id')->nullable()->constrained('budgets');

            // Persona
            $table->foreignId('person_id')->constrained('people');
            $table->enum('person_type', ['staff', 'contractor']); // staff=planta
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors');

            // Tipo
            $table->enum('request_type', ['travel', 'training', 'event'])->default('travel');

            // Datos básicos (cabecera)
            $table->string('origin', 150);
            $table->string('destination', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();

            // Estado
            $table->enum('status', ['draft','submitted','returned','rejected','approved','executed','cancelled'])
                  ->default('draft');

            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Totales
            $table->decimal('total_transport', 14, 2)->default(0);
            $table->decimal('total_per_diem', 14, 2)->default(0);
            $table->decimal('total_other', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['area_id', 'status']);
            $table->index(['person_id', 'person_type']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_requests');
    }
}
