<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractDetailsTable extends Migration
{
    public function up()
    {
        Schema::create('contract_details', function (Blueprint $table) {
            $table->id();

            // Relación 1:1 con contractors
            $table->foreignId('contractor_id')
                ->unique()
                ->constrained('contractors')
                ->onDelete('cascade');

            // =========================
            // DATOS CONTRACTUALES (copiados)
            // =========================
            $table->string('contract_number_formatted', 100)->nullable();
            $table->date('contract_date')->nullable();
            $table->date('expedition_date')->nullable();
            $table->date('execution_start_date')->nullable();
            $table->date('execution_end_date')->nullable();

            $table->enum('status', [
                'draft',
                'issued',
                'cancelled',
                'revised'
            ])->default('draft');

            // Forma de pago
            $table->enum('payment_type', ['mensual', 'horas'])->nullable();
            $table->decimal('monthly_payment', 15, 2)->nullable();
            $table->decimal('unit_hour_value', 15, 2)->nullable();

            // =========================
            // CAMPOS PROPIOS DE contract_details
            // =========================
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->onDelete('set null');

            $table->string('contract_version', 20)->default('1.0');
            $table->text('modification_notes')->nullable();
            $table->date('last_modified_date')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Índices
            $table->index('contract_number_formatted');
            $table->index('contract_date');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_details');
    }
}
