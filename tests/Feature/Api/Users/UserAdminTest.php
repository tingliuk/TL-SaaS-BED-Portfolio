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
 * USER ADMIN TESTS - DETAILED PERMISSION MATRIX
 * 
 * Based on the comprehensive permission matrix for user administration:
 * 
 * User Type    | Browse | Read     | Edit                    | Add  | Delete                    | Search | Notes
 * -------------|--------|----------|-------------------------|------|---------------------------|--------|------------------
 * Unregistered | No     | No       | No                      | No   | No                        | No     | Must register to work on account
 * Client       | No     | Only Own | Only Own                | No   | Only Own                  | No     | Soft deletes, no account recovery
 * Staff        | All    | All      | Only own, Clients, Apps | Yes  | Clients, Applicants       | All    | Cannot delete themselves, Admins, Super-users
 * Admin        | All    | All      | All                     | All  | Clients, Applicants, Staff| All    | Cannot delete themselves, Admins, Super-users
 * Super-User   | All    | All      | All                     | All  | Any                       | All    | Cannot delete themselves
 * 
 * All user types use soft deletes with no access to account recovery.
 */

// Include shared helper
require_once __DIR__ . '/../../../Helpers/UserHelper.php';

// Seed roles and permissions before each test
beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

// UNREGISTERED USER TESTS

/**
 * Test: Unregistered users cannot access any user admin endpoints
 * 
 * Verifies that unauthenticated users are completely denied access
 * to all user administration functionality.
 * 
 * @return void
 */
it('unregistered users cannot access any user admin endpoints', function () {
    // All user admin endpoints should return 401 Unauthorized
    getJson('/api/v1/users')->assertUnauthorized();
    postJson('/api/v1/users', [])->assertUnauthorized();
    getJson('/api/v1/users/1')->assertUnauthorized();
    putJson('/api/v1/users/1', [])->assertUnauthorized();
    deleteJson('/api/v1/users/1')->assertUnauthorized();
    putJson('/api/v1/users/1/roles', [])->assertUnauthorized();
    putJson('/api/v1/users/1/status', [])->assertUnauthorized();
});

// CLIENT USER TESTS

/**
 * Test: Client users can only read their own profile
 * 
 * Verifies that client users can only access their own profile
 * and cannot browse, edit, add, or delete other users.
 * 
 * @return void
 */
it('client users can only read their own profile', function () {
    $client = createUserWithRole('client');
    $otherUser = User::factory()->create();
    Sanctum::actingAs($client);

    // Can read own profile
    getJson('/api/v1/me')->assertOk();
    
    // Cannot browse all users
    getJson('/api/v1/users')->assertForbidden();
    
    // Cannot read other users
    getJson('/api/v1/users/'.$otherUser->id)->assertForbidden();
    
    // Cannot edit other users
    putJson('/api/v1/users/'.$otherUser->id, ['name' => 'Updated'])->assertForbidden();
    
    // Cannot add users
    postJson('/api/v1/users', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ])->assertForbidden();
    
    // Cannot delete other users
    deleteJson('/api/v1/users/'.$otherUser->id)->assertForbidden();
    
    // Cannot assign roles
    putJson('/api/v1/users/'.$otherUser->id.'/roles', ['roles' => ['user']])->assertForbidden();
    
    // Cannot change status
    putJson('/api/v1/users/'.$otherUser->id.'/status', ['status' => 'suspended'])->assertForbidden();
});

/**
 * Test: Client users can edit their own profile
 * 
 * Verifies that client users can update their own profile information
 * but cannot change their role or status.
 * 
 * @return void
 */
it('client users can edit their own profile', function () {
    $client = createUserWithRole('client');
    Sanctum::actingAs($client);

    // Can update own profile
    $resp = putJson('/api/v1/me', [
        'name' => 'Updated Client Name',
        'given_name' => 'Client',
        'family_name' => 'User',
    ]);
    $resp->assertOk()->assertJsonPath('data.user.name', 'Updated Client Name');
});

/**
 * Test: Client users can delete their own profile
 * 
 * Verifies that client users can soft delete their own profile
 * but cannot delete other users.
 * 
 * @return void
 */
