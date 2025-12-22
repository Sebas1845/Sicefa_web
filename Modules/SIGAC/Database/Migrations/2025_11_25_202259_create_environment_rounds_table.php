<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnvironmentRoundsTable extends Migration
{
    public function up()
    {
        Schema::create('environment_rounds', function (Blueprint $table) {
            $table->id();

            $table->date('date');
            $table->enum('shift', ['MANANA', 'TARDE', 'NOCHE'])->default('MANANA');

            // Usuario que crea la ronda (pasante/coordinador)
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->boolean('is_locked')->default(false);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('environment_rounds');
    }
}
