<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoginTokensTable extends Migration{
    public function up(): void
    {
        Schema::create('login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique(); // sha256 hex
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_tokens');
    }
};
