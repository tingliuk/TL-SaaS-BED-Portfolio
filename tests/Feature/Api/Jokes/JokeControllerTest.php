<?php

use App\Models\Category;
use App\Models\Joke;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * JOKES API TESTS - STREAMLINED
 * 
 * Essential joke tests covering core requirements:
 * - Basic CRUD operations
 * - Role-based permissions
 * - Guest access restrictions
 * - Random joke endpoint
 */

// Include shared helper
require_once __DIR__ . '/../../../Helpers/UserHelper.php';

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

/**
 * Test: Logged in user can retrieve jokes
 * 
 * Verifies that authenticated users with 'client' role (level 100+) can successfully
 * browse and retrieve jokes. This is the core requirement for joke access.
 * 
 * @return void
 */
it('logged in user can retrieve jokes', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $category = Category::factory()->create();
    Joke::factory()->count(3)->create()->each(function ($joke) use ($category) {
        $joke->categories()->attach($category);
    });

    $resp = getJson('/api/v1/jokes');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: Guest cannot access jokes except random
 * 
 * Verifies that unauthenticated users cannot access joke endpoints except
 * for the random joke endpoint, ensuring proper security for protected resources.
 * 
 * @return void
 */
it('guest cannot access jokes except random', function () {
    // Cannot access jokes list
    $resp = getJson('/api/v1/jokes');
    $resp->assertUnauthorized();

    // Can access random joke
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    $resp = getJson('/api/v1/jokes/random');
    $resp->assertOk();
});

/**
 * Test: User can create and manage their own jokes
 * 
 * Verifies that users with 'client' role can create, read, update, and delete
 * their own jokes but cannot manage other users' jokes.
 * 
 * @return void
 */
it('user can create and manage their own jokes', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    // Create joke with category
    $category = Category::factory()->create();
    $resp = postJson('/api/v1/jokes', [
        'title' => 'User Joke',
        'content' => 'This is a joke created by user',
        'categories' => [$category->id],
    ]);
    $resp->assertCreated();

    $joke = Joke::where('title', 'User Joke')->first();

    // Read joke
    $resp = getJson('/api/v1/jokes/' . $joke->id);
    $resp->assertOk();

    // Update joke
    $resp = putJson('/api/v1/jokes/' . $joke->id, [
        'title' => 'Updated User Joke',
    ]);
    $resp->assertOk();

    // Delete joke
    $resp = deleteJson('/api/v1/jokes/' . $joke->id);
    $resp->assertOk();

    // Verify soft delete
    expect($joke->fresh()->deleted_at)->not->toBeNull();
});

/**
 * Test: Staff can perform full CRUD operations
 * 
 * Verifies that staff users (level 500+) can create, read, update, and delete
 * any jokes, demonstrating the full permission set for staff role.
 * 
 * @return void
 */
it('staff can perform full CRUD operations', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    $category = Category::factory()->create();

    // Create
    $resp = postJson('/api/v1/jokes', [
        'title' => 'Staff Joke',
        'content' => 'This is a joke created by staff',
        'categories' => [$category->id],
    ]);
    $resp->assertCreated();

    $joke = Joke::where('title', 'Staff Joke')->first();

    // Read
    $resp = getJson('/api/v1/jokes/' . $joke->id);
    $resp->assertOk();

    // Update
    $resp = putJson('/api/v1/jokes/' . $joke->id, [
        'title' => 'Updated Staff Joke'
    ]);
    $resp->assertOk();

    // Delete (soft delete)
    $resp = deleteJson('/api/v1/jokes/' . $joke->id);
    $resp->assertOk();
});

/**
 * Test: Random joke endpoint works for guests
 * 
 * Verifies that the random joke endpoint is accessible to guests,
 * providing public access to joke content.
 * 
 * @return void
 */
it('random joke endpoint works for guests', function () {
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    $resp = getJson('/api/v1/jokes/random');
    $resp->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'joke' => ['id', 'title', 'content']
            ]
        ]);
});

/**
 * Test: Client cannot retrieve jokes with unknown or empty categories
 * 
 * Verifies that clients cannot see jokes without valid categories,
 * implementing the business rule for content filtering.
 * 
 * @return void
 */
it('client cannot retrieve jokes with unknown or empty categories', function () {
    $client = createUserWithRole('client');
    Sanctum::actingAs($client);

    // Create joke without categories
    $jokeWithoutCategories = Joke::factory()->create(['title' => 'Joke Without Categories']);

    // Create joke with valid category
    $category = Category::factory()->create();
    $jokeWithCategories = Joke::factory()->create(['title' => 'Joke With Categories']);
    $jokeWithCategories->categories()->attach($category);

    // Test index endpoint - should only return joke with valid categories
    $resp = getJson('/api/v1/jokes');
    $resp->assertOk();
    
    $jokes = $resp->json('data.jokes.data');
    $jokeTitles = collect($jokes)->pluck('title')->toArray();

    expect($jokeTitles)->not->toContain('Joke Without Categories');
    expect($jokeTitles)->toContain('Joke With Categories');
});

/**
 * Test: User can search jokes
 * 
 * Verifies that authenticated users can search jokes by title.
 * 
 * @return void
 */
it('user can search jokes', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $category = Category::factory()->create();
    $joke1 = Joke::factory()->create(['title' => 'Funny Joke']);
    $joke1->categories()->attach($category);
    
    $joke2 = Joke::factory()->create(['title' => 'Serious Joke']);
    $joke2->categories()->attach($category);

    $resp = getJson('/api/v1/jokes?q=Funny');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: User cannot manage other users jokes
 * 
 * Verifies that users cannot update or delete jokes created by other users.
 * 
 * @return void
 */
it('user cannot manage other users jokes', function () {
    $user1 = createUserWithRole('client');
    $user2 = createUserWithRole('client');
    Sanctum::actingAs($user2);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create(['user_id' => $user1->id, 'title' => 'User1 Joke']);
    $joke->categories()->attach($category);

    // Cannot update other user's joke
    $resp = putJson('/api/v1/jokes/' . $joke->id, [
        'title' => 'Updated by User2'
    ]);
    $resp->assertForbidden();

    // Cannot delete other user's joke
    $resp = deleteJson('/api/v1/jokes/' . $joke->id);
    $resp->assertForbidden();
});

/**
 * Test: Staff can restore soft deleted jokes
 * 
 * Verifies that staff can restore soft deleted jokes.
 * 
 * @return void
 */
it('staff can restore soft deleted jokes', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);
    $joke->delete(); // Soft delete

    $resp = postJson('/api/v1/jokes/' . $joke->id . '/restore');
    $resp->assertOk();

    // Verify joke is restored
    expect($joke->fresh()->deleted_at)->toBeNull();
});
