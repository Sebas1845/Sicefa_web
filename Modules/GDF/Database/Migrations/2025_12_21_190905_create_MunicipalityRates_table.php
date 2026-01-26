<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMunicipalityRatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('municipality_rates', function (Blueprint $table) {
            $table->id();

            /**
             * Recomendado:
             * - municipality_id: para relacionar con tu tabla municipalities (cuando quieras)
             * - municipality_name: para carga rápida y fallback si no tienes ID aún
             */
            $table->unsignedBigInteger('municipality_id')->nullable()->index();
            $table->string('municipality_name', 120)->index();

            // Costos SOLO IDA
            $table->decimal('bus_amount', 12, 2)->default(0);
            $table->decimal('van_amount', 12, 2)->default(0);        // Camioneta
            $table->decimal('motorcycle_amount', 12, 2)->default(0);
            $table->decimal('air_amount', 12, 2)->default(0);

            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            // Unicidad cuando tengas municipality_id poblado (opcional)
            // $table->unique(['municipality_id'], 'uniq_municipality_rates_municipality_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_rates');
    }
}