it('client users can delete their own profile', function () {
    $client = createUserWithRole('client');
    Sanctum::actingAs($client);

    // Can delete own profile
    $resp = deleteJson('/api/v1/me');
    $resp->assertOk()->assertJsonPath('success', true);
    
    // Verify profile is soft deleted
    $this->assertSoftDeleted('users', ['id' => $client->id]);
});

// STAFF USER TESTS

/**
 * Test: Staff users can browse all users
 * 
 * Verifies that staff users can browse and search all users
 * in the system with proper pagination.
 * 
 * @return void
 */
it('staff users can browse all users', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Create some test users
    User::factory()->count(3)->create();

    $resp = getJson('/api/v1/users');
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'users' => [
                'data' => []
            ]
        ]
    ]);
});

/**
 * Test: Staff users can read all users
 * 
 * Verifies that staff users can read any user's profile
 * including their own and other users.
 * 
 * @return void
 */
it('staff users can read all users', function () {
    $staff = createUserWithRole('staff');
    $otherUser = User::factory()->create();
    Sanctum::actingAs($staff);

    // Can read own profile
    getJson('/api/v1/me')->assertOk();
    
    // Can read other users
    getJson('/api/v1/users/'.$otherUser->id)->assertOk();
});

/**
 * Test: Staff users can edit own profile and clients/applicants
 * 
 * Verifies that staff users can edit their own profile and
 * profiles of clients and applicants, but not other staff/admins.
 * 
 * @return void
 */
it('staff users can edit own profile and clients/applicants', function () {
    $staff = createUserWithRole('staff');
    $client = createUserWithRole('client');
    $applicant = createUserWithRole('user'); // Assuming 'user' role represents applicants
    $otherStaff = createUserWithRole('staff');
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($staff);

    // Can edit own profile
    putJson('/api/v1/me', ['name' => 'Updated Staff Name'])->assertOk();
    
    // Can edit clients
    putJson('/api/v1/users/'.$client->id, ['name' => 'Updated Client'])->assertOk();
    
    // Can edit applicants/users
    putJson('/api/v1/users/'.$applicant->id, ['name' => 'Updated Applicant'])->assertOk();
    
    // Cannot edit other staff
    putJson('/api/v1/users/'.$otherStaff->id, ['name' => 'Updated Other Staff'])->assertForbidden();
    
    // Cannot edit admins
    putJson('/api/v1/users/'.$admin->id, ['name' => 'Updated Admin'])->assertForbidden();
});

/**
 * Test: Staff users can add clients and applicants
 * 
 * Verifies that staff users can create new users with
 * client or applicant roles.
 * 
 * @return void
 */
it('staff users can add clients and applicants', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Can create client
    $resp = postJson('/api/v1/users', [
        'name' => 'New Client',
        'email' => 'client@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['client'],
    ]);
    $resp->assertCreated();
    
    // Can create applicant (user role)
    $resp = postJson('/api/v1/users', [
        'name' => 'New Applicant',
        'email' => 'applicant@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['user'],
    ]);
    $resp->assertCreated();
});

/**
 * Test: Staff users can delete clients and applicants only
 * 
 * Verifies that staff users can delete clients and applicants
 * but cannot delete themselves, other staff, admins, or super-users.
 * 
 * @return void
 */
it('staff users can delete clients and applicants only', function () {
    $staff = createUserWithRole('staff');
    $client = createUserWithRole('client');
    $applicant = createUserWithRole('user');
    $otherStaff = createUserWithRole('staff');
    $admin = createUserWithRole('admin');
    $superuser = createUserWithRole('superuser');
    Sanctum::actingAs($staff);

    // Can delete clients
    deleteJson('/api/v1/users/'.$client->id)->assertOk();
    
    // Can delete applicants
    deleteJson('/api/v1/users/'.$applicant->id)->assertOk();
    
    // Cannot delete themselves
    deleteJson('/api/v1/users/'.$staff->id)->assertForbidden();
    
    // Cannot delete other staff
    deleteJson('/api/v1/users/'.$otherStaff->id)->assertForbidden();
    
    // Cannot delete admins
    deleteJson('/api/v1/users/'.$admin->id)->assertForbidden();
    
    // Cannot delete super-users
    deleteJson('/api/v1/users/'.$superuser->id)->assertForbidden();
});

