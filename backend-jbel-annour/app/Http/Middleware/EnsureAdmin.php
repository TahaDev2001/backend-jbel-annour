<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Accès réservé à l\'administrateur.'], 403);
        }

        return $next($request);
    }
}