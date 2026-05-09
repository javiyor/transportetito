<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->blocked_at) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $request->session()->flash('flash.bannerStyle', 'danger');
            $request->session()->flash('flash.banner', 'Tu usuario esta bloqueado.');

            return redirect()->route('login');
        }

        return $next($request);
    }
}
