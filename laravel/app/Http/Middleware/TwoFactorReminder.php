<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class TwoFactorReminder
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $isSensitive =
                (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('contabilidad')));
            $has2fa = !empty($user->two_factor_secret);
            // Solo avisar (no bloquear). Mostramos 1 vez por sesión.
            if ($isSensitive && !$has2fa && !$request->session()->get('two_factor_reminder_shown')) {
                $request->session()->put('two_factor_reminder_shown', true);
                // Banner Jetstream
                $request->session()->flash('flash.bannerStyle', 'danger');
                $request->session()->flash(
                    'flash.banner',
                    'Tu rol requiere 2FA. Activarlo en Perfil > Two-Factor Authentication.'
                );
            }
        }
        return $next($request);
    }
}
