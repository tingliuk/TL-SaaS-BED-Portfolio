<?php

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
 * CATEGORIES API TESTS - STREAMLINED
 * 
 * Essential category tests covering core requirements:
 * - Basic CRUD operations
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
 * Test: Logged in user can retrieve categories
 * 
 * Verifies that authenticated users with 'client' role (level 100+) can successfully
 * browse and retrieve categories. This is the core requirement for category access.
 * 
 * @return void
 */
it('logged in user can retrieve categories', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    Category::factory()->count(3)->create();

    $resp = getJson('/api/v1/categories');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: Guest cannot access categories
 * 
 * Verifies that unauthenticated users cannot access category endpoints,
 * ensuring proper security for protected resources.
 * 
 * @return void
 */
it('guest cannot access categories', function () {
    $resp = getJson('/api/v1/categories');
    $resp->assertUnauthorized();
});

/**
 * Test: Staff can perform full CRUD operations
 * 
 * Verifies that staff users (level 500+) can create, read, update, and delete
 * categories, demonstrating the full permission set for staff role.
 * 
 * @return void
 */
it('staff can perform full CRUD operations', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Create
    $resp = postJson('/api/v1/categories', [
        'name' => 'Test Category',
        'description' => 'Test Description'
    ]);
    $resp->assertCreated();

    $category = Category::where('name', 'Test Category')->first();

    // Read
    $resp = getJson('/api/v1/categories/' . $category->id);
    $resp->assertOk();

    // Update
    $resp = putJson('/api/v1/categories/' . $category->id, [
        'name' => 'Updated Category'
    ]);
    $resp->assertOk();

    // Delete (soft delete)
    $resp = deleteJson('/api/v1/categories/' . $category->id);
    $resp->assertOk();

    // Verify soft delete
    expect($category->fresh()->deleted_at)->not->toBeNull();
});

/**
 * Test: Admin can permanently delete categories
 * 
 * Verifies that admin users (level 750+) can permanently delete categories,
 * demonstrating the elevated permissions for administrative roles.
 * 
 * @return void
 */
it('admin can permanently delete categories', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    $category = Category::factory()->create();
    $category->delete(); // Soft delete first

    $resp = deleteJson('/api/v1/categories/' . $category->id . '/force');
    $resp->assertOk();

    // Verify permanent deletion
    expect(Category::withTrashed()->find($category->id))->toBeNull();
});

/**
 * Test: Superuser has all permissions
 * 
 * Verifies that superuser role (level 999) has access to all category operations,
 * demonstrating the highest permission level in the system.
 * 
 * @return void
 */
it('superuser has all permissions', function () {
    $superuser = createUserWithRole('superuser');
    Sanctum::actingAs($superuser);

    // Can create
    $resp = postJson('/api/v1/categories', [
        'name' => 'Superuser Category',
        'description' => 'Created by superuser'
    ]);
    $resp->assertCreated();

    $category = Category::where('name', 'Superuser Category')->first();

    // Can read
    $resp = getJson('/api/v1/categories/' . $category->id);
    $resp->assertOk();

    // Can update
    $resp = putJson('/api/v1/categories/' . $category->id, [
        'name' => 'Updated by Superuser'
    ]);
    $resp->assertOk();

    // Can delete
    $resp = deleteJson('/api/v1/categories/' . $category->id);
    $resp->assertOk();
});

/**
 * Test: User can search categories
 * 
 * Verifies that authenticated users can search categories by name.
 * 
 * @return void
 */
it('client can search categories', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    Category::factory()->create(['name' => 'Technology']);
    Category::factory()->create(['name' => 'Science']);

    $resp = getJson('/api/v1/categories?q=Tech');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: User cannot delete categories
 * 
 * Verifies that regular users cannot delete categories.
 * 
 * @return void
 */
it('client cannot delete categories', function () {
    $user = createUserWithRole('client');
    Sanctum::actingAs($user);

    $category = Category::factory()->create();

    $resp = deleteJson('/api/v1/categories/' . $category->id);
    $resp->assertForbidden();
});

/**
 * Test: Staff can restore soft deleted categories
 * 
 * Verifies that staff can restore soft deleted categories.
 * 
 * @return void
 */
it('staff can restore soft deleted categories', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    $category = Category::factory()->create();
    $category->delete(); // Soft delete

    $resp = postJson('/api/v1/categories/' . $category->id . '/restore');
    $resp->assertOk();

    // Verify category is restored
    expect($category->fresh()->deleted_at)->toBeNull();
});
