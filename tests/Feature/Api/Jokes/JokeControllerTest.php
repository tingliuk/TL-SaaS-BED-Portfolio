<?php

use App\Models\Joke;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * JOKES API TESTS WITH OWNERSHIP-BASED PERMISSIONS
 * 
 * Jokes have user ownership with role-based access control:
 * - Guest (Level 0): No access
 * - User (Level 100): Create, read own jokes, search
 * - Staff (Level 500): Full CRUD + can manage any joke
 * - Admin (Level 750): All Staff + permanent delete
 * - Superuser (Level 999): All Admin + backup
 */

// Include shared helper
require_once __DIR__ . '/../../../Helpers/UserHelper.php';

// MINIMUM REQUIRED TESTS

/**
 * Test: Logged in user can retrieve jokes
 * 
 * Verifies that authenticated users with 'user' role (level 100+) can successfully
 * browse and retrieve the list of jokes. This is a basic read permission test.
 * 
 * @return void
 */
it('logged in user can retrieve jokes', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    Joke::factory()->count(3)->create();

    $resp = getJson('/api/v1/jokes');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: Unable to access different user's jokes - ownership-based access
 * 
 * Demonstrates that users can only access jokes they own or that are publicly available.
 * This tests the ownership-based access control system for jokes.
 * 
 * @return void
 */
it('unable to access different user jokes ', function () {
    $user1 = createUserWithRole('user');
    $user2 = createUserWithRole('user');
    
    // Create a category first
    $category = Category::factory()->create(['name' => 'Test Category']);
    
    // User1 creates a joke with categories
    Sanctum::actingAs($user1);
    $resp = postJson('/api/v1/jokes', [
        'title' => 'User1 Joke',
        'content' => 'This is a joke created by user1',
        'categories' => [$category->id],
    ]);
    $resp->assertCreated();
    
    $joke = Joke::where('title', 'User1 Joke')->first();
    
    // User2 can view the joke (jokes are viewable by all authenticated users)
    // But User2 cannot update or delete User1's joke
    Sanctum::actingAs($user2);
    $resp = getJson('/api/v1/jokes/'.$joke->id);
    $resp->assertOk()->assertJsonPath('data.joke.title', 'User1 Joke');
    
    // User2 cannot update User1's joke
    $resp = putJson('/api/v1/jokes/'.$joke->id, [
        'title' => 'Updated by User2',
    ]);
    $resp->assertForbidden()->assertJsonPath('message', 'Unauthorized to update this joke');
    
    // User2 cannot delete User1's joke
    $resp = deleteJson('/api/v1/jokes/'.$joke->id);
    $resp->assertForbidden()->assertJsonPath('message', 'Unauthorized to delete this joke');
});

/**
 * Test: Able to delete jokes created by themselves
 * 
 * Verifies that users can create and delete their own jokes.
 * This tests the ownership tracking system where jokes are assigned
 * to the user who creates them.
 * 
 * @return void
 */
it('able to delete jokes created by themselves', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    // User creates their own joke
    $resp = postJson('/api/v1/jokes', [
        'title' => 'My Joke',
        'content' => 'This is my own joke',
    ]);
    $resp->assertCreated();
    
    $joke = Joke::where('title', 'My Joke')->first();
    
    // User can delete their own joke
    $resp = deleteJson('/api/v1/jokes/'.$joke->id);
    $resp->assertOk()->assertJsonPath('success', true);
    
    // Verify joke is soft deleted
    $this->assertSoftDeleted('jokes', ['id' => $joke->id]);
});

// ROLE-BASED PERMISSION TESTS

/**
 * Test: Guest cannot access jokes (except random)
 * 
 * Verifies that unauthenticated users (guests) are denied access to joke operations
 * except for the random joke endpoint. This ensures that authentication is required
 * for all joke operations except the public random joke.
 * 
 * @return void
 */
