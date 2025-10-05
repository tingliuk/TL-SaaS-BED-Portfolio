<?php

namespace App\Policies;

use App\Models\Joke;
use App\Models\User;

class JokePolicy
{
    /**
     * Determine whether the user can view any models.
     * User level (100) and above can browse jokes
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['client', 'staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can view the model.
     * User level (100) and above can view jokes
     */
    public function view(User $user, Joke $joke): bool
    {
        return $user->hasRole(['client', 'staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can create models.
     * User level (100) and above can create jokes
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['client', 'staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can update the model.
     * Users can update their own jokes, Staff+ can update any joke
     */
    public function update(User $user, Joke $joke): bool
    {
        // Admin and Superuser can update any joke
        if ($user->hasRole(['admin', 'superuser'])) {
            return true;
        }

        // Staff can update any joke
        if ($user->hasRole('staff')) {
            return true;
        }

        // Clients can only update their own jokes
        if ($user->hasRole('client')) {
            return $joke->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Users can delete their own jokes, Staff+ can delete any joke
     */
    public function delete(User $user, Joke $joke): bool
    {
        // Admin and Superuser can delete any joke
        if ($user->hasRole(['admin', 'superuser'])) {
            return true;
        }

        // Staff can delete any joke
        if ($user->hasRole('staff')) {
            return true;
        }

        // Clients can only delete their own jokes
        if ($user->hasRole('client')) {
            return $joke->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     * Staff level (500) and above can restore jokes
     */
    public function restore(User $user, Joke $joke): bool
    {
        return $user->hasRole(['staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Admin level (750) and above can permanently delete jokes
     */
    public function forceDelete(User $user, Joke $joke): bool
    {
        return $user->hasRole(['admin', 'superuser']);
    }

    /**
     * Determine whether the user can search jokes.
     * User level (100) and above can search jokes
     */
    public function search(User $user): bool
    {
        return $user->hasRole(['client', 'staff', 'admin', 'superuser']);
    }
}
