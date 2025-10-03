<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * API Routes for Jokes Database Assessment
 * Using single version (v1) for simplicity
 */

// Note: api_v1 routes are already loaded via bootstrap/app.php routing groups

/**
 * Fallback route for any routes that are not defined
 * Result 404
 */
Route::fallback(static function(){
   return response()->json([
       'success' => false,
       'message' => 'API endpoint not found',
       'data' => null
   ], 404);
});
