<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('profile.show') || $request->routeIs('user-password.update') || $request->routeIs('logout')) {
            return $next($request);
        }

        $request->session()->flash('flash.bannerStyle', 'danger');
        $request->session()->flash('flash.banner', 'Debes cambiar tu password antes de continuar.');

        return redirect()->route('profile.show');
    }
}
