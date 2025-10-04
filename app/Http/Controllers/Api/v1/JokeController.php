<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJokeRequest;
use App\Http\Requests\UpdateJokeRequest;
use App\Models\Joke;
use App\Models\Category;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * JokeController
 * 
 * Handles all joke-related API operations including CRUD operations, search,
 * filtering, and random joke retrieval. Implements ownership-based permissions
 * where users can manage their own jokes, while staff+ can manage any joke.
 * 
 * @package App\Http\Controllers\Api\v1
 * @author TAFE Assessment
 * @version 1.0.0
 * @since 2025-10-04
 * 
 * @method JsonResponse index(Request $request) Display a listing of jokes with pagination and filtering
 * @method JsonResponse store(StoreJokeRequest $request) Store a newly created joke
 * @method JsonResponse show(string $id) Display the specified joke
 * @method JsonResponse update(UpdateJokeRequest $request, string $id) Update the specified joke
 * @method JsonResponse destroy(string $id) Remove the specified joke (soft delete)
 * @method JsonResponse random() Get a random joke (public endpoint for guests)
 * @method JsonResponse restore(string $id) Restore a soft deleted joke
 * @method JsonResponse forceDelete(string $id) Permanently delete a joke
 * 
 * @see \App\Policies\JokePolicy For authorization logic
 * @see \App\Http\Requests\StoreJokeRequest For creation validation
 * @see \App\Http\Requests\UpdateJokeRequest For update validation
 * @see \App\Responses\ApiResponse For standardized JSON responses
 */
class JokeController extends Controller
{
    /**
     * Display a listing of jokes with pagination and filtering.
     * 
     * Retrieves a paginated list of jokes with optional search and filtering capabilities.
     * Supports filtering by category, user (admin/staff only), and text search across
     * title and content fields. Returns 15 jokes per page by default.
     * 
     * @param Request $request The HTTP request containing optional query parameters:
     *                        - q (string): Search term for title/content
     *                        - category_id (int): Filter by category ID
     *                        - user_id (int): Filter by user ID (admin/staff only)
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing paginated jokes with user and category relationships
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user lacks view permissions
     * 
     * @api GET /api/v1/jokes
     * @permission jokes.browse (User level 100+)
     * @permission jokes.search (User level 100+) - for search functionality
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user can view jokes (User level 100+)
        if (!Gate::allows('viewAny', Joke::class)) {
            return ApiResponse::error(null, 'Unauthorized to view jokes', 403);
        }

        $query = Joke::with(['user', 'categories']);

        // For regular users, filter out jokes with unknown/empty categories or soft deleted categories
        if ($request->user()->hasRole('user')) {
            $query->whereHas('categories', function ($q) {
                $q->whereNull('deleted_at'); // Only jokes that have at least one non-deleted category
            });
        }

        // Check if user can search and apply search filter
        if ($search = $request->string('q')->toString()) {
            if (!Gate::allows('search', Joke::class)) {
                return ApiResponse::error(null, 'Unauthorized to search jokes', 403);
            }
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Filter by category if provided
        if ($categoryId = $request->integer('category_id')) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        // Filter by user if provided (for admin/staff)
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        $jokes = $query->orderBy('created_at', 'desc')->paginate(15);

        return ApiResponse::success(['jokes' => $jokes], 'Jokes retrieved');
    }

    /**
     * Store a newly created joke in storage.
     * 
     * Creates a new joke with the provided data and assigns it to the authenticated user.
     * Automatically handles category relationships if provided. The joke is created with
     * ownership tracking for permission-based access control.
     * 
     * @param StoreJokeRequest $request Validated request containing:
     *                                  - title (required): Joke title (max 255 chars)
     *                                  - content (required): Joke content (min 10 chars)
     *                                  - reference (optional): Source reference (max 255 chars)
     *                                  - published_at (optional): Publication date
     *                                  - categories (optional): Array of category IDs
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the created joke with relationships
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user lacks create permissions
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api POST /api/v1/jokes
     * @permission jokes.create (User level 100+)
     */
    public function store(StoreJokeRequest $request): JsonResponse
    {
        // Check if user can create jokes (User level 100+)
        if (!Gate::allows('create', Joke::class)) {
            return ApiResponse::error(null, 'Unauthorized to create jokes', 403);
        }

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $joke = Joke::create($data);

        // Sync categories if provided
        if ($request->has('categories')) {
            $joke->categories()->sync($request->categories);
        }

        $joke->load(['user', 'categories']);

        return ApiResponse::success(['joke' => $joke], 'Joke created', 201);
    }

    /**
     * Display the specified joke.
     * 
     * Retrieves a single joke by ID with all related data including user information,
     * categories, and votes. Implements ownership-based access control where users
     * can view any joke, but only manage their own jokes.
     * 
     * @param string $id The joke ID to retrieve
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the joke with user, categories, and votes
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When joke not found or user lacks view permissions
     * 
     * @api GET /api/v1/jokes/{id}
     * @permission jokes.read (User level 100+)
     */
    public function show(string $id): JsonResponse
    {
        $joke = Joke::with(['user', 'categories', 'votes'])->find($id);

        if (!$joke) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        // Check if user can view this joke (User level 100+)
        if (!Gate::allows('view', $joke)) {
            return ApiResponse::error(null, 'Unauthorized to view this joke', 403);
        }

        // For regular users, check if joke has valid categories (not soft deleted)
        if (request()->user()->hasRole('user')) {
            $hasValidCategories = $joke->categories()->whereNull('deleted_at')->exists();
            if (!$hasValidCategories) {
                return ApiResponse::error(null, "Joke not found", 404);
            }
        }

        return ApiResponse::success(['joke' => $joke], 'Joke retrieved');
    }

