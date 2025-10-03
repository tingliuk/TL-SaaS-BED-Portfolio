<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\CategoryController;
use App\Http\Controllers\Api\v1\JokeController;
use App\Http\Controllers\Api\v1\VoteController;
use App\Http\Controllers\Api\v1\UserController;
use Illuminate\Support\Facades\Route;

/**
 * API Version 1 Routes for Jokes Database Assessment
 * Note: bootstrap/app.php already prefixes these with /api/v1
 */

// Public routes (no authentication required)
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('auth/password/reset', [AuthController::class, 'resetPassword']);

// Guest routes (limited access)
Route::get('jokes/random', [JokeController::class, 'random']);

// Protected routes (authentication required)
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth routes
    Route::get('auth/profile', [AuthController::class, 'profile']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/logout/role/{role}', [AuthController::class, 'logoutByRole']);

    // User profile routes
    Route::get('me', [UserController::class, 'profile']);
    Route::put('me', [UserController::class, 'updateProfile']);
    Route::put('me/password', [UserController::class, 'updatePassword']);
    Route::delete('me', [UserController::class, 'deleteProfile']);

    // Categories routes
    Route::apiResource('categories', CategoryController::class);
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore']);
    Route::delete('categories/{category}/force', [CategoryController::class, 'forceDelete']);

    // Jokes routes
    Route::apiResource('jokes', JokeController::class);

    // Votes routes
    Route::post('jokes/{joke}/vote', [VoteController::class, 'vote']);
    Route::delete('jokes/{joke}/vote', [VoteController::class, 'removeVote']);

    // Admin routes (require specific permissions)
    Route::middleware(['permission:users.browse'])->group(function () {
        Route::apiResource('users', UserController::class);
        Route::put('users/{user}/roles', [UserController::class, 'assignRoles']);
        Route::put('users/{user}/status', [UserController::class, 'changeStatus']);
    });

    // Admin vote management
    Route::middleware(['permission:votes.clear-user'])->group(function () {
        Route::delete('users/{user}/votes', [VoteController::class, 'clearUserVotes']);
    });

    Route::middleware(['permission:votes.clear-all'])->group(function () {
        Route::delete('votes', [VoteController::class, 'clearAllVotes']);
    });
});
