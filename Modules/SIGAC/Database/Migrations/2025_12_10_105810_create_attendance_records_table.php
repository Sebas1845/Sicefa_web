<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAttendanceRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            // Fecha y hora de la asistencia
            $table->date('attendance_date');
            $table->time('attendance_time');

            // Relaciones (IDs)
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('apprentice_id');



            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('instructor_id')->references('id')->on('people')->onDelete('cascade');
            $table->foreign('apprentice_id')->references('id')->on('people')->onDelete('cascade');




            // Estado de la asistencia
            $table->enum('attendance_status', [
                'present',
                'late',
                'absent',
                'withdrawn',
                'excused'
            ]);

            // Evidencia y observaciones
            $table->string('evidence')->nullable();
            $table->text('observations')->nullable();

            // Timestamps de Laravel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance_records');
    }
}
