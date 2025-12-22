<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnvironmentKeyLogsTable extends Migration
{
    public function up()
    {
        Schema::create('environment_key_logs', function (Blueprint $table) {
            $table->id();

            // Ambiente (esta FK sí es segura)
            $table->unsignedBigInteger('environment_id');
            $table->foreign('environment_id')
                  ->references('id')->on('environments')
                  ->onDelete('cascade');

            // Clase del cronograma (por ahora solo ID, sin FK)
            $table->unsignedBigInteger('schedule_id')->nullable();

            // Instructor (por ahora solo ID, sin FK)
            $table->unsignedBigInteger('instructor_id')->nullable();

            // Usuario que entrega la llave (user del sistema)
            $table->unsignedBigInteger('given_by');
            $table->foreign('given_by')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->dateTime('taken_at');          // hora entrega
            $table->dateTime('returned_at')->nullable(); // hora devolución

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('environment_key_logs');
    }
}
