<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

/**
 * AuthController Test Suite
 * 
 * Comprehensive tests for all authentication endpoints including:
 * - User registration with validation
 * - User login with credentials
 * - User profile retrieval
 * - User logout functionality
 * - Password reset flow (forgot/reset)
 * - Role-based logout functionality
 * 
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

/**
 * Test: User Registration - Success
 * 
 * Verifies that a user can successfully register with valid data
 * and receive a token for immediate authentication.
 * 
 * @return void
 */
it('user can register with valid data', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = postJson('/api/v1/auth/register', $userData);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User successfully created')
        ->assertJsonPath('data.token', fn($token) => !empty($token))
        ->assertJsonPath('data.user.name', 'John Doe')
        ->assertJsonPath('data.user.email', 'john@example.com');

    // Verify user was created in database
    expect(User::where('email', 'john@example.com')->exists())->toBeTrue();
});

/**
 * Test: User Registration - Validation Errors
 * 
 * Verifies that registration fails with appropriate validation errors
 * for invalid or missing data.
 * 
 * @return void
 */
it('user registration fails with invalid data', function () {
    $invalidData = [
        'name' => '', // Empty name
        'email' => 'invalid-email', // Invalid email
        'password' => '123', // Too short
        'password_confirmation' => 'different', // Mismatch
    ];

    $response = postJson('/api/v1/auth/register', $invalidData);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Registration details error')
        ->assertJsonStructure(['data' => ['error']]);
});

/**
 * Test: User Registration - Duplicate Email
 * 
 * Verifies that registration fails when attempting to use
 * an email address that already exists.
 * 
 * @return void
 */
it('user registration fails with duplicate email', function () {
    // Create existing user
    User::factory()->create(['email' => 'existing@example.com']);

    $userData = [
        'name' => 'New User',
        'email' => 'existing@example.com', // Duplicate email
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = postJson('/api/v1/auth/register', $userData);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Registration details error');
});

/**
 * Test: User Login - Success
 * 
 * Verifies that a user can successfully log in with valid credentials
 * and receive a token for authentication.
 * 
 * @return void
 */
it('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    $loginData = [
        'email' => 'user@example.com',
        'password' => 'password123',
    ];

    $response = postJson('/api/v1/auth/login', $loginData);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Login successful')
        ->assertJsonPath('data.token', fn($token) => !empty($token))
        ->assertJsonPath('data.user.email', 'user@example.com');
});

/**
 * Test: User Login - Invalid Credentials
 * 
 * Verifies that login fails with appropriate error message
 * when invalid credentials are provided.
 * 
 * @return void
 */
it('user login fails with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    $invalidData = [
        'email' => 'user@example.com',
        'password' => 'wrongpassword',
    ];

    $response = postJson('/api/v1/auth/login', $invalidData);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid credentials');
});

/**
 * Test: User Login - Non-existent User
 * 
 * Verifies that login fails when attempting to log in
 * with a non-existent email address.
 * 
 * @return void
 */
it('user login fails with non-existent email', function () {
    $loginData = [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ];

    $response = postJson('/api/v1/auth/login', $loginData);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid credentials');
});

/**
 * Test: User Login - Validation Errors
 * 
 * Verifies that login fails with appropriate validation errors
 * for missing or invalid data.
 * 
 * @return void
 */
it('user login fails with validation errors', function () {
    $invalidData = [
        'email' => 'invalid-email', // Invalid email format
        'password' => '', // Empty password
    ];

    $response = postJson('/api/v1/auth/login', $invalidData);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid credentials');
});

/**
 * Test: User Profile - Authenticated Access
 * 
 * Verifies that an authenticated user can retrieve their profile
 * information successfully.
 * 
 * @return void
 */
it('authenticated user can get profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson('/api/v1/auth/profile');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User profile request successful')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.user.name', $user->name);
});

/**
 * Test: User Profile - Unauthenticated Access
 * 
 * Verifies that unauthenticated users cannot access the profile endpoint
 * and receive appropriate error response.
 * 
 * @return void
 */
