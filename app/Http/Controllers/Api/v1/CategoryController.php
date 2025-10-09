<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * CategoryController
 * 
 * Handles all category-related API operations including CRUD operations, search,
 * and soft delete management. Implements role-based permissions where categories
 * are global resources accessible to all authenticated users, but only staff+
 * can create, update, and delete categories.
 * 
 * @package App\Http\Controllers\Api\v1
 * @author Ting Liu
 * @version 1.0.0
 * @since 2025-10-04
 * 
 * @method JsonResponse index(Request $request) Display a listing of categories with search
 * @method JsonResponse store(StoreCategoryRequest $request) Store a newly created category
 * @method JsonResponse show(string $id) Display the specified category with 5 random jokes
 * @method JsonResponse update(UpdateCategoryRequest $request, string $id) Update the specified category
 * @method JsonResponse destroy(string $id) Remove the specified category (soft delete)
 * @method JsonResponse restore(string $id) Restore a soft deleted category
 * @method JsonResponse forceDelete(string $id) Permanently delete a category
 * 
 * @see \App\Policies\CategoryPolicy For authorization logic
 * @see \App\Http\Requests\StoreCategoryRequest For creation validation
 * @see \App\Http\Requests\UpdateCategoryRequest For update validation
 * @see \App\Responses\ApiResponse For standardized JSON responses
 */
