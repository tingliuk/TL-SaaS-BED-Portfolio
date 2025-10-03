<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create(
            [
                'id' => 1,
                'name' => 'Unknown',
                'description' => 'Sorry, but we have no idea where to place this joke.',
            ]
        );

        $seedCategories = [
            [
                'name' => 'Dad',
                'description' => 'Dad jokes are always the most puntastic and groan worthy!',
            ],
            [
                'name' => 'Pun',
                'description' => "Simply so punny you'll have to laugh",
            ],
            [
                'name' => 'Pirate',
                'description' => 'Aaaaarrrrrrrrgh, me hearties!',
            ],
        ];

        // Shuffle the categories for fun ;)
        shuffle($seedCategories);

        foreach ($seedCategories as $seedCategory) {
            Category::create($seedCategory);
        }

        // Category::factory(10)->create();

    }
}
