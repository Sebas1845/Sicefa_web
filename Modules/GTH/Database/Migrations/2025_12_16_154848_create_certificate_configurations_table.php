<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCertificateConfigurationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('certificate_configurations', function (Blueprint $table) {
            $table->id();
            
            // Información del centro
            $table->string('center_name');
            $table->text('center_address');
            $table->string('version_code', 50);
            
            // Títulos del certificado
            $table->text('title_line_1');
            $table->string('title_line_2');
            
            // Valores por defecto para las firmas
            $table->string('default_projected_by')->nullable();
            $table->string('default_projected_role')->nullable();
            $table->string('default_reviewed_by')->nullable();
            $table->string('default_reviewed_role')->nullable();
            $table->string('default_director_name')->nullable();
            $table->string('default_director_role')->nullable();
            
            // Configuración de formato
            $table->string('logo_color', 20)->default('#39A900');
            $table->string('font_family', 50)->default('Arial');
            
            // Control de configuración activa
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Descripción opcional
            $table->text('description')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para búsquedas rápidas
            $table->index('is_default');
            $table->index('is_active');
        });

        // Insertar configuración por defecto
        DB::table('certificate_configurations')->insert([
            'center_name' => 'Centro de Formación Agroindustrial',
            'center_address' => 'Km 38 via al sur de Neiva, Campoalegre – Huila PBX 57 601 5461500',
            'version_code' => 'GTH-F-131 V05',
            'title_line_1' => ' LA SUSCRITA SUBDIRECTORA (E) DEL CENTRO DE FORMACIÓN AGROINDUSTRIAL DEL',
            'title_line_2' => 'HACE CONSTAR',
            'default_reviewed_by' => 'Doris Yolima Amaya Tovar',
            'default_reviewed_role' => 'Profesional GTH',
            'default_director_name' => 'GLORIA MARITZA SÁNCHEZ ALARCÓN',
            'default_director_role' => 'Subdirectora (E)',
            'logo_color' => '#39A900',
            'font_family' => 'calibri',
            'is_default' => true,
            'is_active' => true,
            'description' => 'Configuración principal para certificados contractuales del CEFA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('certificate_configurations');
    }
}