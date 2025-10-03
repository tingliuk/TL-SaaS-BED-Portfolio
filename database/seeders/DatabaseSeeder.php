<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call(
            [
                // When using Spatie Permissions, perform the Role / Permission seeding FIRST
                RolesAndPermissionsSeeder::class,
                UserSeeder::class,
                // Add further seeder classes here
                CategorySeeder::class,
                JokeSeeder::class,
            ]
        );

    }
}
