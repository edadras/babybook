<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Story Studio triggers paid AI generations, so access is closed by default.
 */
class EnsureStoryStudioAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('story-studio.access.allow_guests')) {
            return $next($request);
        }

        if (! $request->user()) {
            if (! $request->expectsJson() && Route::has('login')) {
                return redirect()->guest(route('login'));
            }

            abort(Response::HTTP_UNAUTHORIZED);
        }

        abort_unless(Gate::allows('access-story-studio'), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