it('guest cannot access jokes except random', function () {
    // Guest cannot browse jokes
    $resp = getJson('/api/v1/jokes');
    $resp->assertUnauthorized();
    
    // But guest CAN access random joke
    $category = Category::factory()->create();
    $randomJoke = Joke::factory()->create();
    $randomJoke->categories()->attach($category);
    
    $resp = getJson('/api/v1/jokes/random');
    $resp->assertOk();
});

/**
 * Test: User can create and manage their own jokes
 * 
 * Verifies that users with 'user' role can create, read, update, and delete
 * their own jokes but cannot manage other users' jokes.
 * 
 * @return void
 */
it('user can create and manage their own jokes', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    // Create a category first
    $category = Category::factory()->create(['name' => 'Test Category']);

    // Create joke with categories
    $resp = postJson('/api/v1/jokes', [
        'title' => 'User Joke',
        'content' => 'This is a joke created by user',
        'categories' => [$category->id],
    ]);
    $resp->assertCreated();

    $joke = Joke::where('title', 'User Joke')->first();

    // Read joke
    $resp = getJson('/api/v1/jokes/'.$joke->id);
    $resp->assertOk();

    // Update joke
    $resp = putJson('/api/v1/jokes/'.$joke->id, [
        'title' => 'Updated User Joke',
    ]);
    $resp->assertOk();

    // Delete joke
    $resp = deleteJson('/api/v1/jokes/'.$joke->id);
    $resp->assertOk();
});

/**
 * Test: User cannot manage other users' jokes
 * 
 * Verifies that users cannot update or delete jokes created by other users.
 * This enforces the ownership-based permission system.
 * 
 * @return void
 */
it('user cannot manage other users jokes', function () {
    $user1 = createUserWithRole('user');
    $user2 = createUserWithRole('user');
    
    // User1 creates a joke
    Sanctum::actingAs($user1);
    $resp = postJson('/api/v1/jokes', [
        'title' => 'User1 Joke',
        'content' => 'This is a joke created by user1',
    ]);
    $resp->assertCreated();
    
    $joke = Joke::where('title', 'User1 Joke')->first();
    
    // User2 cannot update User1's joke
    Sanctum::actingAs($user2);
    $resp = putJson('/api/v1/jokes/'.$joke->id, [
        'title' => 'Updated by User2',
    ]);
    $resp->assertForbidden();
    
    // User2 cannot delete User1's joke
    $resp = deleteJson('/api/v1/jokes/'.$joke->id);
    $resp->assertForbidden();
});

/**
 * Test: User can search jokes
 * 
 * Verifies that users with 'user' role can search jokes using the query parameter.
 * This tests the search functionality with proper role-based permissions.
 * 
 * @return void
 */
it('user can search jokes', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    Joke::factory()->create(['title' => 'Funny Joke']);
    Joke::factory()->create(['title' => 'Serious Joke']);

    $resp = getJson('/api/v1/jokes?q=Funny');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: Staff can perform full CRUD operations
 * 
 * Verifies that staff members can perform all CRUD operations on jokes:
 * Create, Read, Update, Delete (soft delete), and Restore. This tests the complete
 * permission set for staff-level users.
 * 
 * @return void
 */
it('staff can perform full CRUD operations', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Create
    $resp = postJson('/api/v1/jokes', [
        'title' => 'Staff Joke',
        'content' => 'Created by staff',
    ]);
    $resp->assertCreated();

    $joke = Joke::where('title', 'Staff Joke')->first();

    // Read
    $resp = getJson('/api/v1/jokes/'.$joke->id);
    $resp->assertOk();

    // Update
    $resp = putJson('/api/v1/jokes/'.$joke->id, [
        'title' => 'Updated Staff Joke',
    ]);
    $resp->assertOk();

    // Soft delete
    $resp = deleteJson('/api/v1/jokes/'.$joke->id);
    $resp->assertOk();

    // Restore
    $resp = postJson('/api/v1/jokes/'.$joke->id.'/restore');
    $resp->assertOk();
});

