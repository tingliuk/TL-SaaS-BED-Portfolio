<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

class DeployHookController extends Controller
{
    public function __invoke(Request $request)
    {
        $token = $request->header('X-Deploy-Token');
        $expected = config('app.deploy_hook_token');

        if (!$expected || !hash_equals((string) $expected, (string) $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Ensure required directories exist (shared hosts sometimes miss these)
        @mkdir(storage_path('framework/cache/data'), 0775, true);
        @mkdir(storage_path('framework/sessions'), 0775, true);
        @mkdir(storage_path('framework/views'), 0775, true);
        @mkdir(base_path('bootstrap/cache'), 0775, true);

        $results = [];

        // Clear caches before building new ones
        $results['config_clear'] = Artisan::call('config:clear');
        $results['route_clear'] = Artisan::call('route:clear');
        $results['view_clear'] = Artisan::call('view:clear');
        $results['cache_clear'] = Artisan::call('cache:clear');

        // Run migrations and seeds if present
        $results['migrate'] = Artisan::call('migrate', ['--force' => true]);

        // Rebuild caches
        $results['config_cache'] = Artisan::call('config:cache');
        $results['route_cache'] = Artisan::call('route:cache');
        $results['view_cache'] = Artisan::call('view:cache');

        return response()->json([
            'success' => true,
            'message' => 'Deployment tasks executed',
            'results' => $results,
        ]);
    }
}


