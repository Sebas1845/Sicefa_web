<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContractDateToContractualCertificatesTable extends Migration
{
    public function up()
    {
        Schema::table('contractual_certificates', function (Blueprint $table) {

            if (!Schema::hasColumn('contractual_certificates', 'contract_date')) {
                $table->date('contract_date')->nullable();
            }

            if (!Schema::hasColumn('contractual_certificates', 'expedition_date')) {
                $table->date('expedition_date')->nullable();
            }

            if (!Schema::hasColumn('contractual_certificates', 'execution_start_date')) {
                $table->date('execution_start_date')->nullable();
            }

            if (!Schema::hasColumn('contractual_certificates', 'execution_end_date')) {
                $table->date('execution_end_date')->nullable();
            }

            if (!Schema::hasColumn('contractual_certificates', 'status')) {
                $table->enum('status', ['draft', 'issued', 'cancelled', 'revised'])
                      ->default('draft');
            }

            // 🔽 NUEVO: forma de pago
            if (!Schema::hasColumn('contractual_certificates', 'payment_type')) {
               $table->enum('payment_type', ['mensual', 'horas'])->nullable();
            }

            if (!Schema::hasColumn('contractual_certificates', 'monthly_payment')) {
                $table->decimal('monthly_payment', 15, 2)->nullable();
            }

            if (!Schema::hasColumn('contractual_certificates', 'unit_hour_value')) {
               $table->decimal('unit_hour_value', 15, 2)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('contractual_certificates', function (Blueprint $table) {

            $columns = [
                'contract_date',
                'expedition_date',
                'execution_start_date',
                'execution_end_date',
                'status',
                'payment_type',
                'monthly_payment',
                'unit_hour_value'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('contractual_certificates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
