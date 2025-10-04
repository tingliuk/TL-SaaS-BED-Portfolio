<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Joke;
use App\Models\Vote;
use App\Models\User;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * VoteController
 * 
 * Handles all vote-related API operations including voting on jokes,
 * removing votes, and administrative vote management. Implements
 * ownership-based permissions where users can manage their own votes,
 * while staff+ can manage all votes.
 * 
 * @package App\Http\Controllers\Api\v1
 * @author Ting Liu
 * @version 1.0.0
 * @since 2025-10-04
 * 
 * @method JsonResponse vote(Request $request, string $jokeId) Vote on a joke (like/dislike)
 * @method JsonResponse removeVote(string $jokeId) Remove vote from a joke
 * @method JsonResponse clearUserVotes(string $userId) Clear all votes for a specific user (admin)
 * @method JsonResponse clearAllVotes() Clear all votes in the system (admin)
 * 
 * @see \App\Responses\ApiResponse For standardized JSON responses
 */
class VoteController extends Controller
{
    /**
     * Vote on a joke (like or dislike).
     * 
     * Allows authenticated users to vote on jokes with a rating of +1 (like)
     * or -1 (dislike). Users can only vote once per joke, and updating a vote
     * will replace the previous vote. Implements proper validation and
     * ownership tracking.
     * 
     * @param Request $request The HTTP request containing:
     *                        - rating (required): Vote rating (+1 for like, -1 for dislike)
     * @param string $jokeId The joke ID to vote on
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the vote information
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When joke not found or user lacks vote permissions
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api POST /api/v1/jokes/{joke}/vote
     * @permission votes.create (User level 100+)
     */
    public function vote(Request $request, string $jokeId): JsonResponse
    {
        // Check if user can vote (User level 100+)
        if (!Gate::allows('create', Vote::class)) {
            return ApiResponse::error(null, 'Unauthorized to vote', 403);
        }

        $joke = Joke::find($jokeId);

        if (!$joke) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        // Check if user can vote on this specific joke
        if (!Gate::forUser(request()->user())->allows('voteOnJoke', $joke)) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        $request->validate([
            'rating' => ['required', 'integer', Rule::in([1, -1])],
        ]);

        $userId = request()->user()->id;

        // Check if user has already voted on this joke
        $existingVote = Vote::where('user_id', $userId)
            ->where('joke_id', $jokeId)
            ->first();

        if ($existingVote) {
            // Update existing vote
            $existingVote->update(['rating' => $request->rating]);
            $vote = $existingVote;
            $message = 'Vote updated successfully';
            $statusCode = 200;
        } else {
            // Create new vote
            $vote = Vote::create([
                'user_id' => $userId,
                'joke_id' => $jokeId,
                'rating' => $request->rating,
            ]);
            $message = 'Vote created successfully';
            $statusCode = 201;
        }

        $vote->load(['user', 'joke']);

        return ApiResponse::success(['vote' => $vote], $message, $statusCode);
    }

    /**
     * Remove vote from a joke.
     * 
     * Allows authenticated users to remove their vote from a specific joke.
     * Users can only remove their own votes, while staff+ can remove any vote.
     * Implements proper authorization and ownership validation.
     * 
     * @param string $jokeId The joke ID to remove vote from
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: null (vote is removed)
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When joke not found or user lacks delete permissions
     * 
     * @api DELETE /api/v1/jokes/{joke}/vote
     * @permission votes.delete (User level 100+ for own votes, Staff+ for any vote)
     */
    public function removeVote(string $jokeId): JsonResponse
    {
        // Check if user can delete votes (User level 100+)
        if (!Gate::allows('viewAny', Vote::class)) {
            return ApiResponse::error(null, 'Unauthorized to remove votes', 403);
        }

        $joke = Joke::find($jokeId);

        if (!$joke) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        $currentUser = request()->user();

        // Find the vote for this joke
        $vote = Vote::where('joke_id', $jokeId)->first();

        if (!$vote) {
            return ApiResponse::error(null, "No vote found for this joke", 404);
        }

        // Check if user can delete this specific vote
        if (!Gate::allows('delete', $vote)) {
            return ApiResponse::error(null, "No vote found for this joke", 404);
        }

        $vote->delete();

        return ApiResponse::success(null, 'Vote removed successfully');
    }

    /**
     * Clear all votes for a specific user (admin only).
     * 
     * Allows staff+ level users to clear all votes for a specific user.
     * This is useful for administrative purposes or when cleaning up
     * user data. Implements proper permission validation.
     * 
     * @param string $userId The user ID to clear votes for
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the count of cleared votes
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user not found or lacks clear permissions
     * 
     * @api DELETE /api/v1/users/{user}/votes
     * @permission votes.clear-user (Staff level 500+)
     */
    public function clearUserVotes(string $userId): JsonResponse
    {
        $currentUser = request()->user();
        $user = User::find($userId);

        if (!$user) {
            return ApiResponse::error(null, "User not found", 404);
        }

        // Check if user can clear votes for this user (Staff level 500+)
        if (!Gate::allows('clearUserVotes', $user)) {
            return ApiResponse::error(null, 'Unauthorized to clear user votes', 403);
        }

        $voteCount = Vote::where('user_id', $userId)->count();
        Vote::where('user_id', $userId)->delete();

        return ApiResponse::success(
            ['cleared_votes_count' => $voteCount], 
            "Cleared {$voteCount} votes for user"
        );
    }

    /**
     * Clear all votes in the system (admin only).
     * 
     * Allows admin+ level users to clear all votes in the system.
     * This is a powerful administrative function that should be used
     * with caution as it affects all users' voting data.
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the count of cleared votes
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user lacks clear all permissions
     * 
     * @api DELETE /api/v1/votes
     * @permission votes.clear-all (Admin level 750+)
     * @warning This action affects all users' voting data
     */
    public function clearAllVotes(): JsonResponse
    {
        $currentUser = request()->user();

        // Check if user can clear all votes (Admin level 750+)
        if (!Gate::allows('clearAllVotes', Vote::class)) {
            return ApiResponse::error(null, 'Unauthorized to clear all votes', 403);
        }

        $voteCount = Vote::count();
        Vote::truncate();

        return ApiResponse::success(
            ['cleared_votes_count' => $voteCount], 
            "Cleared {$voteCount} votes from the system"
        );
    }

}