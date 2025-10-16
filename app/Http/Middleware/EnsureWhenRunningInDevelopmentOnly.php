<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Will deny access to development features when not running in a development environment.
 * Note:
 * Set APP_ENV=development in the .env Laravel application file to enable development mode.
 */
class EnsureWhenRunningInDevelopmentOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!EnsureWhenRunningInDevelopmentOnly::isRunningInDevelopmentEnvironment()) {
            return new Response("Not running in development.", 403);
        }

        return $next($request);
    }
    
    public static function isRunningInDevelopmentEnvironment() :bool {
        return config("app.env") == "development";
    }
}
