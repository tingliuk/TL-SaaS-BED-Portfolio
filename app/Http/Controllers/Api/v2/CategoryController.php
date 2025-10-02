<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the Categories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $categories = Category::all();
        return ApiResponse::success($categories, "Categories retrieved");
    }

    /**
     * Store a newly created Category in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['string', 'required', 'min:4'],
            'description' => ['string', 'nullable', 'min:6'],
        ]);

        $category = Category::create($validated);

        return ApiResponse::success($category, 'Category created', 201);
    }

    /**
     * Display the specified Category.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $category = Category::whereId($id)->get();

        if (count($category) === 0) {
            return ApiResponse::error($category, "Category not found", 404);
        }
        return ApiResponse::success($category, "Category retrieved");
    }

    /**
     * Update the specified Category in storage.
     *
     * @param Request $request
     * @param string $id
     * @return void
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified Category from storage.
     *
     * @param string $id
     * @return void
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Show all soft deleted Categories
     *
     * @param Request $request
     * @return void
     */
    public function trash(Request $request)
    {
    }

    /**
     * Recover all soft deleted categories from trash
     *
     * @return void
     */
    public function recoverAll()
    {
    }

    /**
     * Remove all soft deleted categories from trash
     *
     * @return void
     */
    public function removeAll()
    {
    }

    /**
     * Recover specified soft deleted category from trash
     *
     * @param string $id
     * @return void
     */
    public function recoverOne(string $id)
    {
    }

    /**
     * Remove specified soft deleted category from trash
     *
     * @param string $id
     * @return void
     */
    public function removeOne(string $id)
    {
    }
}
