<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visit_schedules', function (Blueprint $table) {
            $table->id();

            /* ===============================
             * RELACIONES PRINCIPALES
             * =============================== */
            $table->foreignId('visit_request_id')
                ->constrained('visit_requests')
                ->cascadeOnDelete();

            $table->foreignId('person_in_charge_id')
                ->nullable()
                ->constrained('people')
                ->nullOnDelete();

            /* ===============================
             * DATOS GENERALES
             * =============================== */
            $table->string('notification_email', 255)->nullable();
            $table->string('activity'); // 255 por defecto
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');

            $table->foreignId('environment_id')
                ->nullable()
                ->constrained('environments')
                ->nullOnDelete();

            $table->text('observations')->nullable();

            /* ===============================
             * AUTORIZACIÓN (PDF)
             * =============================== */
            $table->string('authorization_path', 255)->nullable();

            /* === NUEVOS CAMPOS: CONTROL DE ENVÍO A PORTERÍA === */
            $table->timestamp('security_authorized_at')->nullable();

            $table->foreignId('security_authorized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('security_authorization_source', 20)
                ->nullable(); // 'manual' | 'job'

            /* ===============================
             * ESTADO / CONTROL DE SEGURIDAD
             * =============================== */
            $table->string('status', 30)->default('Agendada'); // Agendada | En curso | Finalizada | Cancelada

            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();

            $table->foreignId('security_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            /* ===============================
             * ÍNDICES
             * =============================== */
            $table->index(['date', 'person_in_charge_id']);
            $table->index('status');
            $table->index('security_authorized_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_schedules');
    }
};