class CategoryController extends Controller
{
    /**
     * Display a listing of categories with optional search functionality.
     * 
     * Retrieves all available categories with optional search filtering by name.
     * Categories are global resources accessible to all authenticated users.
     * Returns different response codes based on whether it's a search query or
     * general browse operation.
     * 
     * @param Request $request The HTTP request containing optional query parameters:
     *                        - q (string): Search term for category name
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing categories array
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user lacks view permissions
     * 
     * @api GET /api/v1/categories
     * @permission categories.browse (User level 100+)
     * @permission categories.search (User level 100+) - for search functionality
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user can view categories (User level 100+)
        if (!Gate::allows('viewAny', Category::class)) {
            return ApiResponse::error(null, 'Unauthorized to view categories', 403);
        }

        $query = Category::query();

        // Check if user can search and apply search filter
        if ($search = $request->string('q')->toString()) {
            if (!Gate::allows('search', Category::class)) {
                return ApiResponse::error(null, 'Unauthorized to search categories', 403);
            }
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('name')->get();

        // For search queries, return empty results with 200 status
        if ($search && count($categories) === 0) {
            return ApiResponse::success(['categories' => $categories], 'No categories found matching search criteria');
        }

        // For general browse, return 404 if no categories exist at all
        if (!$search && count($categories) === 0) {
            return ApiResponse::error($categories, "No categories found", 404);
        }

        return ApiResponse::success(['categories' => $categories], 'Categories retrieved');
    }

    /**
     * Store a newly created category in storage.
     * 
     * Creates a new category with the provided data and assigns it to the authenticated user.
     * Categories are global resources but ownership is tracked for administrative purposes.
     * Only staff+ level users can create categories.
     * 
     * @param StoreCategoryRequest $request Validated request containing:
     *                                      - name (required): Category name (max 255 chars, unique)
     *                                      - description (optional): Category description (max 1000 chars)
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the created category
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user lacks create permissions
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api POST /api/v1/categories
     * @permission categories.create (Staff level 500+)
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        // Check if user can create categories (Staff level 500+)
        if (!Gate::allows('create', Category::class)) {
            return ApiResponse::error(null, 'Unauthorized to create categories', 403);
        }

        // Add the authenticated user as the owner
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $category = Category::create($data);

        return ApiResponse::success(['category' => $category], 'Category created', 201);
    }

    /**
     * Display the specified category with 5 random jokes.
     * 
     * Retrieves a single category by ID along with 5 random jokes from that category.
     * Categories are global resources accessible to all authenticated users.
     * The random jokes are included to provide sample content for the category.
     * 
     * @param string $id The category ID to retrieve
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the category with 5 random jokes
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When category not found or user lacks view permissions
     * 
     * @api GET /api/v1/categories/{id}
     * @permission categories.read (User level 100+)
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::query()->find($id);

        if (!$category) {
            return ApiResponse::error(null, "Category not found", 404);
        }

        // Check if user can view this category (User level 100+)
        if (!Gate::allows('view', $category)) {
            return ApiResponse::error(null, 'Unauthorized to view this category', 403);
        }

        // Load 5 random jokes for this category
        $category->load(['jokes' => function ($q) {
            $q->inRandomOrder()->limit(5);
        }]);

        return ApiResponse::success(['category' => $category], 'Category retrieved');
    }

    /**
     * Update the specified category in storage.
     * 
     * Updates an existing category with the provided data. Only staff+ level users
     * can update categories. Supports partial updates and maintains data integrity
     * with unique name validation.
     * 
     * @param UpdateCategoryRequest $request Validated request containing:
     *                                       - name (optional): Category name (max 255 chars, unique)
     *                                       - description (optional): Category description (max 1000 chars)
     * @param string $id The category ID to update
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the updated category
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When category not found or user lacks update permissions
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api PUT /api/v1/categories/{id}
     * @permission categories.update (Staff level 500+)
     */
    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return ApiResponse::error(null, "Category not found", 404);
        }

        // Check if user can update this category (Staff level 500+)
        if (!Gate::allows('update', $category)) {
            return ApiResponse::error(null, 'Unauthorized to update this category', 403);
        }

        $category->update($request->validated());

        return ApiResponse::success(['category' => $category], 'Category updated');
    }

    /**
     * Remove the specified category from storage (soft delete).
     * 
     * Performs a soft delete on the specified category, making it unavailable to users
     * but preserving the data for potential restoration. Staff+ can delete any category,
     * implementing a hybrid permission system where staff have global deletion rights.
     * 
     * @param string $id The category ID to delete
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: null (category is soft deleted)
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When category not found or user lacks delete permissions
     * 
     * @api DELETE /api/v1/categories/{id}
     * @permission categories.delete (Staff level 500+)
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return ApiResponse::error(null, "Category not found", 404);
        }

        // Check if user can delete this category (Staff level 500+)
        if (!Gate::allows('delete', $category)) {
            return ApiResponse::error(null, 'Unauthorized to delete this category', 403);
        }

        $category->delete();

        return ApiResponse::success(null, 'Category deleted');
    }

    /**
     * Restore a soft deleted category.
     * 
     * Restores a previously soft deleted category, making it available again to users.
     * This operation is restricted to staff+ level users and can only be performed
     * on categories that are currently soft deleted.
     * 
     * @param string $id The category ID to restore
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the restored category
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When category not found, not deleted, or user lacks restore permissions
     * 
     * @api POST /api/v1/categories/{id}/restore
     * @permission categories.restore (Staff level 500+)
     */
    public function restore(string $id): JsonResponse
    {
        $category = Category::withTrashed()->find($id);

        if (!$category) {
            return ApiResponse::error(null, "Category not found", 404);
        }

        // Check if user can restore this category (Staff level 500+)
        if (!Gate::allows('restore', $category)) {
            return ApiResponse::error(null, 'Unauthorized to restore this category', 403);
        }

        if (!$category->trashed()) {
            return ApiResponse::error(null, "Category is not deleted", 400);
        }

        $category->restore();

        return ApiResponse::success(['category' => $category], 'Category restored');
    }

    /**
     * Permanently delete a category.
     * 
     * Permanently removes a category from the database, including soft deleted categories.
     * This operation is irreversible and is restricted to admin+ level users.
     * Use with extreme caution as this action cannot be undone and may affect
     * jokes that reference this category.
     * 
     * @param string $id The category ID to permanently delete
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: null (category is permanently removed)
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When category not found or user lacks force delete permissions
     * 
     * @api DELETE /api/v1/categories/{id}/force
     * @permission categories.force-delete (Admin level 750+)
     * @warning This action is irreversible and may affect related jokes
     */
    public function forceDelete(string $id): JsonResponse
    {
        $category = Category::withTrashed()->find($id);

        if (!$category) {
            return ApiResponse::error(null, "Category not found", 404);
        }

        // Check if user can permanently delete this category (Admin level 750+)
        if (!Gate::allows('forceDelete', $category)) {
            return ApiResponse::error(null, 'Unauthorized to permanently delete this category', 403);
        }

        $category->forceDelete();

        return ApiResponse::success(null, 'Category permanently removed');
    }
}
