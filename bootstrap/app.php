<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (){
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));

            \Illuminate\Support\Facades\Route::middleware('api')
                ->prefix('api/v2')
                ->group(base_path('routes/api_v2.php'));

            /* Add further API versions as required */

        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function(ModelNotFoundException $error, Request $request){
            if($request->wantsJson()){
                return response()->json([
                    'error'=>'entry for '.str_replace('App','',$error->getModel()).' not found'
                    ],
                    404
                );
            }
        });
    })->create();
