<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Los roles permitidos se pasan como argumentos separados por comas.
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->user() || !in_array($request->user()->role, $roles)) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }
        return $next($request);
    }
}
