<?php

namespace App\Http\Middleware;

use App\Models\UserHorario;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserSchedule
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $adminRole = config('roles.super_admin', 'admin');
        if (method_exists($user, 'hasRole') && $user->hasRole($adminRole)) {
            return $next($request);
        }

        $now = CarbonImmutable::now();
        $dia = (int) $now->dayOfWeek; // 0=domingo..6=sabado
        $hora = $now->format('H:i:s');

        $horario = UserHorario::where('user_id', $user->id)
            ->where('dia_semana', $dia)
            ->first();

        if (! $horario) {
            return $next($request);
        }

        if ($horario->hora_desde === null || $horario->hora_hasta === null) {
            return redirect()->route('dashboard')
                ->with('error', 'No tienes acceso en este horario. Tu cuenta no tiene horario permitido para hoy.');
        }

        if ($hora < $horario->hora_desde || $hora > $horario->hora_hasta) {
            return redirect()->route('dashboard')
                ->with('error', 'Fuera de horario laboral. Tu acceso está limitado de '.$horario->hora_desde.' a '.$horario->hora_hasta.' los '.$this->diaNombre($dia).'.');
        }

        return $next($request);
    }

    private function diaNombre(int $dia): string
    {
        return [0 => 'domingos', 1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábados'][$dia] ?? '';
    }
}
