<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class AuthenticateApi extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        // Check if auth cookie exists and set it as Authorization header
        if ($request->cookie('authcookie')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->cookie('authcookie'));
        }

        $this->authenticate($request, $guards);

        return $next($request);
    }
}
