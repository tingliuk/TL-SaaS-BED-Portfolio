<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StaticPageController::class, 'home'])
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])
        ->name('dashboard');
});

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminController::class, 'index'])
            ->name('index');

        /* Users Admin Routes ----------------------------------------------------------- */
        Route::get('users', [AdminController::class, 'users'])
            ->name('users.index');

        /* Categories Admin Routes ------------------------------------------------------ */
        Route::get('categories/trash', [AdminCategoryController::class, 'trash'])
            ->name('categories.trash');

        Route::delete('categories/trash/empty', [AdminCategoryController::class, 'removeAll'])
            ->name('categories.trash.remove.all');

        Route::post('categories/trash/recover', [AdminCategoryController::class, 'recoverAll'])
            ->name('categories.trash.recover.all');

        Route::delete('categories/trash/{id}/remove', [AdminCategoryController::class, 'removeOne'])
            ->name('categories.trash.remove.one');

        Route::post('categories/trash/{id}/recover', [AdminCategoryController::class, 'recoverOne'])
            ->name('categories.trash.recover.one');

        /** Stop people trying to "GET" admin/categories/trash/1234/delete or similar */
        Route::get('categories/trash/{id}/{method}', [AdminCategoryController::class, 'trash']);

        Route::resource("categories", AdminCategoryController::class);

        Route::post('categories/{category}/delete', [AdminCategoryController::class, 'delete'])
            ->name('categories.delete');

        Route::get('categories/{category}/delete', function () {
            return redirect()->route('admin.categories.index');
        });
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__ . '/auth.php';
