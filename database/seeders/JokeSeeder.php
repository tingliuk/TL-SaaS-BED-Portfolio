<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Joke;
use App\Models\User;
use Illuminate\Database\Seeder;

class JokeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seedJokes = [
            [
                'title' => 'Skeleton Fight',
                'content' => "Why don't skeletons fight each other? ".
                           "Because they don't have the guts.",
                'user_id' => 100,
                'categories' => ['Skeleton'],
            ],
            [
                'title' => 'Pirate Maths',
                'content' => 'What type of Maths are pirates best at?'.
                           'Algebra. Because they are good at finding X.',
                'user_id' => 100,
                'categories' => ['Pirate', 'Maths'],
            ],
        ];

        $users = User::all()->pluck('id', 'id')->toArray();

        foreach ($seedJokes as $seedJoke) {

            $categoryList = $seedJoke['categories'] ?? ['Unknown'];
            unset($seedJoke['categories']);

            $joke = Joke::updateOrCreate([
                'title' => $seedJoke['title'],
                'content' => $seedJoke['content'],
                'user_id' => $users[array_rand($users)],
            ]);

            foreach ($categoryList as $category) {
                Category::updateOrCreate(['title' => $category]);
            }

            if (! empty($categoryList)) {
                $categoryIds = Category::whereIn('title', $categoryList)
                    ->get()
                    ->pluck('id')
                    ->toArray();
                $joke->categories()->sync($categoryIds);
            }

        }
    }
}
