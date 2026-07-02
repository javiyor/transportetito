<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserHorario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserHorariosUpdateController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'horarios' => ['required', 'array', 'size:7'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:0,6'],
            'horarios.*.hora_desde' => ['nullable', 'string', 'date_format:H:i'],
            'horarios.*.hora_hasta' => ['nullable', 'string', 'date_format:H:i'],
        ]);

        $user->horarios()->delete();

        foreach ($data['horarios'] as $horario) {
            if ($horario['hora_desde'] && $horario['hora_hasta']) {
                UserHorario::create([
                    'user_id' => $user->id,
                    'dia_semana' => $horario['dia_semana'],
                    'hora_desde' => $horario['hora_desde'],
                    'hora_hasta' => $horario['hora_hasta'],
                ]);
            }
        }

        return back()->with('flash.success', 'Horarios actualizados.');
    }
}
