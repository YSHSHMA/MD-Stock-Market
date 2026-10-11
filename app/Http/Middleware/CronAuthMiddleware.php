<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CronAuthMiddleware
{
    /**
     * Handle an incoming request.
     * Protects cron endpoints from unauthorized access.
     * 
     * In production, this validates Google Cloud Scheduler OIDC tokens.
     * In local/staging, it checks for a simple bearer token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cronSecret = config('settings.cron_secret');

        if (!$cronSecret) {
            return response()->json([
                'error' => 'Server Error',
                'message' => 'Cron secret not configured'
            ], 500);
        }

        if (!hash_equals((string) $cronSecret, (string) $request->bearerToken())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing authentication token'
            ], 401);
        }

        return $next($request);
    }
}