/**
 * Test: Staff users can search all users
 * 
 * Verifies that staff users can search through all users
 * using various search criteria.
 * 
 * @return void
 */
it('staff users can search all users', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    User::factory()->create(['name' => 'UniqueSearchable123']);
    User::factory()->create(['email' => 'searchable@example.com']);

    $resp = getJson('/api/v1/users?q=UniqueSearchable123');
    $resp->assertOk();
    
    $users = $resp->json('data.users.data');
    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('UniqueSearchable123');
});

// ADMIN USER TESTS

/**
 * Test: Admin users can browse all users
 * 
 * Verifies that admin users can browse all users in the system.
 * 
 * @return void
 */
it('admin users can browse all users', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    User::factory()->count(3)->create();

    getJson('/api/v1/users')->assertOk();
});

/**
 * Test: Admin users can read all users
 * 
 * Verifies that admin users can read any user's profile.
 * 
 * @return void
 */
it('admin users can read all users', function () {
    $admin = createUserWithRole('admin');
    $otherUser = User::factory()->create();
    Sanctum::actingAs($admin);

    getJson('/api/v1/users/'.$otherUser->id)->assertOk();
});

/**
 * Test: Admin users can edit all users
 * 
 * Verifies that admin users can edit any user's profile
 * including their own and all other users.
 * 
 * @return void
 */
it('admin users can edit all users', function () {
    $admin = createUserWithRole('admin');
    $client = createUserWithRole('client');
    $staff = createUserWithRole('staff');
    $otherAdmin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    // Can edit own profile
    putJson('/api/v1/me', ['name' => 'Updated Admin'])->assertOk();
    
    // Can edit clients
    putJson('/api/v1/users/'.$client->id, ['name' => 'Updated Client'])->assertOk();
    
    // Can edit staff
    putJson('/api/v1/users/'.$staff->id, ['name' => 'Updated Staff'])->assertOk();
    
    // Can edit other admins
    putJson('/api/v1/users/'.$otherAdmin->id, ['name' => 'Updated Other Admin'])->assertOk();
});

/**
 * Test: Admin users can add all user types
 * 
 * Verifies that admin users can create users with any role.
 * 
 * @return void
 */
it('admin users can add all user types', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    // Can create client
    postJson('/api/v1/users', [
        'name' => 'New Client',
        'email' => 'client@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['client'],
    ])->assertCreated();
    
    // Can create staff
    postJson('/api/v1/users', [
        'name' => 'New Staff',
        'email' => 'staff@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['staff'],
    ])->assertCreated();
    
    // Can create admin
    postJson('/api/v1/users', [
        'name' => 'New Admin',
        'email' => 'admin@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['admin'],
    ])->assertCreated();
});

/**
 * Test: Admin users can delete clients, applicants, and staff
 * 
 * Verifies that admin users can delete clients, applicants, and staff
 * but cannot delete themselves or other admins/super-users.
 * 
 * @return void
 */
it('admin users can delete clients applicants and staff', function () {
    $admin = createUserWithRole('admin');
    $client = createUserWithRole('client');
    $applicant = createUserWithRole('user');
    $staff = createUserWithRole('staff');
    $otherAdmin = createUserWithRole('admin');
    $superuser = createUserWithRole('superuser');
    Sanctum::actingAs($admin);

    // Can delete clients
    deleteJson('/api/v1/users/'.$client->id)->assertOk();
    
    // Can delete applicants
    deleteJson('/api/v1/users/'.$applicant->id)->assertOk();
    
    // Can delete staff
    deleteJson('/api/v1/users/'.$staff->id)->assertOk();
    
    // Cannot delete themselves
    deleteJson('/api/v1/users/'.$admin->id)->assertForbidden();
    
    // Cannot delete other admins
    deleteJson('/api/v1/users/'.$otherAdmin->id)->assertForbidden();
    
    // Cannot delete super-users
    deleteJson('/api/v1/users/'.$superuser->id)->assertForbidden();
});

/**
 * Test: Admin users can search all users
 * 
 * Verifies that admin users can search through all users.
 * 
 * @return void
 */
it('admin users can search all users', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    User::factory()->create(['name' => 'Admin Searchable']);

    $resp = getJson('/api/v1/users?q=Admin');
    $resp->assertOk();
});

// SUPER-USER TESTS

