<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * USER PROFILE API TESTS - STREAMLINED
 * 
 * Essential user profile tests covering core requirements:
 * - Profile management
 * - Password updates
 * - Authentication requirements
 */

// Include shared helper
require_once __DIR__ . '/../../../Helpers/UserHelper.php';

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

/**
 * Test: User can get their own profile
 * 
 * Verifies that authenticated users can retrieve their profile information.
 * 
 * @return void
 */
it('user can get their own profile', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $resp = getJson('/api/v1/me');
    $resp->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email);
});

/**
 * Test: User can update their own profile
 * 
 * Verifies that authenticated users can update their profile information.
 * 
 * @return void
 */
it('user can update their own profile', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $resp = putJson('/api/v1/me', [
        'name' => 'Updated Name',
        'given_name' => 'John',
        'family_name' => 'Doe'
    ]);
    
    $resp->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.name', 'Updated Name');
});

/**
 * Test: User can update their password
 * 
 * Verifies that authenticated users can update their password.
 * 
 * @return void
 */
it('user can update their password', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $resp = putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ]);
    
    $resp->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Password updated successfully. Please log in again.');
});

/**
 * Test: User can delete their own profile
 * 
 * Verifies that authenticated users can delete their own profile.
 * 
 * @return void
 */
it('user can delete their own profile', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $resp = deleteJson('/api/v1/me');
    $resp->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Profile deleted successfully');
    
    // Verify soft delete
    expect($user->fresh()->deleted_at)->not->toBeNull();
});

/**
 * Test: Guest cannot access profile endpoints
 * 
 * Verifies that unauthenticated users cannot access profile endpoints,
 * ensuring proper security for user data.
 * 
 * @return void
 */
it('guest cannot access profile endpoints', function () {
    // Cannot get profile
    $resp = getJson('/api/v1/me');
    $resp->assertUnauthorized();

    // Cannot update profile
    $resp = putJson('/api/v1/me', ['name' => 'Test']);
    $resp->assertUnauthorized();

    // Cannot delete profile
    $resp = deleteJson('/api/v1/me');
    $resp->assertUnauthorized();
});

/**
 * Test: Validates password update data
 * 
 * Verifies that password update validation works correctly.
 * 
 * @return void
 */
it('validates password update data', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    // Missing current password
    $resp = putJson('/api/v1/me/password', [
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ]);
    $resp->assertUnprocessable();

    // Password confirmation mismatch
    $resp = putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'newpassword123',
        'password_confirmation' => 'different'
    ]);
    $resp->assertUnprocessable();
});

/**
 * Test: Validates profile update data
 * 
 * Verifies that profile update validation works correctly.
 * 
 * @return void
 */
it('validates profile update data', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    // Invalid email format
    $resp = putJson('/api/v1/me', [
        'email' => 'invalid-email'
    ]);
    $resp->assertUnprocessable();
});

/**
 * Test: Password update revokes all tokens
 * 
 * Verifies that password update revokes all user tokens for security.
 * 
 * @return void
 */
it('password update revokes all tokens', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    // Create some tokens
    $user->createToken('test-token-1');
    $user->createToken('test-token-2');

    $resp = putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ]);
    
    $resp->assertOk();
    
    // Verify all tokens are revoked
    expect($user->fresh()->tokens()->count())->toBe(0);
});

/**
 * Test: Profile deletion revokes all tokens
 * 
 * Verifies that profile deletion revokes all user tokens.
 * 
 * @return void
 */
it('profile deletion revokes all tokens', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    // Create some tokens
    $user->createToken('test-token-1');
    $user->createToken('test-token-2');

    $resp = deleteJson('/api/v1/me');
    $resp->assertOk();
    
    // Verify all tokens are revoked
    expect($user->fresh()->tokens()->count())->toBe(0);
});
