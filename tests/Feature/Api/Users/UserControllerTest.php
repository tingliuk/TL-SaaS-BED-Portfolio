<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * USER PROFILE API TESTS
 * 
 * Focused on user profile management functionality (/me endpoints):
 * - Profile retrieval and updates
 * - Password management
 * - Profile deletion
 * - Basic validation
 * 
 * Note: Comprehensive user administration tests are in UserAdminTest.php
 */

// Include shared helper
require_once __DIR__ . '/../../../Helpers/UserHelper.php';

// Seed roles and permissions before each test
beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

// USER PROFILE TESTS (GET/PUT/DELETE /me)

/**
 * Test: User can get their own profile
 * 
 * Verifies that authenticated users can retrieve their own profile
 * information including roles and permissions.
 * 
 * @return void
 */
it('user can get their own profile', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    $resp = getJson('/api/v1/me');
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.user.id', $user->id);
});

/**
 * Test: User can update their own profile
 * 
 * Verifies that authenticated users can update their own profile
 * information including name, email, and other personal details.
 * 
 * @return void
 */
it('user can update their own profile', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    $resp = putJson('/api/v1/me', [
        'name' => 'Updated Name',
        'given_name' => 'John',
        'family_name' => 'Doe',
    ]);
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonPath('data.user.name', 'Updated Name');
});

/**
 * Test: User can update their password
 * 
 * Verifies that authenticated users can update their password
 * with proper current password verification.
 * 
 * @return void
 */
it('user can update their password', function () {
    $user = createUserWithRole('user');
    $user->update(['password' => Hash::make('OldPassword123')]);
    Sanctum::actingAs($user);

    $resp = putJson('/api/v1/me/password', [
        'current_password' => 'OldPassword123',
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ]);
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: User cannot update password with wrong current password
 * 
 * Verifies that password update fails when current password is incorrect.
 * 
 * @return void
 */
it('user cannot update password with wrong current password', function () {
    $user = createUserWithRole('user');
    $user->update(['password' => Hash::make('OldPassword123')]);
    Sanctum::actingAs($user);

    $resp = putJson('/api/v1/me/password', [
        'current_password' => 'WrongPassword',
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ]);
    $resp->assertUnprocessable()->assertJsonPath('message', 'Current password is incorrect');
});

/**
 * Test: User can delete their own profile
 * 
 * Verifies that authenticated users can delete their own profile,
 * which soft deletes the user and revokes all tokens.
 * 
 * @return void
 */
it('user can delete their own profile', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    $resp = deleteJson('/api/v1/me');
    $resp->assertOk()->assertJsonPath('success', true);
    
    // Verify user is soft deleted
    $this->assertSoftDeleted('users', ['id' => $user->id]);
});

/**
 * Test: Guest cannot access profile endpoints
 * 
 * Verifies that unauthenticated users are denied access to all
 * profile management endpoints.
 * 
 * @return void
 */
it('guest cannot access profile endpoints', function () {
    getJson('/api/v1/me')->assertUnauthorized();
    putJson('/api/v1/me', ['name' => 'Test'])->assertUnauthorized();
    putJson('/api/v1/me/password', ['current_password' => 'test'])->assertUnauthorized();
    deleteJson('/api/v1/me')->assertUnauthorized();
});

// VALIDATION TESTS

/**
 * Test: Validates password update data
 * 
 * Verifies that password update properly validates current password
 * and new password requirements.
 * 
 * @return void
 */
it('validates password update data', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    // Missing current password
    $resp = putJson('/api/v1/me/password', [
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ]);
    $resp->assertUnprocessable();

    // Password confirmation mismatch
    $resp = putJson('/api/v1/me/password', [
        'current_password' => 'CurrentPassword123',
        'password' => 'NewPassword123',
        'password_confirmation' => 'DifferentPassword123',
    ]);
    $resp->assertUnprocessable();
});

/**
 * Test: Validates profile update data
 * 
 * Verifies that profile update properly validates email uniqueness
 * and other field requirements.
 * 
 * @return void
 */
it('validates profile update data', function () {
    $user = createUserWithRole('user');
    $otherUser = User::factory()->create(['email' => 'existing@example.com']);
    Sanctum::actingAs($user);

    // Duplicate email
    $resp = putJson('/api/v1/me', [
        'email' => 'existing@example.com',
    ]);
    $resp->assertUnprocessable();

    // Invalid email format
    $resp = putJson('/api/v1/me', [
        'email' => 'invalid-email',
    ]);
    $resp->assertUnprocessable();
});

// ROLE-SPECIFIC PROFILE TESTS

/**
 * Test: Staff can access their own profile
 * 
 * Verifies that staff users can access their own profile information.
 * 
 * @return void
 */
it('staff can access their own profile', function () {
    $user = createUserWithRole('staff');
    Sanctum::actingAs($user);
    
    $resp = getJson('/api/v1/me');
    $resp->assertOk()->assertJsonPath('data.user.id', $user->id);
});

/**
 * Test: Admin can access their own profile
 * 
 * Verifies that admin users can access their own profile information.
 * 
 * @return void
 */
it('admin can access their own profile', function () {
    $user = createUserWithRole('admin');
    Sanctum::actingAs($user);
    
    $resp = getJson('/api/v1/me');
    $resp->assertOk()->assertJsonPath('data.user.id', $user->id);
});

/**
 * Test: Superuser can access their own profile
 * 
 * Verifies that superuser can access their own profile information.
 * 
 * @return void
 */
it('superuser can access their own profile', function () {
    $user = createUserWithRole('superuser');
    Sanctum::actingAs($user);
    
    $resp = getJson('/api/v1/me');
    $resp->assertOk()->assertJsonPath('data.user.id', $user->id);
});

// TOKEN REVOCATION TESTS

/**
 * Test: Password update revokes all tokens
 * 
 * Verifies that when a user updates their password, all existing
 * tokens are revoked for security.
 * 
 * @return void
 */
it('password update revokes all tokens', function () {
    $user = createUserWithRole('user');
    $user->update(['password' => Hash::make('OldPassword123')]);
    
    // Create some tokens
    $user->createToken('test-token-1');
    $user->createToken('test-token-2');
    
    Sanctum::actingAs($user);
    
    $resp = putJson('/api/v1/me/password', [
        'current_password' => 'OldPassword123',
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ]);
    $resp->assertOk();
    
    // Verify all tokens are revoked
    expect($user->fresh()->tokens()->count())->toBe(0);
});

/**
 * Test: Profile deletion revokes all tokens
 * 
 * Verifies that when a user deletes their profile, all existing
 * tokens are revoked.
 * 
 * @return void
 */
it('profile deletion revokes all tokens', function () {
    $user = createUserWithRole('user');
    
    // Create some tokens
    $user->createToken('test-token-1');
    $user->createToken('test-token-2');
    
    Sanctum::actingAs($user);
    
    $resp = deleteJson('/api/v1/me');
    $resp->assertOk();
    
    // Verify all tokens are revoked
    expect($user->fresh()->tokens()->count())->toBe(0);
});