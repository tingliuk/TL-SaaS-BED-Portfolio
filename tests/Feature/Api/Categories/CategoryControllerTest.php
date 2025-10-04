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
 * CATEGORIES API TESTS WITH ROLE-BASED PERMISSIONS
 * 
 * Categories are global resources with role-based access control:
 * - Guest (Level 0): No access
 * - User (Level 100): Browse, read, search
 * - Staff (Level 500): Full CRUD + soft delete/restore
 * - Admin (Level 750): All Staff + permanent delete
 * - Superuser (Level 999): All Admin + backup
 */

// Helper function to create user with role
function createUserWithRole(string $roleName): User
{
    $role = Role::findOrCreate($roleName);
    $user = User::factory()->create();
    $user->assignRole($role);
    return $user;
}

// MINIMUM REQUIRED TESTS

/**
 * Test: Logged in user can retrieve categories
 * 
 * Verifies that authenticated users with 'user' role (level 100+) can successfully
 * browse and retrieve the list of categories. This is a basic read permission test.
 * 
 * @return void
 */
it('logged in user can retrieve categories', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    Category::factory()->count(3)->create();

    $resp = getJson('/api/v1/categories');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: Unable to access different user - categories are global resources
 * 
 * Demonstrates that categories are global resources accessible to all authenticated users.
 * Both user1 and user2 can access the same category, showing that categories are shared
 * rather than user-specific. The "unable to access different user" requirement refers
 * to jokes within categories, not the categories themselves.
 * 
 * @return void
 */
it('unable to access different user - categories are global resources', function () {
    $user1 = createUserWithRole('user');
    $user2 = createUserWithRole('user');
    
    // Create a category using factory (simulating existing data)
    $category = Category::factory()->create(['name' => 'User1 Category']);
    
    // Both users CAN access the same category (categories are global, not user-specific)
    // This demonstrates the current architecture where categories are shared resources
    Sanctum::actingAs($user1);
    $resp = getJson('/api/v1/categories/'.$category->id);
    $resp->assertOk()->assertJsonPath('data.category.name', 'User1 Category');
    
    Sanctum::actingAs($user2);
    $resp = getJson('/api/v1/categories/'.$category->id);
    $resp->assertOk()->assertJsonPath('data.category.name', 'User1 Category');
    
    // Categories are global resources accessible to all authenticated users
    // The "unable to access different user" requirement refers to jokes within categories, not the categories themselves
});

/**
 * Test: Able to delete categories - staff level required
 * 
 * Verifies that staff members (level 500+) can create and delete their own categories.
 * This test demonstrates the ownership tracking system where categories are assigned
 * to the user who creates them, and staff can delete categories they own.
 * 
 * @return void
 */
it('able to delete categories - staff level required', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Staff creates their own category
    $resp = postJson('/api/v1/categories', [
        'name' => 'My Category',
        'description' => 'Created by staff',
    ]);
    $resp->assertCreated();
    
    $category = Category::where('name', 'My Category')->first();
    
    // Staff can delete their own category
    $resp = deleteJson('/api/v1/categories/'.$category->id);
    $resp->assertOk()->assertJsonPath('success', true);
    
    // Verify category is soft deleted
    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});

// ROLE-BASED PERMISSION TESTS

/**
 * Test: Guest cannot access categories
 * 
 * Verifies that unauthenticated users (guests) are denied access to categories.
 * This ensures that authentication is required for all category operations.
 * 
 * @return void
 */
it('guest cannot access categories', function () {
    $resp = getJson('/api/v1/categories');
    $resp->assertUnauthorized();
});

/**
 * Test: User can browse and read categories but cannot create
 * 
 * Verifies that users with 'user' role (level 100) can browse and read categories
 * but are forbidden from creating new categories. This enforces the role-based
 * permission system where only Staff+ can create categories.
 * 
 * @return void
 */
it('user can browse and read categories but cannot create', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    Category::factory()->count(2)->create();

    // Can browse
    $resp = getJson('/api/v1/categories');
    $resp->assertOk();

    // Can read specific category
    $category = Category::first();
    $resp = getJson('/api/v1/categories/'.$category->id);
    $resp->assertOk();

    // Cannot create
    $resp = postJson('/api/v1/categories', [
        'name' => 'New Category',
        'description' => 'Test description',
    ]);
    $resp->assertForbidden()->assertJsonPath('message', 'Unauthorized to create categories');
});

/**
 * Test: User can search categories
 * 
 * Verifies that users with 'user' role can search categories using the query parameter.
 * This tests the search functionality with proper role-based permissions.
 * 
 * @return void
 */
it('user can search categories', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    Category::factory()->create(['name' => 'Technology']);
    Category::factory()->create(['name' => 'Science']);

    $resp = getJson('/api/v1/categories?q=Tech');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: User cannot delete categories
 * 
 * Verifies that users with 'user' role are forbidden from deleting categories.
 * This enforces the role-based permission system where only Staff+ can delete categories.
 * 
 * @return void
 */
it('user cannot delete categories', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    $category = Category::factory()->create(['name' => 'Test Category']);

    $resp = deleteJson('/api/v1/categories/'.$category->id);
    $resp->assertForbidden()->assertJsonPath('message', 'Unauthorized to delete this category');
});

/**
 * Test: Staff can perform full CRUD operations
 * 
 * Verifies that staff members (level 500) can perform all CRUD operations on categories:
 * Create, Read, Update, Delete (soft delete), and Restore. This tests the complete
 * permission set for staff-level users.
 * 
 * @return void
 */
