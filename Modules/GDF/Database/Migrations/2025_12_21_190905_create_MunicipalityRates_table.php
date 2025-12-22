<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMunicipalityRatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('municipality_rates', function (Blueprint $table) {
            $table->id();

            // If you have a municipalities table, use municipality_id instead.
            $table->string('municipality_name', 120);

            $table->decimal('transport_amount', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['municipality_name']);
            $table->index(['active']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('municipality_rates');
    }
}