    /**
     * Update the specified joke in storage.
     * 
     * Updates an existing joke with the provided data. Implements ownership-based
     * permissions where users can only update their own jokes, while staff+ can
     * update any joke. Supports partial updates and category relationship management.
     * 
     * @param UpdateJokeRequest $request Validated request containing:
     *                                   - title (optional): Joke title (max 255 chars)
     *                                   - content (optional): Joke content (min 10 chars)
     *                                   - reference (optional): Source reference (max 255 chars)
     *                                   - published_at (optional): Publication date
     *                                   - categories (optional): Array of category IDs
     * @param string $id The joke ID to update
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the updated joke with relationships
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When joke not found or user lacks update permissions
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api PUT /api/v1/jokes/{id}
     * @permission jokes.update (User level 100+ for own jokes, Staff+ for any joke)
     */
    public function update(UpdateJokeRequest $request, string $id): JsonResponse
    {
        $joke = Joke::find($id);

        if (!$joke) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        // Check if user can update this joke (ownership or Staff+)
        if (!Gate::allows('update', $joke)) {
            return ApiResponse::error(null, 'Unauthorized to update this joke', 403);
        }

        $joke->update($request->validated());

        // Sync categories if provided
        if ($request->has('categories')) {
            $joke->categories()->sync($request->categories);
        }

        $joke->load(['user', 'categories']);

        return ApiResponse::success(['joke' => $joke], 'Joke updated');
    }

    /**
     * Remove the specified joke from storage (soft delete).
     * 
     * Performs a soft delete on the specified joke, making it unavailable to users
     * but preserving the data for potential restoration. Implements ownership-based
     * permissions where users can only delete their own jokes, while staff+ can
     * delete any joke.
     * 
     * @param string $id The joke ID to delete
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: null (joke is soft deleted)
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When joke not found or user lacks delete permissions
     * 
     * @api DELETE /api/v1/jokes/{id}
     * @permission jokes.delete (User level 100+ for own jokes, Staff+ for any joke)
     */
    public function destroy(string $id): JsonResponse
    {
        $joke = Joke::find($id);

        if (!$joke) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        // Check if user can delete this joke (ownership or Staff+)
        if (!Gate::allows('delete', $joke)) {
            return ApiResponse::error(null, 'Unauthorized to delete this joke', 403);
        }

        $joke->delete();

        return ApiResponse::success(null, 'Joke deleted');
    }

    /**
     * Get a random joke (public endpoint for guests).
     * 
     * Retrieves a single random joke that has categories assigned. This endpoint
     * is publicly accessible without authentication and is specifically designed
     * for guest users. Excludes jokes without categories (unknown category jokes)
     * to ensure quality content is presented to guests.
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the random joke with user and category relationships
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When no jokes with categories are available
     * 
     * @api GET /api/v1/jokes/random
     * @permission None (public endpoint)
     * @note Only returns jokes that have categories assigned
     */
    public function random(): JsonResponse
    {
        $joke = Joke::with(['user', 'categories'])
            ->whereHas('categories') // Only jokes with categories (no unknown category jokes)
            ->inRandomOrder()
            ->first();

        if (!$joke) {
            return ApiResponse::error(null, "No jokes available", 404);
        }

        return ApiResponse::success(['joke' => $joke], 'Random joke retrieved');
    }

    /**
     * Restore a soft deleted joke.
     * 
     * Restores a previously soft deleted joke, making it available again to users.
     * This operation is restricted to staff+ level users and can only be performed
     * on jokes that are currently soft deleted.
     * 
     * @param string $id The joke ID to restore
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the restored joke with relationships
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When joke not found, not deleted, or user lacks restore permissions
     * 
     * @api POST /api/v1/jokes/{id}/restore
     * @permission jokes.restore (Staff level 500+)
     */
    public function restore(string $id): JsonResponse
    {
        $joke = Joke::withTrashed()->find($id);

        if (!$joke) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        // Check if user can restore this joke (Staff level 500+)
        if (!Gate::allows('restore', $joke)) {
            return ApiResponse::error(null, 'Unauthorized to restore this joke', 403);
        }

        if (!$joke->trashed()) {
            return ApiResponse::error(null, "Joke is not deleted", 400);
        }

        $joke->restore();
        $joke->load(['user', 'categories']);

        return ApiResponse::success(['joke' => $joke], 'Joke restored');
    }

    /**
     * Permanently delete a joke.
     * 
     * Permanently removes a joke from the database, including soft deleted jokes.
     * This operation is irreversible and is restricted to admin+ level users.
     * Use with extreme caution as this action cannot be undone.
     * 
     * @param string $id The joke ID to permanently delete
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: null (joke is permanently removed)
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When joke not found or user lacks force delete permissions
     * 
     * @api DELETE /api/v1/jokes/{id}/force
     * @permission jokes.force-delete (Admin level 750+)
     * @warning This action is irreversible
     */
    public function forceDelete(string $id): JsonResponse
    {
        $joke = Joke::withTrashed()->find($id);

        if (!$joke) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        // Check if user can permanently delete this joke (Admin level 750+)
        if (!Gate::allows('forceDelete', $joke)) {
            return ApiResponse::error(null, 'Unauthorized to permanently delete this joke', 403);
        }

        $joke->forceDelete();

        return ApiResponse::success(null, 'Joke permanently removed');
    }
}
