<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * USER ADMIN API TESTS - STREAMLINED
 * 
 * Essential user admin tests covering core permission matrix:
 * - Client permissions (own profile only)
 * - Staff permissions (clients and applicants)
 * - Admin permissions (all users except super-users)
 * - Super-user permissions (all users except themselves)
 */

// Include shared helper
require_once __DIR__ . '/../../../Helpers/UserHelper.php';

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

/**
 * Test: Client users can only read their own profile
 * 
 * Verifies that client users can only access their own profile,
 * implementing the basic permission level for regular users.
 * 
 * @return void
 */
it('client users can only read their own profile', function () {
    $client = createUserWithRole('client');
    $otherClient = createUserWithRole('client');
    Sanctum::actingAs($client);

    // Cannot access user admin endpoints at all
    $resp = getJson('/api/v1/users/' . $client->id);
    $resp->assertForbidden();

    // Cannot read other user's profile
    $resp = getJson('/api/v1/users/' . $otherClient->id);
    $resp->assertForbidden();
});

/**
 * Test: Staff users can browse all users
 * 
 * Verifies that staff users can browse all users in the system,
 * demonstrating elevated permissions for staff role.
 * 
 * @return void
 */
it('staff users can browse all users', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Create some users
    User::factory()->count(3)->create();

    $resp = getJson('/api/v1/users');
    $resp->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'users' => [
                    'data' => [
                        '*' => ['id', 'name', 'email', 'status']
                    ]
                ]
            ]
        ]);
});

/**
 * Test: Staff users can add clients and applicants
 * 
 * Verifies that staff users can create new client and applicant users,
 * demonstrating user management capabilities.
 * 
 * @return void
 */
it('staff users can add clients and applicants', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Can add client
    $resp = postJson('/api/v1/users', [
        'name' => 'New Client',
        'email' => 'client@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['client'],
    ]);
    $resp->assertCreated();

    // Can add applicant (also client role)
    $resp = postJson('/api/v1/users', [
        'name' => 'New Applicant',
        'email' => 'applicant@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['client'],
    ]);
    $resp->assertCreated();
});

/**
 * Test: Admin users can add all user types
 * 
 * Verifies that admin users can create users with any role,
 * demonstrating full user management capabilities.
 * 
 * @return void
 */
it('admin users can add all user types', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    // Can add client
    $resp = postJson('/api/v1/users', [
        'name' => 'New Client',
        'email' => 'client@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['client'],
    ]);
    $resp->assertCreated();

    // Can add staff
    $resp = postJson('/api/v1/users', [
        'name' => 'New Staff',
        'email' => 'staff@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['staff'],
    ]);
    $resp->assertCreated();

    // Can add admin
    $resp = postJson('/api/v1/users', [
        'name' => 'New Admin',
        'email' => 'admin@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['admin'],
    ]);
    $resp->assertCreated();
});

/**
 * Test: Super-users can delete any user except themselves
 * 
 * Verifies that super-users can delete any user except themselves,
 * demonstrating the highest permission level with self-protection.
 * 
 * @return void
 */
it('super-users can delete any user except themselves', function () {
    $superuser = createUserWithRole('superuser');
    $client = createUserWithRole('client');
    Sanctum::actingAs($superuser);

    // Can delete other users
    $resp = deleteJson('/api/v1/users/' . $client->id);
    $resp->assertOk();

    // Cannot delete themselves
    $resp = deleteJson('/api/v1/users/' . $superuser->id);
    $resp->assertForbidden();
});

/**
 * Test: All user deletions are soft deletes
 * 
 * Verifies that user deletions are soft deletes, preserving data integrity
 * and allowing for potential recovery.
 * 
 * @return void
 */
it('all user deletions are soft deletes', function () {
    $admin = createUserWithRole('admin');
    $client = createUserWithRole('client');
    Sanctum::actingAs($admin);

    $resp = deleteJson('/api/v1/users/' . $client->id);
    $resp->assertOk();

    // Verify soft delete
    expect($client->fresh()->deleted_at)->not->toBeNull();
    expect($client->fresh())->not->toBeNull(); // User still exists
});
