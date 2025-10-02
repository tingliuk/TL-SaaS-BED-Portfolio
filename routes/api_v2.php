<?php

use App\Http\Controllers\Api\v1\AuthController as AuthControllerV1;
use App\Http\Controllers\Api\v2\CategoryController as CategoryControllerV2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * API Version 2 Routes
 */

/**
 * User API Routes
 * - Register, Login (no authentication)
 * - Profile, Logout, User details (authentication required)
 */

Route::prefix('auth')
    ->group(function () {
        Route::post('register', [AuthControllerV1::class, 'register']);
        Route::post('login', [AuthControllerV1::class, 'login']);

        Route::get('profile', [AuthControllerV1::class, 'profile'])
            ->middleware(['auth:sanctum',]);
        Route::post('logout', [AuthControllerV1::class, 'logout'])
            ->middleware(['auth:sanctum',]);

    });

/* Categories Routes ------------------------------------------------------ */
Route::get('categories/trash', [CategoryControllerV2::class, 'trash'])
    ->name('categories.trash');

Route::delete('categories/trash/empty', [CategoryControllerV2::class, 'removeAll'])
    ->name('categories.trash.remove.all');

Route::post('categories/trash/recover', [CategoryControllerV2::class, 'recoverAll'])
    ->name('categories.trash.recover.all');

Route::delete('categories/trash/{id}/remove', [CategoryControllerV2::class, 'removeOne'])
    ->name('categories.trash.remove.one');

Route::post('categories/trash/{id}/recover', [CategoryControllerV2::class, 'recoverOne'])
    ->name('categories.trash.recover.one');

/** Stop people trying to "GET" admin/categories/trash/1234/delete or similar */
Route::get('categories/trash/{id}/{method}', [CategoryControllerV2::class, 'trash']);

Route::resource("categories", CategoryControllerV2::class);

Route::post('categories/{category}/delete', [CategoryControllerV2::class, 'delete'])
    ->name('categories.delete');

Route::get('categories/{category}/delete', function () {
    return redirect()->route('admin.categories.index');
});
