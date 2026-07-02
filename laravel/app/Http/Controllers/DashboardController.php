<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $empresa = Empresa::query()
            ->with(['depositos:id,empresa_id,nombre,direccion,punto_venta_numero'])
            ->orderBy('id')
            ->first();

        $contacts = User::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'blocked_at'])
            ->orderBy('id')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'blocked_at' => $user->blocked_at,
                    'roles' => $user->getRoleNames()->values()->all(),
                ];
            })
            ->values();

        return Inertia::render('Dashboard', [
            'empresa' => $empresa ? [
                'id' => $empresa->id,
                'razon_social' => $empresa->razon_social,
                'cuit' => $empresa->cuit,
                'condicion_iva' => $empresa->condicion_iva,
                'arca_pv_default' => $empresa->arca_pv_default,
                'arca_env' => $empresa->arca_env,
                'depositos' => $empresa->depositos,
            ] : null,
            'contacts' => $contacts,
        ]);
    }
}
