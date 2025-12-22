<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnvironmentIncidentsTable extends Migration
{
    public function up()
    {
        Schema::create('environment_incidents', function (Blueprint $table) {
            $table->id();

            // Ambiente (esta sí sabemos que existe)
            $table->unsignedBigInteger('environment_id');
            $table->foreign('environment_id')
                  ->references('id')->on('environments')
                  ->onDelete('cascade');

            // Cronograma: por ahora solo ID, sin FK para no fallar
            $table->unsignedBigInteger('schedule_id')->nullable();

            // Instructor: igual, solo ID por ahora
            $table->unsignedBigInteger('instructor_id')->nullable();

            // Usuario que reporta (user del sistema) -> esta FK sí se mantiene
            $table->unsignedBigInteger('reported_by');
            $table->foreign('reported_by')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->dateTime('reported_at');

            $table->enum('source', ['RONDAS', 'INSTRUCTOR']);
            $table->enum('type', ['LIMPIEZA', 'AIRE', 'EQUIPO', 'OTRO']);
            $table->text('description')->nullable();

            $table->enum('status', ['ABIERTA','EN_PROCESO','CERRADA'])
                  ->default('ABIERTA');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('environment_incidents');
    }
}
