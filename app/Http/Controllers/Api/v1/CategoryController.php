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

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
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
     * Store a newly created resource in storage.
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
     * Display the specified resource.
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
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
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
