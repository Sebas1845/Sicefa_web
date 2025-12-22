<?php

namespace Modules\GDF\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\SICA\Entities\App;

class AppTableSeeder extends Seeder
{
    public function run(): void
    {
        App::updateOrCreate(['name' => 'GDF'], [
            'url' => '/gdf/index',
            'color' => '#198754', // verde bootstrap
            'icon' => 'fas fa-route',
            'description' => 'Travel & logistics management (GDF)',
            'description_english' => 'Travel & logistics management (GDF)',
        ]);
    }
}
