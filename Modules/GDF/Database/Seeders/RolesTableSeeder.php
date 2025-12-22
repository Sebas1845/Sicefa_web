<?php

namespace Modules\GDF\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\SICA\Entities\App;
use Modules\SICA\Entities\Role;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        // Importante: tu app debe llamarse GDF (no EVS)
        $app = App::where('name', 'GDF')->firstOrFail();

        // Roles funcionales completos (incluye ambos apoyos)
        $roles = [
            [
                'slug' => 'gdf.subdirection',
                'name' => 'Subdirección GDF',
                'description' => 'Gestiona la asignación de roles y aprobación directiva de solicitudes.',
                'description_english' => 'Manages role assignment and executive approval of requests.',
            ],
            [
                'slug' => 'gdf.treasury',
                'name' => 'Tesorería GDF',
                'description' => 'Gestiona presupuestos, adiciones, movimientos y ejecución financiera.',
                'description_english' => 'Manages budgets, additions, movements and financial execution.',
            ],
            [
                'slug' => 'gdf.academic_coordinator',
                'name' => 'Coordinador Académico GDF',
                'description' => 'Revisa y decide solicitudes del área Coordinación Académica.',
                'description_english' => 'Reviews and decides requests for Academic Coordination area.',
            ],
            [
                'slug' => 'gdf.campesena_coordinator',
                'name' => 'Coordinador Campesena GDF',
                'description' => 'Revisa y decide solicitudes del área Campesena.',
                'description_english' => 'Reviews and decides requests for Campesena area.',
            ],

            // === LOS DOS ROLES DE APOYO (lo que te faltaba) ===
            [
                'slug' => 'gdf.academic_support',
                'name' => 'Apoyo Coordinación Académica GDF',
                'description' => 'Registra y gestiona solicitudes del área de Coordinación Académica.',
                'description_english' => 'Creates and manages requests for Academic Coordination area.',
            ],
            [
                'slug' => 'gdf.campesena_support',
                'name' => 'Apoyo Campesena GDF',
                'description' => 'Registra y gestiona solicitudes del área Campesena.',
                'description_english' => 'Creates and manages requests for Campesena area.',
            ],

            [
                'slug' => 'gdf.instructor',
                'name' => 'Instructor GDF',
                'description' => 'Consulta solicitudes propias y aporta soportes cuando aplique.',
                'description_english' => 'Views own requests and provides attachments when applicable.',
            ],

            // Opcional: técnico (puede existir o no en tu operación)
            [
                'slug' => 'gdf.admin',
                'name' => 'Administrador GDF',
                'description' => 'Rol técnico para mantenimiento del módulo.',
                'description_english' => 'Technical role for module maintenance.',
            ],
        ];

        foreach ($roles as $r) {
            Role::updateOrCreate(
                ['slug' => $r['slug']],
                $this->rolePayload($app->id, $r)
            );
        }
    }

    /**
     * Build payload ONLY with columns that exist in roles table.
     * This avoids errors when your SICEFA roles table has additional NOT NULL columns
     * or when some columns differ by version.
     */
    private function rolePayload(int $appId, array $r): array
    {
        $payload = [];

        if (Schema::hasColumn('roles', 'name')) {
            $payload['name'] = $r['name'];
        }

        if (Schema::hasColumn('roles', 'description')) {
            $payload['description'] = $r['description'];
        }

        if (Schema::hasColumn('roles', 'description_english')) {
            $payload['description_english'] = $r['description_english'];
        }

        if (Schema::hasColumn('roles', 'full_access')) {
            // En SICEFA suele ser 'Yes'/'No' (string)
            $payload['full_access'] = 'No';
        }

        if (Schema::hasColumn('roles', 'app_id')) {
            $payload['app_id'] = $appId;
        }

        // Campos frecuentes en algunas variantes:
        if (Schema::hasColumn('roles', 'active')) {
            $payload['active'] = 1;
        }

        if (Schema::hasColumn('roles', 'protected')) {
            $payload['protected'] = 0;
        }

        if (Schema::hasColumn('roles', 'is_protected')) {
            $payload['is_protected'] = 0;
        }

        return $payload;
    }
}
