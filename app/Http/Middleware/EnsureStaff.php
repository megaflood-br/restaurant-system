<?php

namespace App\Http\Middleware;

use App\Support\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->user()?->role;

        if (! in_array($role, UserRole::values(), true)) {
            abort(403);
        }

        return $next($request);
    }
}
