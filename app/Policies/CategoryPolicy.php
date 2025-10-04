<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     * User level (100) and above can browse categories
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['user', 'staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can view the model.
     * User level (100) and above can view categories
     */
    public function view(User $user, Category $category): bool
    {
        return $user->hasRole(['user', 'staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can create models.
     * Staff level (500) and above can create categories
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can update the model.
     * Staff level (500) and above can update categories
     */
    public function update(User $user, Category $category): bool
    {
        return $user->hasRole(['staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can delete the model.
     * Staff level (500) and above can soft delete categories
     * Staff can delete ALL categories OR their own categories
     */
    public function delete(User $user, Category $category): bool
    {
        // Admin and Superuser can delete any category
        if ($user->hasRole(['admin', 'superuser'])) {
            return true;
        }

        // Staff can delete any category OR their own categories
        if ($user->hasRole('staff')) {
            return true; // Staff can delete all categories
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     * Staff level (500) and above can restore soft deleted categories
     */
    public function restore(User $user, Category $category): bool
    {
        return $user->hasRole(['staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Admin level (750) and above can permanently delete categories
     */
    public function forceDelete(User $user, Category $category): bool
    {
        return $user->hasRole(['admin', 'superuser']);
    }

    /**
     * Determine whether the user can search categories.
     * User level (100) and above can search categories
     */
    public function search(User $user): bool
    {
        return $user->hasRole(['user', 'staff', 'admin', 'superuser']);
    }
}
