<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Local development and tests get sample data; every deployed server only gets the admin account. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(app()->environment(['local', 'testing']) ? DemoSeeder::class : ProductionSeeder::class);
    }
}
