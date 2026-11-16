<?php

namespace App\Services\Cobranzas;

use App\Models\TerceroCuenta;
use App\Models\User;

class CuentaCorrienteAccess
{
    public function puedeAcceder(User $user, TerceroCuenta $cuenta): bool
    {
        if ((int) $cuenta->empresa_id === (int) $user->current_empresa_id) {
            return true;
        }

        return $user->hasRole('cobrador') && (int) $cuenta->cobrador_user_id === (int) $user->id;
    }
}