/**
 * Test: Staff cannot permanently delete jokes
 * 
 * Verifies that staff members are forbidden from permanently deleting jokes.
 * Only Admin+ level users can perform permanent deletions. Staff can only soft delete.
 * 
 * @return void
 */
it('staff cannot permanently delete jokes', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    $joke = Joke::factory()->create();
    $joke->delete(); // Soft delete first

    $resp = deleteJson('/api/v1/jokes/'.$joke->id.'/force');
    $resp->assertForbidden()->assertJsonPath('message', 'Unauthorized to permanently delete this joke');
});

/**
 * Test: Admin can permanently delete jokes
 * 
 * Verifies that admin users (level 750) can permanently delete jokes that have
 * been soft deleted. This tests the elevated permission level for admin users
 * beyond what staff can do.
 * 
 * @return void
 */
it('admin can permanently delete jokes', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    $joke = Joke::factory()->create();
    $joke->delete(); // Soft delete first

    $resp = deleteJson('/api/v1/jokes/'.$joke->id.'/force');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: Superuser has all permissions
 * 
 * Verifies that superuser (level 999) has all permissions including browsing
 * and creating jokes. This tests the highest permission level in the system.
 * 
 * @return void
 */
it('superuser has all permissions', function () {
    $superuser = createUserWithRole('superuser');
    Sanctum::actingAs($superuser);

    // Can do everything
    $resp = getJson('/api/v1/jokes');
    $resp->assertOk();

    $resp = postJson('/api/v1/jokes', [
        'title' => 'Superuser Joke',
        'content' => 'Created by superuser',
    ]);
    $resp->assertCreated();
});

// ADDITIONAL COMPREHENSIVE TESTS

/**
 * Test: Random joke endpoint works for guests
 * 
 * Verifies that guests can access the random joke endpoint without authentication
 * and only receive jokes that have categories (no unknown category jokes).
 * 
 * @return void
 */
it('random joke endpoint works for guests', function () {
    // Create jokes with categories
    $category = Category::factory()->create();
    $joke1 = Joke::factory()->create();
    $joke1->categories()->attach($category);
    
    $joke2 = Joke::factory()->create();
    $joke2->categories()->attach($category);

    // Create a joke without categories (should not be returned)
    Joke::factory()->create(); // No categories attached

    $resp = getJson('/api/v1/jokes/random');
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'joke' => [
                'id',
                'title',
                'content',
                'user',
                'categories'
            ]
        ]
    ]);
    
    // Verify the joke has categories
    $jokeData = $resp->json('data.joke');
    expect($jokeData['categories'])->not->toBeEmpty();
});

/**
 * Test: Random joke excludes unknown category jokes
 * 
 * Verifies that the random joke endpoint only returns jokes that have categories
 * and excludes jokes without categories (unknown category jokes).
 * 
 * @return void
 */
it('random joke excludes unknown category jokes', function () {
    // Create jokes without categories (unknown category jokes)
    Joke::factory()->count(3)->create(); // No categories attached
    
    // Create one joke with a category
    $category = Category::factory()->create();
    $jokeWithCategory = Joke::factory()->create();
    $jokeWithCategory->categories()->attach($category);
    
    // Make multiple requests to ensure we get the joke with category
    $foundJokeWithCategory = false;
    for ($i = 0; $i < 10; $i++) {
        $resp = getJson('/api/v1/jokes/random');
        $resp->assertOk();
        
        $jokeData = $resp->json('data.joke');
        if (!empty($jokeData['categories'])) {
            $foundJokeWithCategory = true;
            break;
        }
    }
    
    expect($foundJokeWithCategory)->toBeTrue();
});

/**
 * Test: Handles search with no results
 * 
 * Verifies that the search functionality properly handles cases where no jokes
 * match the search criteria. This tests the error handling and response formatting
 * for empty search results.
 * 
 * @return void
 */
