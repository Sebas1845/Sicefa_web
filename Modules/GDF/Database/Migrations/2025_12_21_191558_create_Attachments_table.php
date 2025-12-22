<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAttachmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')->constrained('travel_requests')->cascadeOnDelete();

            $table->string('file_path', 255);
            $table->string('file_type', 60)->nullable();

            $table->foreignId('uploaded_by')->constrained('users');

            $table->timestamps();

            $table->index(['travel_request_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
}
