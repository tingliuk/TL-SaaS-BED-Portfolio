<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * API Routes defined by version in separate files
 *
 * Version  Route File Location
 * V1       routes/api_v1.php
 * V2       routes/api_v2.php
 */


/**
 * Fallback route for any routes that are not defined
 * Result 404
 */
Route::fallback(static function(){
   return Response::json([
       ['error'=>"OOPS!"]
   ],404);
});

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');
