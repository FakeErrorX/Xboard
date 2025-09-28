<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\AuthService;
use Auth;
use Closure;
use Illuminate\Support\Facades\Cache;

class User
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::guard('sanctum')->check()) {
            throw new ApiException('Not logged in or login has expired', 403);
        }
        return $next($request);
    }
}
