<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVillageRatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('village_rates', function (Blueprint $table) {
            $table->id();

            $table->string('village_name', 150);
            $table->string('municipality_name', 120)->nullable();

            $table->decimal('transport_amount', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['village_name']);
            $table->index(['active']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('village_rates');
    }
}
