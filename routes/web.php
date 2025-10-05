<?php

use Illuminate\Support\Facades\Route;

// API Documentation/Info page
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Jokes API v1.0',
        'data' => [
            'api_version' => 'v1',
            'base_url' => url('/api/v1'),
            'endpoints' => [
                'auth' => '/api/v1/auth/*',
                'categories' => '/api/v1/categories',
                'jokes' => '/api/v1/jokes',
                'votes' => '/api/v1/jokes/{id}/vote',
                'users' => '/api/v1/users',
                'profile' => '/api/v1/me'
            ],
            'documentation' => 'This is a REST API for managing jokes, categories, and user interactions.'
        ]
    ]);
});

require __DIR__ . '/auth.php';
