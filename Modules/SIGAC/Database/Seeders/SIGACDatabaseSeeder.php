<?php
namespace Modules\SIGAC\Database\Seeders;

use Illuminate\Database\Seeder;

class SIGACDatabaseSeeder extends Seeder
{
    public function run()
    {
        // Primero los permisos
        $this->call(PermissionsTableSeeder::class);

        // Luego los roles
        $this->call(RolesTableSeeder::class);

        // Otros seeders (opcional)
        // $this->call(AppTableSeeder::class);
        // $this->call(PeopleTableSeeder::class);
        // $this->call(UsersTableSeeder::class);
    }
}
