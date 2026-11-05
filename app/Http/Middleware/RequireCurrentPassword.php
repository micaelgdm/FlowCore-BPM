<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCurrentPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->passwordMustBeChanged()) {
            return redirect()->route('password.required');
        }

        return $next($request);
    }
}
