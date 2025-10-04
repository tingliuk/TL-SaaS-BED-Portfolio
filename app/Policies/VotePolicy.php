<?php

namespace App\Policies;

use App\Models\Vote;
use App\Models\User;

class VotePolicy
{
    /**
     * Determine whether the user can view any models.
     * User level (100) and above can view votes
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['user', 'staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can view the model.
     * User level (100) and above can view votes
     */
    public function view(User $user, Vote $vote): bool
    {
        return $user->hasRole(['user', 'staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can create models.
     * User level (100) and above can create votes
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['user', 'staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can update the model.
     * Users can update their own votes, Staff+ can update any vote
     */
    public function update(User $user, Vote $vote): bool
    {
        // Admin and Superuser can update any vote
        if ($user->hasRole(['admin', 'superuser'])) {
            return true;
        }

        // Staff can update any vote
        if ($user->hasRole('staff')) {
            return true;
        }

        // Users can only update their own votes
        if ($user->hasRole('user')) {
            return $vote->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Users can delete their own votes, Staff+ can delete any vote
     */
    public function delete(User $user, Vote $vote): bool
    {
        // Admin and Superuser can delete any vote
        if ($user->hasRole(['admin', 'superuser'])) {
            return true;
        }

        // Staff can delete any vote
        if ($user->hasRole('staff')) {
            return true;
        }

        // Users can only delete their own votes
        if ($user->hasRole('user')) {
            return $vote->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     * Staff level (500) and above can restore votes
     */
    public function restore(User $user, Vote $vote): bool
    {
        return $user->hasRole(['staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Admin level (750) and above can permanently delete votes
     */
    public function forceDelete(User $user, Vote $vote): bool
    {
        return $user->hasRole(['admin', 'superuser']);
    }

    /**
     * Determine whether the user can clear votes for a specific user.
     * Staff level (500) and above can clear user votes
     */
    public function clearUserVotes(User $user, $targetUser): bool
    {
        return $user->hasRole(['staff', 'admin', 'superuser']);
    }

    /**
     * Determine whether the user can clear all votes in the system.
     * Admin level (750) and above can clear all votes
     */
    public function clearAllVotes(User $user): bool
    {
        return $user->hasRole(['admin', 'superuser']);
    }

    /**
     * Determine whether the user can vote on a specific joke.
     * User level (100) and above can vote on jokes with valid categories
     */
    public function voteOnJoke(User $user, $joke): bool
    {
        // All authenticated users can vote
        if (!$user->hasRole(['user', 'staff', 'admin', 'superuser'])) {
            return false;
        }

        // For regular users, check if joke has valid categories
        if ($user->hasRole('user')) {
            // Load categories if not already loaded
            if (!$joke->relationLoaded('categories')) {
                $joke->load('categories');
            }
            // Check if joke has at least one non-deleted category
            return $joke->categories->where('deleted_at', null)->isNotEmpty();
        }

        // Staff+ can vote on any joke
        return true;
    }

    /**
     * Determine whether the user can remove a vote from a specific joke.
     * Users can remove their own votes, Staff+ can remove any vote
     */
    public function removeVoteFromJoke(User $user, $joke): bool
    {
        // Check if user has a vote on this joke
        $vote = Vote::where('user_id', $user->id)
            ->where('joke_id', $joke->id)
            ->first();

        if (!$vote) {
            return false;
        }

        // Use the delete permission logic
        return $this->delete($user, $vote);
    }
}
