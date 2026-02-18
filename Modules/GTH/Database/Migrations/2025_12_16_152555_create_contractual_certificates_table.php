<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractualCertificatesTable extends Migration
{
    public function up()
    {
        Schema::create('contractual_certificates', function (Blueprint $table) {
            $table->id();
            
            // Relación con contrato
            $table->foreignId('contractor_id')->constrained('contractors')->onDelete('cascade');
            $table->string('certificate_number')->unique()->nullable();
            
            // Configuración del encabezado
            $table->string('center_name');
            $table->text('center_address');
            $table->string('version_code', 50);
            $table->text('title_line_1');
            $table->string('title_line_2');
            
            // Datos del contratista
            $table->string('gender', 50)->nullable();
            $table->string('place_of_issue')->nullable();
            
            // Datos de ejecución
            $table->date('contract_date')->nullable();
            $table->date('expedition_date')->nullable();
            $table->date('execution_start_date')->nullable();
            $table->date('execution_end_date')->nullable();
            
            // Forma de pago
            $table->enum('payment_type', ['mensual', 'horas'])->nullable();
            $table->decimal('monthly_payment', 15, 2)->nullable();
            $table->decimal('unit_hour_value', 15, 2)->nullable();
            
            // Firmas y responsables
            $table->string('projected_by')->nullable();
            $table->string('projected_by_role')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('reviewed_by_role')->nullable();
            $table->string('director_name')->nullable();
            $table->string('director_role')->nullable();
            
            // Formato
            $table->string('logo_color', 20)->default('#39A900');
            $table->string('font_family', 50)->default('Calibri');
            
            // Estado y seguimiento
            $table->enum('status', ['draft', 'issued', 'cancelled', 'revised'])->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('contractor_id');
            $table->index('certificate_number');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('contractual_certificates');
    }
}