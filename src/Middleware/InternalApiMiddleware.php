<?php

namespace Src\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InternalApiMiddleware
{
    /**
     * Handle an incoming request.
     * Validates requests from internal services (Accounting) using a shared API key.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get the expected API key from environment
        $expectedKey = env('INTERNAL_API_KEY');
        
        // If no key is configured, allow requests (for development)
        if (empty($expectedKey)) {
            Log::warning('InternalApiMiddleware: No INTERNAL_API_KEY configured, allowing request');
            return $next($request);
        }

        // Get the provided API key from header
        $providedKey = $request->header('X-Internal-Api-Key');

        // Validate the API key
        if ($providedKey !== $expectedKey) {
            Log::warning('InternalApiMiddleware: Invalid API key provided', [
                'ip' => $request->ip(),
                'url' => $request->url(),
            ]);
            return response()->json([
                'code' => 401,
                'message' => 'Unauthorized - Invalid API key'
            ], 401);
        }

        // Log successful internal API call
        Log::debug('InternalApiMiddleware: Valid internal API request', [
            'ip' => $request->ip(),
            'url' => $request->url(),
        ]);

        return $next($request);
    }
}
