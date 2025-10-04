<?php

use App\Models\Joke;
use App\Models\Category;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

/**
 * VOTES API TESTS
 * 
 * Comprehensive testing of vote functionality including:
 * - Voting on jokes (like/dislike)
 * - Removing votes
 * - Administrative vote management
 * - Role-based permissions
 * - Validation and error handling
 */

// Include shared helper
require_once __DIR__ . '/../../../Helpers/UserHelper.php';

// Seed roles and permissions before each test
beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

// VOTE CREATION TESTS

/**
 * Test: User can vote on a joke (like)
 * 
 * Verifies that authenticated users can vote +1 (like) on jokes
 * and the vote is properly recorded with relationships.
 * 
 * @return void
 */
it('user can vote like on a joke', function () {
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($user);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertCreated()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.vote.rating', 1);
    $resp->assertJsonPath('data.vote.user_id', $user->id);
    $resp->assertJsonPath('data.vote.joke_id', (string) $joke->id);
});

/**
 * Test: User can vote on a joke (dislike)
 * 
 * Verifies that authenticated users can vote -1 (dislike) on jokes
 * and the vote is properly recorded with relationships.
 * 
 * @return void
 */
it('user can vote dislike on a joke', function () {
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($user);

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
 * Verifies that when a user votes on a joke they've already voted on,
 * the vote is updated rather than creating a duplicate.
 * 
 * @return void
 */
it('user can update their vote', function () {
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($user);

    // First vote (like)
    $resp1 = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    $resp1->assertCreated()->assertJsonPath('data.vote.rating', 1);

    // Update vote (dislike)
    $resp2 = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => -1
    ]);
    $resp2->assertOk()->assertJsonPath('success', true);
    $resp2->assertJsonPath('data.vote.rating', -1);
    $resp2->assertJsonPath('message', 'Vote updated successfully');

    // Verify only one vote exists
    expect(Vote::where('user_id', $user->id)->where('joke_id', $joke->id)->count())->toBe(1);
});

/**
 * Test: User cannot vote on non-existent joke
 * 
 * Verifies that voting on a non-existent joke returns a 404 error.
 * 
 * @return void
 */
