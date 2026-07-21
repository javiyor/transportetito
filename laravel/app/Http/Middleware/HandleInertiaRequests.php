<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $currentEmpresa = null;
        $empresasDisponibles = [];

        if ($user) {
            if (! $user->current_empresa_id) {
                $first = Empresa::query()->orderBy('id')->first();
                if ($first) {
                    $user->forceFill(['current_empresa_id' => $first->id])->save();
                }
            }

            $currentEmpresa = $user->current_empresa_id
                ? Empresa::query()->with('condicionIva:id,nombre')->whereKey($user->current_empresa_id)->first(['id', 'razon_social', 'cuit', 'condicion_iva', 'condicion_iva_id', 'arca_pv_default', 'arca_env', 'logo'])
                : null;

            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                $empresasDisponibles = Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social', 'cuit']);
            } else {
                $empresasDisponibles = $user->empresas()->orderBy('razon_social')->get(['id', 'razon_social', 'cuit']);
            }
        }

        return [
            ...parent::share($request),
            'tt' => [
                'roles' => fn () => $request->user()?->getRoleNames()->values()->all() ?? [],
                'currentEmpresa' => fn () => $currentEmpresa,
                'empresasDisponibles' => fn () => $empresasDisponibles,
                'flash' => [
                    'tempPassword' => fn () => $request->session()->get('tt.temp_password'),
                    'tempPasswordEmail' => fn () => $request->session()->get('tt.temp_password_email'),
                    'importResult' => fn () => $request->session()->get('tt.import_result'),
                    'importError' => fn () => $request->session()->get('tt.import_error'),
                ],
            ],
        ];
    }
}
