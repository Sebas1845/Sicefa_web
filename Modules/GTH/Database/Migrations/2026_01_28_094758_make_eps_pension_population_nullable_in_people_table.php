<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Esta migración hace que los campos eps_id, population_group_id y pension_entity_id
     * sean OPCIONALES (nullable) en la tabla people.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Hacer los campos nullable (permitir NULL)
            $table->foreignId('eps_id')->nullable()->change();
            $table->foreignId('population_group_id')->nullable()->change();
            $table->foreignId('pension_entity_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
     Schema::create('people', function (Blueprint $table) {
    $table->id();

    $table->foreignId('eps_id')->nullable()->constrained();
    $table->foreignId('population_group_id')->nullable()->constrained();
    $table->foreignId('pension_entity_id')->nullable()->constrained();

    $table->timestamps();
});


    }
};