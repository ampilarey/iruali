<?php

namespace Database\Seeders;

use App\Support\IslandList;
use Illuminate\Database\Seeder;

/**
 * The inhabited islands from database/data/islands.json. A migration already loads them on every
 * database; this only tops up a local or test database (running it again changes nothing).
 */
class IslandSeeder extends Seeder
{
    public function run()
    {
        IslandList::load();
    }
}
