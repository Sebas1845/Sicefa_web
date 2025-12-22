<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_schedules', function (Blueprint $table) {
            // Estado de la agenda / ejecución
            if (!Schema::hasColumn('visit_schedules', 'status')) {
                $table->string('status', 30)
                    ->default('Programada')
                    ->after('environment_id');
            }

            // Control de ingreso / salida
            if (!Schema::hasColumn('visit_schedules', 'check_in_at')) {
                $table->dateTime('check_in_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('visit_schedules', 'check_out_at')) {
                $table->dateTime('check_out_at')->nullable()->after('check_in_at');
            }

            // Usuario de seguridad / portería que marcó
            if (!Schema::hasColumn('visit_schedules', 'security_user_id')) {
                $table->foreignId('security_user_id')
                    ->nullable()
                    ->after('check_out_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            // Índices útiles (por si tu tabla vieja no los tenía)
            $table->index(['date', 'person_in_charge_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('visit_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('visit_schedules', 'security_user_id')) {
                $table->dropForeign(['security_user_id']);
                $table->dropColumn('security_user_id');
            }

            if (Schema::hasColumn('visit_schedules', 'check_in_at')) {
                $table->dropColumn('check_in_at');
            }

            if (Schema::hasColumn('visit_schedules', 'check_out_at')) {
                $table->dropColumn('check_out_at');
            }

            if (Schema::hasColumn('visit_schedules', 'status')) {
                $table->dropColumn('status');
            }

            $table->dropIndex(['date', 'person_in_charge_id']);
            $table->dropIndex(['status']);
        });
    }
};
