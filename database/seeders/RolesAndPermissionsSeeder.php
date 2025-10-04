<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Categories
            'categories.browse',
            'categories.read',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.search',
            'categories.restore',
            'categories.force-delete',

            // Jokes
            'jokes.browse',
            'jokes.read',
            'jokes.create',
            'jokes.update',
            'jokes.delete',
            'jokes.search',

            // Votes
            'votes.create',
            'votes.update',
            'votes.delete',
            'votes.browse',
            'votes.clear-user',
            'votes.clear-all',

            // Users
            'users.browse',
            'users.read',
            'users.create',
            'users.update',
            'users.delete',
            'users.search',
            'users.assign-roles',
            'users.change-status',

            // Auth
            'auth.logout-role',
            'auth.reset-password',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $userRole = Role::create(['name' => 'user']);
        $userRole->givePermissionTo([
            'categories.browse',
            'categories.read',
            'categories.search',
            'jokes.browse',
            'jokes.read',
            'jokes.create',
            'jokes.update',
            'jokes.delete',
            'votes.create',
            'votes.update',
            'votes.delete',
        ]);

        $clientRole = Role::create(['name' => 'client']);
        $clientRole->givePermissionTo([
            'categories.browse',
            'categories.read',
            'categories.search',
            'jokes.browse',
            'jokes.read',
            'jokes.create',
            'jokes.update',
            'jokes.delete',
            'votes.create',
            'votes.update',
            'votes.delete',
        ]);

        $staffRole = Role::create(['name' => 'staff']);
        $staffRole->givePermissionTo([
            'categories.browse',
            'categories.read',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.search',
            'categories.restore',
            'jokes.browse',
            'jokes.read',
            'jokes.create',
            'jokes.update',
            'jokes.delete',
            'jokes.search',
            'votes.create',
            'votes.update',
            'votes.delete',
            'votes.browse',
            'votes.clear-user',
            'users.browse',
            'users.read',
            'users.create',
            'users.update',
            'users.delete',
            'users.search',
            'users.change-status',
            'auth.logout-role',
            'auth.reset-password',
        ]);

        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'categories.browse',
            'categories.read',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.search',
            'categories.restore',
            'categories.force-delete',
            'jokes.browse',
            'jokes.read',
            'jokes.create',
            'jokes.update',
            'jokes.delete',
            'jokes.search',
            'votes.create',
            'votes.update',
            'votes.delete',
            'votes.browse',
            'votes.clear-user',
            'users.browse',
            'users.read',
            'users.create',
            'users.update',
            'users.delete',
            'users.search',
            'users.assign-roles',
            'users.change-status',
            'auth.logout-role',
            'auth.reset-password',
        ]);

        $superuserRole = Role::create(['name' => 'superuser']);
        $superuserRole->givePermissionTo(Permission::all());
    }
}