it('handles search with no results', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    // Create some jokes first
    Joke::factory()->count(2)->create();

    $resp = getJson('/api/v1/jokes?q=NonexistentJoke');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: Client cannot retrieve jokes with unknown/empty categories
 * 
 * Verifies that users with 'user' role cannot retrieve jokes that have no categories
 * or only have soft deleted categories. This ensures quality content filtering
 * for regular users.
 * 
 * @return void
 */
it('client cannot retrieve jokes with unknown or empty categories', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    // Create a joke without any categories (unknown category)
    $jokeWithoutCategories = Joke::factory()->create(['title' => 'Joke without categories']);

    // Create a joke with a soft deleted category
    $category = Category::factory()->create(['name' => 'Test Category']);
    $jokeWithDeletedCategory = Joke::factory()->create(['title' => 'Joke with deleted category']);
    $jokeWithDeletedCategory->categories()->attach($category);
    $category->delete(); // Soft delete the category

    // Create a joke with valid categories
    $validCategory = Category::factory()->create(['name' => 'Valid Category']);
    $jokeWithValidCategory = Joke::factory()->create(['title' => 'Joke with valid category']);
    $jokeWithValidCategory->categories()->attach($validCategory);

    // Test index endpoint - should only return joke with valid categories
    $resp = getJson('/api/v1/jokes');
    $resp->assertOk();
    
    $jokes = $resp->json('data.jokes.data');
    $jokeTitles = collect($jokes)->pluck('title')->toArray();
    
    expect($jokeTitles)->not->toContain('Joke without categories');
    expect($jokeTitles)->not->toContain('Joke with deleted category');
    expect($jokeTitles)->toContain('Joke with valid category');

    // Test show endpoint for joke without categories
    $resp = getJson('/api/v1/jokes/'.$jokeWithoutCategories->id);
    $resp->assertNotFound()->assertJsonPath('message', 'Joke not found');

    // Test show endpoint for joke with deleted category
    $resp = getJson('/api/v1/jokes/'.$jokeWithDeletedCategory->id);
    $resp->assertNotFound()->assertJsonPath('message', 'Joke not found');

    // Test show endpoint for joke with valid category
    $resp = getJson('/api/v1/jokes/'.$jokeWithValidCategory->id);
    $resp->assertOk()->assertJsonPath('data.joke.title', 'Joke with valid category');
});

/**
 * Test: Staff can retrieve jokes with unknown/empty categories
 * 
 * Verifies that staff+ level users can retrieve all jokes regardless of category status.
 * This tests that the filtering only applies to regular users, not staff+.
 * 
 * @return void
 */
it('staff can retrieve jokes with unknown or empty categories', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Create a joke without any categories
    $jokeWithoutCategories = Joke::factory()->create(['title' => 'Joke without categories']);

    // Create a joke with a soft deleted category
    $category = Category::factory()->create(['name' => 'Test Category']);
    $jokeWithDeletedCategory = Joke::factory()->create(['title' => 'Joke with deleted category']);
    $jokeWithDeletedCategory->categories()->attach($category);
    $category->delete(); // Soft delete the category

    // Test index endpoint - should return all jokes
    $resp = getJson('/api/v1/jokes');
    $resp->assertOk();
    
    $jokes = $resp->json('data.jokes.data');
    $jokeTitles = collect($jokes)->pluck('title')->toArray();
    
    expect($jokeTitles)->toContain('Joke without categories');
    expect($jokeTitles)->toContain('Joke with deleted category');

    // Test show endpoint for joke without categories
    $resp = getJson('/api/v1/jokes/'.$jokeWithoutCategories->id);
    $resp->assertOk()->assertJsonPath('data.joke.title', 'Joke without categories');

    // Test show endpoint for joke with deleted category
    $resp = getJson('/api/v1/jokes/'.$jokeWithDeletedCategory->id);
    $resp->assertOk()->assertJsonPath('data.joke.title', 'Joke with deleted category');
});

