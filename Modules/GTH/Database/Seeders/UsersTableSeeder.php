<?php

namespace Modules\GTH\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\SICA\Entities\Person;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        // Registrar o actualizar usuario para Administrador
        $person = Person::where('document_number',1075213634)->first(); // Consultar Persona
        User::updateOrCreate(['nickname' => 'Daniel'], [ // Actualizar o crear usuario
            'person_id' => $person->id,
            'email' => 'danielgth@gmail.com', //con: Dagu3634
        ]);


        // Registrar o actualizar usuario para Administrador
        $person = Person::where('document_number',1109840652)->first(); // Consultar Persona
        User::updateOrCreate(['nickname' => 'Mayerly'], [ // Actualizar o crear usuario
            'person_id' => $person->id,
            'email' => 'mayerly@gmail.com', //con:  Maca0652
        ]);

        

        // Asignar rol de Administrador al usuario Daniel
        $rol_admin = DB::table('roles')->where('slug', 'gth.admin')->first(); // Consultar rol de Administrador GTH
        $user = User::where('nickname', 'Daniel')->firstOrFail(); // Consultar usuario Daniel
        $user->roles()->syncWithoutDetaching([$rol_admin->id]); // Asignar rol de Administrador a Daniel
    }



}


