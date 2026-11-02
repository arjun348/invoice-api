<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'super_admin' && $user->company?->status !== 'active') {
            return response()->json([
                'code' => 'company_' . ($user->company?->status ?? 'missing'),
                'message' => 'Your company account is not active.',
            ], 403);
        }

        return $next($request);
    }
}