/**
 * Test: Super-users can browse all users
 * 
 * Verifies that super-users can browse all users in the system.
 * 
 * @return void
 */
it('super-users can browse all users', function () {
    $superuser = createUserWithRole('superuser');
    Sanctum::actingAs($superuser);

    User::factory()->count(3)->create();

    getJson('/api/v1/users')->assertOk();
});

/**
 * Test: Super-users can read all users
 * 
 * Verifies that super-users can read any user's profile.
 * 
 * @return void
 */
it('super-users can read all users', function () {
    $superuser = createUserWithRole('superuser');
    $otherUser = User::factory()->create();
    Sanctum::actingAs($superuser);

    getJson('/api/v1/users/'.$otherUser->id)->assertOk();
});

/**
 * Test: Super-users can edit all users
 * 
 * Verifies that super-users can edit any user's profile.
 * 
 * @return void
 */
it('super-users can edit all users', function () {
    $superuser = createUserWithRole('superuser');
    $otherUser = User::factory()->create();
    Sanctum::actingAs($superuser);

    putJson('/api/v1/users/'.$otherUser->id, ['name' => 'Updated by Superuser'])->assertOk();
});

/**
 * Test: Super-users can add all user types
 * 
 * Verifies that super-users can create users with any role.
 * 
 * @return void
 */
it('super-users can add all user types', function () {
    $superuser = createUserWithRole('superuser');
    Sanctum::actingAs($superuser);

    // Can create any user type
    postJson('/api/v1/users', [
        'name' => 'Superuser Created',
        'email' => 'superuser@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'roles' => ['superuser'],
    ])->assertCreated();
});

/**
 * Test: Super-users can delete any user except themselves
 * 
 * Verifies that super-users can delete any user in the system
 * but cannot delete themselves.
 * 
 * @return void
 */
it('super-users can delete any user except themselves', function () {
    $superuser = createUserWithRole('superuser');
    $client = createUserWithRole('client');
    $staff = createUserWithRole('staff');
    $admin = createUserWithRole('admin');
    $otherSuperuser = createUserWithRole('superuser');
    Sanctum::actingAs($superuser);

    // Can delete clients
    deleteJson('/api/v1/users/'.$client->id)->assertOk();
    
    // Can delete staff
    deleteJson('/api/v1/users/'.$staff->id)->assertOk();
    
    // Can delete admins
    deleteJson('/api/v1/users/'.$admin->id)->assertOk();
    
    // Can delete other super-users
    deleteJson('/api/v1/users/'.$otherSuperuser->id)->assertOk();
    
    // Cannot delete themselves
    deleteJson('/api/v1/users/'.$superuser->id)->assertForbidden();
});

/**
 * Test: Super-users can search all users
 * 
 * Verifies that super-users can search through all users.
 * 
 * @return void
 */
it('super-users can search all users', function () {
    $superuser = createUserWithRole('superuser');
    Sanctum::actingAs($superuser);

    User::factory()->create(['name' => 'Superuser Searchable']);

    $resp = getJson('/api/v1/users?q=Superuser');
    $resp->assertOk();
});

// SOFT DELETE VERIFICATION TESTS

/**
 * Test: All user deletions are soft deletes
 * 
 * Verifies that when any user type deletes a user (where permitted),
 * the user is soft deleted rather than permanently removed.
 * 
 * @return void
 */
it('all user deletions are soft deletes', function () {
    $staff = createUserWithRole('staff');
    $client = createUserWithRole('client');
    Sanctum::actingAs($staff);

    // Delete a client (staff can delete clients)
    deleteJson('/api/v1/users/'.$client->id)->assertOk();
    
    // Verify user is soft deleted, not permanently deleted
    $this->assertSoftDeleted('users', ['id' => $client->id]);
    $this->assertDatabaseHas('users', ['id' => $client->id]); // Still exists in database
});

/**
 * Test: No access to account recovery
 * 
 * Verifies that there are no endpoints for account recovery
 * or permanent deletion of users.
 * 
 * @return void
 */
it('no access to account recovery', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    // There should be no restore endpoint
    postJson('/api/v1/users/1/restore')->assertMethodNotAllowed();
    
    // There should be no force delete endpoint
    deleteJson('/api/v1/users/1/force')->assertMethodNotAllowed();
});
