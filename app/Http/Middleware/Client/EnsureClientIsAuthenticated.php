<?php

namespace App\Http\Middleware\Client;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied directly to route groups (not registered as a global alias) so it
 * never collides with the staff-side 'auth' middleware, which redirects to
 * the Fortify login route instead of the client one.
 */
class EnsureClientIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('client')->check()) {
            return redirect()->route('client.login');
        }

        abort_unless(Auth::guard('client')->user()->status === 'active', 403);

        return $next($request);
    }
}
