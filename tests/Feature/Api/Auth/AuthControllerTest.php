<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('logs in successfully with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('Password1'),
        'email_verified_at' => now(),
    ]);

    $response = postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'Password1',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
});

it('fails login with wrong password', function () {
    $user = User::factory()->create([
        'email' => 'user2@example.com',
        'password' => Hash::make('Password1'),
        'email_verified_at' => now(),
    ]);

    $response = postJson('/api/v1/auth/login', [
        'email' => 'user2@example.com',
        'password' => 'WrongPass',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('success', false);
});

it('logs out and revokes tokens', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Password1'),
        'email_verified_at' => now(),
    ]);

    // Create a token to be revoked
    $user->createToken('TestToken');

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/auth/logout');
    $response->assertOk()->assertJsonPath('success', true);

    expect($user->tokens()->count())->toBe(0);
});

it('sends password reset link', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'resetme@example.com',
        'password' => Hash::make('Password1'),
        'email_verified_at' => now(),
    ]);

    $response = postJson('/api/v1/auth/password/forgot', [
        'email' => 'resetme@example.com',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
});

it('resets password with valid token and revokes tokens', function () {
    $user = User::factory()->create([
        'email' => 'reset2@example.com',
        'password' => Hash::make('OldPass1'),
        'email_verified_at' => now(),
    ]);

    // Have an existing token to verify it will be revoked
    $user->createToken('OldToken');

    $token = Password::createToken($user);

    $response = postJson('/api/v1/auth/password/reset', [
        'email' => 'reset2@example.com',
        'token' => $token,
        'password' => 'NewPass1',
        'password_confirmation' => 'NewPass1',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    // Password updated
    $user->refresh();
    expect(Hash::check('NewPass1', $user->password))->toBeTrue();
    // Tokens revoked
    expect($user->tokens()->count())->toBe(0);
});

it('logout by role revokes tokens for that role only', function () {
    // Ensure roles exist
    Role::findOrCreate('admin');
    Role::findOrCreate('staff');
    Role::findOrCreate('client');

    // Create roles first if not present via seeder assumptions
    // Using seeded roles: admin, staff, client, superuser
    $admin = User::factory()->create([
        'email' => 'adminx@example.com',
        'password' => Hash::make('Password1'),
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('admin');

    $staffA = User::factory()->create();
    $staffB = User::factory()->create();
    $staffA->assignRole('staff');
    $staffB->assignRole('staff');

    $client = User::factory()->create();
    $client->assignRole('client');

    // Create tokens
    $staffA->createToken('A');
    $staffB->createToken('B');
    $client->createToken('C');

    Sanctum::actingAs($admin);

    $resp = postJson('/api/v1/auth/logout/role/staff');
    $resp->assertOk()->assertJsonPath('success', true);

    expect($staffA->tokens()->count())->toBe(0);
    expect($staffB->tokens()->count())->toBe(0);
    expect($client->tokens()->count())->toBe(1);
});