it('user cannot vote on non-existent joke', function () {
    $user = createUserWithRole('user');
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
 * Verifies that regular users cannot vote on jokes that don't have
 * valid (non-deleted) categories assigned.
 * 
 * @return void
 */
it('user cannot vote on joke without valid categories', function () {
    $user = createUserWithRole('user');
    $joke = Joke::factory()->create(); // No categories attached
    
    Sanctum::actingAs($user);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertNotFound()->assertJsonPath('success', false);
    $resp->assertJsonPath('message', 'Joke not found');
});

/**
 * Test: Staff can vote on any joke
 * 
 * Verifies that staff+ level users can vote on any joke,
 * including jokes without categories.
 * 
 * @return void
 */
it('staff can vote on any joke', function () {
    $staff = createUserWithRole('staff');
    $joke = Joke::factory()->create(); // No categories attached
    
    Sanctum::actingAs($staff);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertCreated()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.vote.rating', 1);
});

// VOTE REMOVAL TESTS

/**
 * Test: User can remove their own vote
 * 
 * Verifies that users can remove their own votes from jokes.
 * 
 * @return void
 */
it('user can remove their own vote', function () {
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    // Create a vote
    $vote = Vote::create([
        'user_id' => $user->id,
        'joke_id' => $joke->id,
        'rating' => 1
    ]);
    
    Sanctum::actingAs($user);

    $resp = deleteJson("/api/v1/jokes/{$joke->id}/vote");
    
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonPath('message', 'Vote removed successfully');
    
    // Verify vote is deleted
    expect(Vote::find($vote->id))->toBeNull();
});

/**
 * Test: User cannot remove non-existent vote
 * 
 * Verifies that attempting to remove a vote that doesn't exist
 * returns a 404 error.
 * 
 * @return void
 */
it('user cannot remove non-existent vote', function () {
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($user);

    $resp = deleteJson("/api/v1/jokes/{$joke->id}/vote");
    
    $resp->assertNotFound()->assertJsonPath('success', false);
    $resp->assertJsonPath('message', 'No vote found for this joke');
});

/**
 * Test: Staff can remove any vote
 * 
 * Verifies that staff+ level users can remove any vote,
 * not just their own.
 * 
 * @return void
 */
it('staff can remove any vote', function () {
    $staff = createUserWithRole('staff');
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    // Create a vote by another user
    $vote = Vote::create([
        'user_id' => $user->id,
        'joke_id' => $joke->id,
        'rating' => 1
    ]);
    
    Sanctum::actingAs($staff);

    $resp = deleteJson("/api/v1/jokes/{$joke->id}/vote");
    
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonPath('message', 'Vote removed successfully');
    
    // Verify vote is deleted
    expect(Vote::find($vote->id))->toBeNull();
});

// VALIDATION TESTS

/**
 * Test: Validates vote rating
 * 
 * Verifies that vote rating is properly validated to only allow +1 or -1.
 * 
 * @return void
 */
it('validates vote rating', function () {
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($user);

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

    // Missing rating
    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", []);
    $resp->assertUnprocessable();
});

// PERMISSION TESTS

/**
 * Test: Guest cannot vote
 * 
 * Verifies that unauthenticated users cannot vote on jokes.
 * 
 * @return void
 */
it('guest cannot vote', function () {
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertUnauthorized();
});

/**
 * Test: Guest cannot remove votes
 * 
 * Verifies that unauthenticated users cannot remove votes.
 * 
 * @return void
 */
it('guest cannot remove votes', function () {
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);

    $resp = deleteJson("/api/v1/jokes/{$joke->id}/vote");
    
    $resp->assertUnauthorized();
});

// ADMIN VOTE MANAGEMENT TESTS

/**
 * Test: Staff can clear user votes
 * 
 * Verifies that staff+ level users can clear all votes for a specific user.
 * 
 * @return void
 */
it('staff can clear user votes', function () {
    $staff = createUserWithRole('staff');
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke1 = Joke::factory()->create();
    $joke2 = Joke::factory()->create();
    $joke1->categories()->attach($category->id);
    $joke2->categories()->attach($category->id);
    
    // Create votes for the user
    Vote::create(['user_id' => $user->id, 'joke_id' => $joke1->id, 'rating' => 1]);
    Vote::create(['user_id' => $user->id, 'joke_id' => $joke2->id, 'rating' => -1]);
    
    Sanctum::actingAs($staff);

    $resp = deleteJson("/api/v1/users/{$user->id}/votes");
    
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.cleared_votes_count', 2);
    $resp->assertJsonPath('message', 'Cleared 2 votes for user');
    
    // Verify votes are deleted
    expect(Vote::where('user_id', $user->id)->count())->toBe(0);
});

/**
 * Test: Admin can clear all votes
 * 
 * Verifies that admin+ level users can clear all votes in the system.
 * 
 * @return void
 */
it('admin can clear all votes', function () {
    $admin = createUserWithRole('admin');
    $user1 = createUserWithRole('user');
    $user2 = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke1 = Joke::factory()->create();
    $joke2 = Joke::factory()->create();
    $joke1->categories()->attach($category->id);
    $joke2->categories()->attach($category->id);
    
    // Create votes from different users
    Vote::create(['user_id' => $user1->id, 'joke_id' => $joke1->id, 'rating' => 1]);
    Vote::create(['user_id' => $user2->id, 'joke_id' => $joke2->id, 'rating' => -1]);
    
    Sanctum::actingAs($admin);

    $resp = deleteJson('/api/v1/votes');
    
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.cleared_votes_count', 2);
    $resp->assertJsonPath('message', 'Cleared 2 votes from the system');
    
    // Verify all votes are deleted
    expect(Vote::count())->toBe(0);
});

/**
 * Test: User cannot clear votes (insufficient permissions)
 * 
 * Verifies that regular users cannot clear votes due to insufficient permissions.
 * 
 * @return void
 */
it('user cannot clear votes', function () {
    $user = createUserWithRole('user');
    $otherUser = createUserWithRole('user');
    Sanctum::actingAs($user);

    // Cannot clear other user's votes
    $resp = deleteJson("/api/v1/users/{$otherUser->id}/votes");
    $resp->assertForbidden();

    // Cannot clear all votes
    $resp = deleteJson('/api/v1/votes');
    $resp->assertForbidden();
});

// ROLE-SPECIFIC VOTING TESTS

/**
 * Test: Staff can vote
 * 
 * Verifies that staff users can vote on jokes.
 * 
 * @return void
 */
it('staff can vote', function () {
    $staff = createUserWithRole('staff');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($staff);
    
    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertCreated()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.vote.rating', 1);
});

/**
 * Test: Admin can vote
 * 
 * Verifies that admin users can vote on jokes.
 * 
 * @return void
 */
it('admin can vote', function () {
    $admin = createUserWithRole('admin');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($admin);
    
    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertCreated()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.vote.rating', 1);
});

/**
 * Test: Superuser can vote
 * 
 * Verifies that superuser can vote on jokes.
 * 
 * @return void
 */
it('superuser can vote', function () {
    $superuser = createUserWithRole('superuser');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($superuser);
    
    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertCreated()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.vote.rating', 1);
});

/**
 * Test: Vote relationships are loaded correctly
 * 
 * Verifies that vote responses include proper user and joke relationships.
 * 
 * @return void
 */
it('vote relationships are loaded correctly', function () {
    $user = createUserWithRole('user');
    $category = Category::factory()->create();
    $joke = Joke::factory()->create();
    $joke->categories()->attach($category->id);
    
    Sanctum::actingAs($user);

    $resp = postJson("/api/v1/jokes/{$joke->id}/vote", [
        'rating' => 1
    ]);
    
    $resp->assertCreated();
    $resp->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'vote' => [
                'id',
                'user_id',
                'joke_id',
                'rating',
                'user' => [
                    'id',
                    'name',
                    'email'
                ],
                'joke' => [
                    'id',
                    'title',
                    'content'
                ]
            ]
        ]
    ]);
});
