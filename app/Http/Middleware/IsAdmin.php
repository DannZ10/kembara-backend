<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $roleValue = $user?->role instanceof UserRole ? $user->role->value : $user?->role;

        if (! $user || $roleValue !== UserRole::ADMIN->value) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
