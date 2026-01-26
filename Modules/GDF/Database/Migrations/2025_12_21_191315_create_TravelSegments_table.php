<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTravelSegmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('travel_segments', function (Blueprint $table) {
            $table->id();

            /* =============================
             * Relación principal
             * ============================= */
            $table->foreignId('travel_request_id')
                ->constrained('travel_requests')
                ->cascadeOnDelete();

            /* =============================
             * Fechas / horas reales
             * ============================= */
            // En SITRAV reflejan program_request_dates
            $table->dateTime('departure_at');
            $table->dateTime('return_at');

            /* =============================
             * Origen / Destino (texto)
             * ============================= */
            $table->string('origin_place', 150);
            $table->string('destination_place', 150);

            /* =============================
             * GEO / MAPA (Leaflet / OSM)
             * ============================= */
            $table->decimal('origin_lat', 10, 7)->nullable();
            $table->decimal('origin_lng', 10, 7)->nullable();

            $table->decimal('destination_lat', 10, 7)->nullable();
            $table->decimal('destination_lng', 10, 7)->nullable();

            // Texto amigable (reverse geocoding)
            $table->string('origin_display_name', 255)->nullable();
            $table->string('destination_display_name', 255)->nullable();

            // Texto que escribió el usuario (búsqueda)
            $table->string('destination_query', 255)->nullable();

            /* =============================
             * Tipo de destino
             * ============================= */
            $table->enum('destination_type', [
                'municipality',
                'village',
                'other'
            ])->default('other');

            /* =============================
             * Relación geográfica REAL
             * ============================= */
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments');

            $table->foreignId('municipality_id')
                ->nullable()
                ->constrained('municipalities');

            $table->foreignId('village_id')
                ->nullable()
                ->constrained('villages');

            /* =============================
             * Tarifas oficiales
             * ============================= */
            $table->foreignId('municipality_rate_id')
                ->nullable()
                ->constrained('municipality_rates');

            $table->foreignId('village_rate_id')
                ->nullable()
                ->constrained('village_rates');

            /* =============================
             * Transporte
             * ============================= */
            // Mejor enum que string libre
            $table->enum('transport_type', [
                'terrestre',
                'aereo',
                'moto',
                'otro'
            ])->nullable();

            /* =============================
             * Tipo de viaje
             * ============================= */
            $table->enum('trip_type', [
                'one_way',
                'round_trip'
            ])->default('round_trip');

            // Número de trayectos (1 = ida, 2 = ida y vuelta)
            $table->unsignedTinyInteger('trips')->default(2);

            /* =============================
             * Reprogramación / cancelación
             * ============================= */
            $table->boolean('is_cancelled')->default(false);
            $table->string('change_reason', 255)->nullable();

            /* =============================
             * Costos
             * ============================= */
            // transport_cost = costo por trayecto
            $table->decimal('transport_cost', 14, 2)->default(0);
            $table->decimal('per_diem_cost', 14, 2)->default(0);
            $table->decimal('other_cost', 14, 2)->default(0);

            // total_cost = (transport * trips) + demás
            $table->decimal('total_cost', 14, 2)->default(0);

            /* =============================
             * Observaciones
             * ============================= */
            $table->text('notes')->nullable();

            $table->timestamps();

            /* =============================
             * Índices
             * ============================= */
            $table->index(['travel_request_id']);
            $table->index(['departure_at']);
            $table->index(['destination_type']);
            $table->index(['transport_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_segments');
    }
}