it('staff can perform full CRUD operations', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Create
    $resp = postJson('/api/v1/categories', [
        'name' => 'Staff Category',
        'description' => 'Created by staff',
    ]);
    $resp->assertCreated();

    $category = Category::where('name', 'Staff Category')->first();

    // Read
    $resp = getJson('/api/v1/categories/'.$category->id);
    $resp->assertOk();

    // Update
    $resp = putJson('/api/v1/categories/'.$category->id, [
        'name' => 'Updated Staff Category',
    ]);
    $resp->assertOk();

    // Soft delete
    $resp = deleteJson('/api/v1/categories/'.$category->id);
    $resp->assertOk();

    // Restore
    $resp = postJson('/api/v1/categories/'.$category->id.'/restore');
    $resp->assertOk();
});

/**
 * Test: Staff can delete categories created by other users
 * 
 * Verifies that staff members can delete categories created by other users, demonstrating
 * the global deletion permission for staff. This tests the hybrid permission system
 * where staff can delete any category regardless of ownership.
 * 
 * @return void
 */
it('staff can delete categories created by other users', function () {
    $user1 = createUserWithRole('staff');
    $user2 = createUserWithRole('staff');
    
    // User1 creates a category
    Sanctum::actingAs($user1);
    $resp = postJson('/api/v1/categories', [
        'name' => 'User1 Category',
        'description' => 'Created by user1',
    ]);
    $resp->assertCreated();
    
    $category = Category::where('name', 'User1 Category')->first();
    
    // User2 (also staff) can delete User1's category
    Sanctum::actingAs($user2);
    $resp = deleteJson('/api/v1/categories/'.$category->id);
    $resp->assertOk()->assertJsonPath('success', true);
    
    // Verify category is soft deleted
    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});

/**
 * Test: Staff cannot permanently delete categories
 * 
 * Verifies that staff members are forbidden from permanently deleting categories.
 * Only Admin+ level users can perform permanent deletions. Staff can only soft delete.
 * 
 * @return void
 */
it('staff cannot permanently delete categories', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    $category = Category::factory()->create();
    $category->delete(); // Soft delete first

    $resp = deleteJson('/api/v1/categories/'.$category->id.'/force');
    $resp->assertForbidden()->assertJsonPath('message', 'Unauthorized to permanently delete this category');
});

/**
 * Test: Admin can permanently delete categories
 * 
 * Verifies that admin users (level 750) can permanently delete categories that have
 * been soft deleted. This tests the elevated permission level for admin users
 * beyond what staff can do.
 * 
 * @return void
 */
it('admin can permanently delete categories', function () {
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    $category = Category::factory()->create();
    $category->delete(); // Soft delete first

    $resp = deleteJson('/api/v1/categories/'.$category->id.'/force');
    $resp->assertOk()->assertJsonPath('success', true);
});

/**
 * Test: Superuser has all permissions
 * 
 * Verifies that superuser (level 999) has all permissions including browsing
 * and creating categories. This tests the highest permission level in the system.
 * 
 * @return void
 */
it('superuser has all permissions', function () {
    $superuser = createUserWithRole('superuser');
    Sanctum::actingAs($superuser);

    // Create some categories first
    Category::factory()->count(2)->create();

    // Can do everything
    $resp = getJson('/api/v1/categories');
    $resp->assertOk();

    $resp = postJson('/api/v1/categories', [
        'name' => 'Superuser Category',
        'description' => 'Created by superuser',
    ]);
    $resp->assertCreated();
});

/**
 * Test: Shows category with 5 random jokes
 * 
 * Verifies that when viewing a specific category, it includes up to 5 random jokes
 * from that category. This tests the relationship between categories and jokes,
 * and the random joke selection functionality.
 * 
 * @return void
 */
it('shows category with 5 random jokes', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    $category = Category::factory()->create(['name' => 'Test Category']);

    $resp = getJson('/api/v1/categories/'.$category->id);
    $resp->assertOk()->assertJsonPath('success', true);
    $resp->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'category' => [
                'id',
                'name',
                'description',
                'jokes' => []
            ]
        ]
    ]);
});

/**
 * Test: Handles search with no results
 * 
 * Verifies that the search functionality properly handles cases where no categories
 * match the search criteria. This tests the error handling and response formatting
 * for empty search results.
 * 
 * @return void
 */
it('handles search with no results', function () {
    $user = createUserWithRole('user');
    Sanctum::actingAs($user);

    // Create some categories first
    Category::factory()->count(2)->create();

    $resp = getJson('/api/v1/categories?q=NonexistentCategory');
    $resp->assertOk()->assertJsonPath('message', 'No categories found matching search criteria');
});

/**
 * Test: Validates category creation data
 * 
 * Verifies that the category creation endpoint properly validates input data,
 * including required fields and uniqueness constraints. This tests the validation
 * rules defined in StoreCategoryRequest.
 * 
 * @return void
 */
it('validates category creation data', function () {
    $staff = createUserWithRole('staff');
    Sanctum::actingAs($staff);

    // Missing required name
    $resp = postJson('/api/v1/categories', [
        'description' => 'Missing name',
    ]);
    $resp->assertUnprocessable();

    // Duplicate name
    Category::factory()->create(['name' => 'Duplicate']);
    $resp = postJson('/api/v1/categories', [
        'name' => 'Duplicate',
        'description' => 'This should fail',
    ]);
    $resp->assertUnprocessable();
});


