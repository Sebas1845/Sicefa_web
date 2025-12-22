<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_activations', function (Blueprint $table) {
            $table->id();

            // Relación lógica con people.id (sin FK)
            $table->unsignedBigInteger('person_id');

            // Fechas de activación/login
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('last_login_at')->nullable();

            // Forzar cambio de contraseña en primer ingreso
            $table->boolean('must_change_password')->default(true);

            $table->timestamps();

            $table->unique('person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_activations');
    }
};