it('unauthenticated user cannot get profile', function () {
    $response = getJson('/api/v1/auth/profile');

    $response->assertStatus(401);
});

/**
 * Test: User Logout - Success
 * 
 * Verifies that an authenticated user can successfully log out
 * and their tokens are revoked.
 * 
 * @return void
 */
it('authenticated user can logout', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create a token to verify it gets revoked
    $token = $user->createToken('test-token');
    expect($user->tokens()->count())->toBe(1);

    $response = postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logout successful');

    // Verify token was revoked
    expect($user->fresh()->tokens()->count())->toBe(0);
});

/**
 * Test: User Logout - Unauthenticated Access
 * 
 * Verifies that unauthenticated users cannot access the logout endpoint
 * and receive appropriate error response.
 * 
 * @return void
 */
it('unauthenticated user cannot logout', function () {
    $response = postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});

/**
 * Test: Password Reset - Forgot Password Success
 * 
 * Verifies that a user can request a password reset link
 * with a valid email address.
 * 
 * @return void
 */
it('user can request password reset with valid email', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $response = postJson('/api/v1/auth/password/forgot', [
        'email' => 'user@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);
});

/**
 * Test: Password Reset - Forgot Password Invalid Email
 * 
 * Verifies that password reset request fails with appropriate error
 * when using a non-existent email address.
 * 
 * @return void
 */
it('password reset fails with non-existent email', function () {
    $response = postJson('/api/v1/auth/password/forgot', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

/**
 * Test: Password Reset - Forgot Password Validation
 * 
 * Verifies that password reset request fails with validation errors
 * for invalid email format.
 * 
 * @return void
 */
it('password reset fails with invalid email format', function () {
    $response = postJson('/api/v1/auth/password/forgot', [
        'email' => 'invalid-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

/**
 * Test: Password Reset - Reset Password Success
 * 
 * Verifies that a user can reset their password using a valid token.
 * 
 * @return void
 */
it('user can reset password with valid token', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);
    
    // Mock the password reset token
    $token = Password::createToken($user);

    $response = postJson('/api/v1/auth/password/reset', [
        'email' => 'user@example.com',
        'token' => $token,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);

    // Verify password was changed
    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

/**
 * Test: Password Reset - Reset Password Invalid Token
 * 
 * Verifies that password reset fails with appropriate error
 * when using an invalid or expired token.
 * 
 * @return void
 */
it('password reset fails with invalid token', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $response = postJson('/api/v1/auth/password/reset', [
        'email' => 'user@example.com',
        'token' => 'invalid-token',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

/**
 * Test: Password Reset - Reset Password Validation
 * 
 * Verifies that password reset fails with validation errors
 * for missing or invalid data.
 * 
 * @return void
 */
it('password reset fails with validation errors', function () {
    $response = postJson('/api/v1/auth/password/reset', [
        'email' => 'invalid-email',
        'token' => '',
        'password' => '123', // Too short
        'password_confirmation' => 'different', // Mismatch
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

/**
 * Test: Logout by Role - Success
 * 
 * Verifies that an admin can logout all users with a specific role
 * and receive confirmation of the action.
 * 
 * @return void
 */
it('admin can logout users by role', function () {
    // Create users with different roles
    $user1 = createUserWithRole('user');
    $user2 = createUserWithRole('user');
    $staff = createUserWithRole('staff');
    
    // Create tokens for users
    $user1->createToken('test-token');
    $user2->createToken('test-token');
    $staff->createToken('test-token');

    // Act as admin
    $admin = createUserWithRole('admin');
    Sanctum::actingAs($admin);

    $response = postJson('/api/v1/auth/logout/role/user');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logout by role completed')
        ->assertJsonPath('data.role', 'user')
        ->assertJsonPath('data.users_affected', 2);

    // Verify user tokens were revoked
    expect($user1->fresh()->tokens()->count())->toBe(0);
    expect($user2->fresh()->tokens()->count())->toBe(0);
    expect($staff->fresh()->tokens()->count())->toBe(1); // Staff token should remain
});


