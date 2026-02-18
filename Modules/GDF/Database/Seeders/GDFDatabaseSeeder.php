<?php

namespace Modules\GDF\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GDFDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            //$this->call(AppTableSeeder::class);
            //$this->call(RolesTableSeeder::class);
            $this->call(PermissionsTableSeeder::class);

            // Catálogos propios del módulo (areas/budget_items/per_diem_levels)
            //$this->call(GDFCatalogsSeeder::class);

            // DEMO (si lo quieres activar)
            // $this->call(PeopleTableSeeder::class);
            // $this->call(UsersTableSeeder::class);
            // $this->call(UserRoleAssignmentsSeeder::class); // si lo necesitas
        });
    }
}
