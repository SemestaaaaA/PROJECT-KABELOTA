<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Demo and QA servers get sample data; production only gets the admin account. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(config('kabelota.demo_mode') ? DemoSeeder::class : ProductionSeeder::class);
    }
}
