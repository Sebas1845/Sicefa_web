<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('instructor_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('person_id');           // instructor
            $table->unsignedBigInteger('user_id');             // usuario instructor (si existe)
            $table->unsignedBigInteger('from_area_id');        // área origen
            $table->unsignedBigInteger('to_area_id');          // área destino

            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->enum('status', ['PENDING','APPROVED','REJECTED','CANCELLED'])->default('PENDING');
            $table->text('note')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'to_area_id'], 'loans_status_to_area_idx');
            $table->index(['person_id', 'from_area_id', 'to_area_id'], 'loans_person_areas_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_loans');
    }
};
