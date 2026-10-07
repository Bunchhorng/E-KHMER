<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreIsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        // Authentication must remain available so a platform administrator can
        // sign in and disable maintenance mode. Admin routes perform their own
        // Sanctum and role checks.
        if ($request->is('api/auth/*') || $request->is('api/admin/*')) {
            return $next($request);
        }

        if (! Setting::boolean('maintenanceMode')) {
            return $next($request);
        }

        $user = auth('sanctum')->user();
        if ($user?->isAdmin()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'The store is temporarily unavailable for maintenance. Please try again soon.',
        ], 503, ['Retry-After' => '300']);
    }
}
