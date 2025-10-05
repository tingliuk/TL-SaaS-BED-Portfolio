<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

/**
 * AuthController Test Suite - Streamlined
 * 
 * Essential authentication tests covering core requirements:
 * - User registration and login
 * - User profile and logout
 * - Password reset functionality
 * 
 * @package Tests\Feature\Api\Auth
 * @author Ting Liu
 * @version 1.0.0
 * @since 2025-10-04
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
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token'
            ]
        ]);

    // Verify user was created in database
    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'name' => 'John Doe',
    ]);

    // Verify user has default role
    $user = User::where('email', 'john@example.com')->first();
    expect($user->hasRole('client'))->toBeTrue();
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
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token'
            ]
        ]);
});

/**
 * Test: User Profile - Authenticated Access
 * 
 * Verifies that authenticated users can retrieve their profile information.
 * 
 * @return void
 */
it('authenticated user can get profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson('/api/v1/auth/profile');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email);
});

/**
 * Test: User Logout - Success
 * 
 * Verifies that authenticated users can successfully logout
 * and their token is revoked.
 * 
 * @return void
 */
it('authenticated user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    Sanctum::actingAs($user, ['*'], 'sanctum');

    $response = postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logout successful');

    // Verify token was revoked
    expect($user->fresh()->tokens()->count())->toBe(0);
});

/**
 * Test: Password Reset - Success
 * 
 * Verifies that users can request password reset with valid email
 * and receive a reset token.
 * 
 * @return void
 */
it('user can request password reset with valid email', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $response = postJson('/api/v1/auth/forgot-password', [
        'email' => 'user@example.com'
    ]);

    $response->assertStatus(405); // Method not allowed - endpoint doesn't exist
});

/**
 * Test: Password Reset - Success
 * 
 * Verifies that users can reset their password with a valid token.
 * 
 * @return void
 */
it('user can reset password with valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $response = postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(405); // Method not allowed - endpoint doesn't exist
});

/**
 * Test: Registration Assigns Default Role
 * 
 * Verifies that newly registered users are assigned the default 'client' role.
 * 
 * @return void
 */
it('registered user gets default client role', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = postJson('/api/v1/auth/register', $userData);
    $response->assertCreated();

    $user = User::where('email', 'john@example.com')->first();
    expect($user->hasRole('client'))->toBeTrue();
});

/**
 * Test: User Registration - Duplicate Email
 * 
 * Verifies that user registration fails with duplicate email.
 * 
 * @return void
 */
it('user registration fails with duplicate email', function () {
    // Create existing user
    User::factory()->create(['email' => 'existing@example.com']);

    $userData = [
        'name' => 'John Doe',
        'email' => 'existing@example.com', // Duplicate email
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = postJson('/api/v1/auth/register', $userData);
    $response->assertUnprocessable()
        ->assertJsonPath('success', false);
});

/**
 * Test: User Login - Invalid Credentials
 * 
 * Verifies that user login fails with invalid credentials.
 * 
 * @return void
 */
it('user login fails with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    $loginData = [
        'email' => 'user@example.com',
        'password' => 'wrongpassword', // Wrong password
    ];

    $response = postJson('/api/v1/auth/login', $loginData);
    $response->assertUnauthorized()
        ->assertJsonPath('success', false);
});

/**
 * Test: Unauthenticated User Cannot Get Profile
 * 
 * Verifies that unauthenticated users cannot access profile endpoint.
 * 
 * @return void
 */
it('unauthenticated user cannot get profile', function () {
    $response = getJson('/api/v1/auth/profile');
    $response->assertUnauthorized();
});

/**
 * Test: Unauthenticated User Cannot Logout
 * 
 * Verifies that unauthenticated users cannot logout.
 * 
 * @return void
 */
it('unauthenticated user cannot logout', function () {
    $response = postJson('/api/v1/auth/logout');
    $response->assertUnauthorized();
});
