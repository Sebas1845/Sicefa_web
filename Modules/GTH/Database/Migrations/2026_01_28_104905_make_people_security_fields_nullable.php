<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE people
            MODIFY eps_id BIGINT UNSIGNED NULL,
            MODIFY population_group_id BIGINT UNSIGNED NULL,
            MODIFY pension_entity_id BIGINT UNSIGNED NULL
        ");
    }

    public function down(): void
    {
        // No se revierte por seguridad
    }
};
