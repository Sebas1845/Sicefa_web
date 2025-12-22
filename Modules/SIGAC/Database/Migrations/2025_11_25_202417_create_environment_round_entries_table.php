<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnvironmentRoundEntriesTable extends Migration
{
    public function up()
    {
        Schema::create('environment_round_entries', function (Blueprint $table) {
            $table->id();

            // Cabecera de ronda
            $table->unsignedBigInteger('round_id');
            $table->foreign('round_id')
                  ->references('id')->on('environment_rounds')
                  ->onDelete('cascade');

            // Clase del cronograma REAL (por ahora solo la columna)
            $table->unsignedBigInteger('schedule_id');

            // Campos de la ronda
            $table->enum('present_in_environment', ['SI', 'NO'])->nullable();
            $table->enum('attendance_status', ['OK','SIN_INSTRUCTOR','SOLO_APRENDICES','VACIO'])
                  ->nullable();

            $table->text('observations')->nullable();

            // Novedades físicas
            $table->boolean('is_dirty')->default(false);
            $table->enum('ac_status', ['OK', 'DANADO', 'NO_APLICA'])->default('NO_APLICA');
            $table->text('other_issues')->nullable();

            // Marcación para reubicación
            $table->boolean('marked_for_relocation')->default(false);

            $table->unsignedBigInteger('suggested_environment_id')->nullable();
            $table->foreign('suggested_environment_id')
                  ->references('id')->on('environments')
                  ->nullOnDelete();

            // Quién editó
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });

        /**
         * Agregamos la foreign key de schedule_id
         * SOLO si la tabla del cronograma existe.
         * Cambia el nombre de la tabla de abajo por el real si es diferente.
         */
        if (Schema::hasTable('instructor_program_outcome_schedules')) {
            Schema::table('environment_round_entries', function (Blueprint $table) {
                $table->foreign('schedule_id', 'env_round_entries_schedule_fk')
                      ->references('id')->on('instructor_program_outcome_schedules')
                      ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::table('environment_round_entries', function (Blueprint $table) {
            // por si se creó la FK
            if (Schema::hasColumn('environment_round_entries', 'schedule_id')) {
                $table->dropForeign('env_round_entries_schedule_fk');
            }
        });

        Schema::dropIfExists('environment_round_entries');
    }
}
