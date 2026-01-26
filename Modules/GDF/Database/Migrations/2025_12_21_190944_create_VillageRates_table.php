<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVillageRatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('village_rates', function (Blueprint $table) {
            $table->id();

            /**
             * Recomendado:
             * - municipality_id + village_id: relación exacta con tus catálogos
             * - municipality_name + village_name: fallback / carga inicial
             */
            $table->unsignedBigInteger('municipality_id')->nullable()->index();
            $table->unsignedBigInteger('village_id')->nullable()->index();

            $table->string('village_name', 150)->index();
            $table->string('municipality_name', 120)->index();

            // Costos SOLO IDA (sin aéreo en vereda)
            $table->decimal('bus_amount', 12, 2)->default(0);
            $table->decimal('van_amount', 12, 2)->default(0);        // Camioneta
            $table->decimal('motorcycle_amount', 12, 2)->default(0);

            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            // Unicidad recomendada cuando village_id esté poblado (opcional)
            // $table->unique(['village_id'], 'uniq_village_rates_village_id');

            // Si no usas IDs, al menos evita duplicados por nombre+municipio (opcional)
            // $table->unique(['village_name','municipality_name'], 'uniq_village_rates_name_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('village_rates');
    }
}
