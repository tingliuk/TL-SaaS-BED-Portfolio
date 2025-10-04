<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Helper function to create user with role
 */
function createUserWithRole(string $roleName): User
{
    $role = Role::findOrCreate($roleName);
    $user = User::factory()->create();
    $user->assignRole($role);
    return $user;
}

