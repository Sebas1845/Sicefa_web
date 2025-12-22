<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('login_otps', function (Blueprint $table) {
            $table->id();

            // Relación lógica con people.id (sin FK para no acoplar)
            $table->unsignedBigInteger('person_id');

            // Correo al que se envió el OTP
            $table->string('email_used');

            // Hash SHA-256 del OTP (64 hex chars)
            $table->char('otp_hash', 64);

            // Control de intentos y uso
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();

            // Auditoría opcional
            $table->string('created_ip', 45)->nullable();     // IPv4/IPv6
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index('person_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_otps');
    }
};
