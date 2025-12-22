<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePerDiemLevelsTable extends Migration
{
    public function up(): void
    {
        Schema::create('per_diem_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->text('description')->nullable();

            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->decimal('daily_amount', 12, 2)->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('per_diem_levels');
    }
}
