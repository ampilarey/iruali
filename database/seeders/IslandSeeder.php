<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Island;

class IslandSeeder extends Seeder
{
    public function run()
    {
        $islands = [
            [
                'name' => ['en' => 'Malé', 'dv' => 'މާލެ'],
                'atoll' => 'Kaafu',
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Hulhumalé', 'dv' => 'ހުޅުމާލެ'],
                'atoll' => 'Kaafu',
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Addu City', 'dv' => 'އައްޑޫ'],
                'atoll' => 'Seenu',
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Fuvahmulah', 'dv' => 'ފުވައްމުލައް'],
                'atoll' => 'Gnaviyani',
                'is_active' => true,
            ],
        ];

        foreach ($islands as $island) {
            Island::create($island);
        }
    }
} 