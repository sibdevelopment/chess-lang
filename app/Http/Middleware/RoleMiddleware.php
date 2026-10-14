<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                abort(401, 'Unauthenticated.');
            }

            return redirect('/login');
        }

        $user = Auth::user();
        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => array_map('trim', explode(',', $role)))
            ->filter()
            ->values()
            ->all();

        if ($allowedRoles === [] || !in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
