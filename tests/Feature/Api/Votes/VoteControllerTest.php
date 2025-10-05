<?php

use App\Models\Category;
use App\Models\Joke;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * VOTES API TESTS - STREAMLINED
 * 
 * Essential vote tests covering core requirements:
 * - Basic voting functionality
 * - Role-based permissions
 * - Guest access restrictions
 */

// Include shared helper
require_once __DIR__ . '/../../../Helpers/UserHelper.php';

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

/**
 * Test: User can vote like on a joke
 * 
 * Verifies that authenticated users can vote on jokes with valid categories.
 * This is the core voting functionality.
 * 
 * @return void
 */
it('user can vote like on a joke', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertCreated()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.vote.rating', 1);
    $resp->assertJsonPath('data.vote.user_id', $user->id);
    $resp->assertJsonPath('data.vote.joke_id', (string) $joke->id);
});

/**
 * Test: User can vote dislike on a joke
 * 
 * Verifies that authenticated users can vote dislike on jokes.
 * 
 * @return void
 */
it('user can vote dislike on a joke', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => -1
    ]);
    
    $resp->assertCreated()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.vote.rating', -1);
    $resp->assertJsonPath('data.vote.user_id', $user->id);
    $resp->assertJsonPath('data.vote.joke_id', (string) $joke->id);
});

/**
 * Test: User can update their vote
 * 
 * Verifies that users can change their vote from like to dislike or vice versa.
 * 
 * @return void
 */
it('user can update their vote', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    // First vote (like)
    $resp1 = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    $resp1->assertCreated()->assertJsonPath('data.vote.rating', 1);

    // Update vote (dislike)
    $resp2 = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => -1
    ]);
    $resp2->assertOk()->assertJsonPath('data.vote.rating', -1);

    // Verify only one vote exists
    expect(Vote::where('user_id', $user->id)->where('joke_id', $joke->id)->count())->toBe(1);
});

/**
 * Test: User can remove their own vote
 * 
 * Verifies that users can remove their votes from jokes.
 * 
 * @return void
 */
it('user can remove their own vote', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    // Create a vote
    $vote = Vote::create([
        'user_id' => $user->id,
        'joke_id' => $joke->id,
        'rating' => 1
    ]);

    $resp = deleteJson("/api/v1/jokes/{$joke->id}/vote");
    
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonPath('message', 'Vote removed successfully');
    
    // Verify vote is deleted
    expect(Vote::find($vote->id))->toBeNull();
});

/**
 * Test: Guest cannot vote
 * 
 * Verifies that unauthenticated users cannot vote on jokes,
 * ensuring proper security for voting functionality.
 * 
 * @return void
 */
it('guest cannot vote', function () {
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertUnauthorized();
});

/**
 * Test: Staff can vote on any joke
 * 
 * Verifies that staff users can vote on any joke,
 * demonstrating elevated permissions for staff role.
 * 
 * @return void
 */
it('staff can vote on any joke', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertCreated()->assertJsonPath('success', true);
});

/**
 * Test: Staff can clear user votes
 * 
 * Verifies that staff users can clear votes for specific users,
 * demonstrating administrative capabilities.
 * 
 * @return void
 */
it('staff can clear user votes', function () {
    $staff = createUserWithRole('staff');
    $user = createUserWithRole('client');
    Sanctum::actingAs($staff);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    // Create votes for the user
    Vote::create([
        'user_id' => $user->id,
        'joke_id' => $joke->id,
        'rating' => 1
    ]);

    $resp = deleteJson("/api/v1/users/{$user->id}/votes");
    
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonPath('message', 'Cleared 1 votes for user');
    
    // Verify votes are cleared
    expect(Vote::where('user_id', $user->id)->count())->toBe(0);
});

/**
 * Test: User cannot vote on non-existent joke
 * 
 * Verifies that users cannot vote on jokes that don't exist.
 * 
 * @return void
 */
it('user cannot vote on non-existent joke', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $resp = postJson('/api/v1/jokes/99999/vote', [
        'rating' => 1
    ]);
    
    $resp->assertNotFound()->assertJsonPath('success', false);
    $resp->assertJsonPath('message', 'Joke not found');
});

/**
 * Test: User cannot vote on joke without valid categories
 * 
 * Verifies that users cannot vote on jokes without valid categories.
 * 
 * @return void
 */
it('user cannot vote on joke without valid categories', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $joke = Joke::factory()->create(); // No categories attached

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertNotFound()->assertJsonPath('success', false);
    $resp->assertJsonPath('message', 'Joke not found');
});

/**
 * Test: Validates vote rating
 * 
 * Verifies that vote rating validation works correctly.
 * 
 * @return void
 */
it('validates vote rating', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    // Invalid rating (0)
    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 0
    ]);
    $resp->assertUnprocessable();

    // Invalid rating (2)
    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 2
    ]);
    $resp->assertUnprocessable();
});

/**
 * Test: Admin can clear all votes
 * 
 * Verifies that admin users can clear all votes in the system.
 * 
 * @return void
 */
it('admin can clear all votes', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category);

    // Create some votes
    Vote::create(['user_id' => 1, 'joke_id' => $joke->id, 'rating' => 1]);
    Vote::create(['user_id' => 2, 'joke_id' => $joke->id, 'rating' => -1]);

    $resp = deleteJson('/api/v1/votes');
    
    $resp->assertOk()->assertJsonPath('success', true);
    
    // Verify all votes are cleared
    expect(Vote::count())->toBe(0);
});
