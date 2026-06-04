<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Localidad;
use App\Models\Provincia;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Models\TerceroEmpresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TerceroAdminController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = (int) ($request->query('empresa_id') ?: ($request->user()->current_empresa_id ?: 0));

        $query = TerceroCuenta::query()
            ->with(['tercero:id,cuit,razon_social,condicion_iva', 'empresa:id,razon_social', 'provincia:id,nombre', 'localidadRel:id,nombre,provincia_id', 'cobradorUser:id,name'])
            ->leftJoin('tercero_empresa as te', function ($join) {
                $join->on('te.tercero_cuenta_id', '=', 'tercero_cuentas.id')
                    ->on('te.empresa_id', '=', 'tercero_cuentas.empresa_id');
            })
            ->orderBy('tercero_cuentas.numero_cliente');

        if ($empresaId > 0) {
            $query->where('tercero_cuentas.empresa_id', $empresaId);
        }

        $cuentas = $query->get([
            'tercero_cuentas.id',
            'tercero_cuentas.empresa_id',
            'tercero_cuentas.tercero_id',
            'tercero_cuentas.numero_cliente',
            'tercero_cuentas.nombre_cuenta',
            'tercero_cuentas.localidad',
            'tercero_cuentas.barrio',
            'tercero_cuentas.provincia_id',
            'tercero_cuentas.localidad_id',
            'tercero_cuentas.email',
            'tercero_cuentas.enviar_comprobantes_por_email',
            'tercero_cuentas.activo',
            'te.es_cliente',
            'te.es_proveedor',
        ]);

        $proximoNumero = TerceroCuenta::query()
            ->where('empresa_id', $empresaId > 0 ? $empresaId : 0)
            ->max('numero_cliente');

        if ($empresaId <= 0) {
            $proximoNumero = TerceroCuenta::query()
                ->where('empresa_id', $request->user()->current_empresa_id ?: 0)
                ->max('numero_cliente');
        }

        return Inertia::render('Admin/Terceros/Index', [
            'empresas' => Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'empresaId' => $empresaId > 0 ? $empresaId : null,
            'cuentas' => $cuentas,
            'cuitInicial' => $request->query('cuit') ?: null,
            'provincias' => Provincia::query()->orderBy('nombre')->get(['id', 'nombre']),
            'tipoInicial' => $request->query('tipo') ?: null,
            'proximoNumeroCliente' => ($proximoNumero ?? 0) + 1,
            'cobradores' => User::query()->role('cobrador')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'numero_cliente' => ['required', 'integer', 'min:1'],
            'cuit' => ['required', 'string', 'max:32'],
            'razon_social' => ['required', 'string', 'max:255'],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'nombre_cuenta' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'barrio' => ['nullable', 'string', 'max:255'],
            'provincia_id' => ['nullable', 'integer', 'exists:provincias,id'],
            'localidad_id' => ['nullable', 'integer', 'exists:localidades,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'enviar_comprobantes_por_email' => ['sometimes', 'boolean'],
            'cobrador_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'es_cliente' => ['required', 'boolean'],
            'es_proveedor' => ['required', 'boolean'],
        ]);

        $cleanCuit = preg_replace('/\D+/', '', $data['cuit']) ?? '';

        $tercero = Tercero::query()->firstOrCreate(
            ['cuit' => $cleanCuit],
            [
                'razon_social' => $data['razon_social'],
                'condicion_iva' => $data['condicion_iva'] ?: null,
            ]
        );

        $tercero->update([
            'razon_social' => $data['razon_social'],
            'condicion_iva' => $data['condicion_iva'] ?: null,
        ]);

        $cuenta = TerceroCuenta::query()->firstOrCreate(
            ['empresa_id' => $data['empresa_id'], 'numero_cliente' => (int) $data['numero_cliente']],
            [
                'tercero_id' => $tercero->id,
                'nombre_cuenta' => $data['nombre_cuenta'] ?: null,
                'localidad' => $data['localidad'] ?: null,
                'barrio' => $data['barrio'] ?: null,
                'provincia_id' => $data['provincia_id'] ?: null,
                'localidad_id' => $data['localidad_id'] ?: null,
                'email' => $data['email'] ?: null,
                'enviar_comprobantes_por_email' => (bool) ($data['enviar_comprobantes_por_email'] ?? false),
                'cobrador_user_id' => $data['cobrador_user_id'] ? (int) $data['cobrador_user_id'] : null,
                'activo' => true,
            ]
        );

        $cuenta->update([
            'tercero_id' => $tercero->id,
            'nombre_cuenta' => $data['nombre_cuenta'] ?: null,
            'localidad' => $data['localidad'] ?: null,
            'barrio' => $data['barrio'] ?: null,
            'provincia_id' => $data['provincia_id'] ?: null,
            'localidad_id' => $data['localidad_id'] ?: null,
            'email' => $data['email'] ?: null,
            'enviar_comprobantes_por_email' => (bool) ($data['enviar_comprobantes_por_email'] ?? false),
            'cobrador_user_id' => $data['cobrador_user_id'] ? (int) $data['cobrador_user_id'] : null,
        ]);

        TerceroEmpresa::query()->updateOrCreate(
            ['empresa_id' => $data['empresa_id'], 'tercero_cuenta_id' => $cuenta->id],
            ['es_cliente' => (bool) $data['es_cliente'], 'es_proveedor' => (bool) $data['es_proveedor']]
        );

        return back();
    }

    public function update(Request $request, TerceroCuenta $cuenta): RedirectResponse
    {
        $data = $request->validate([
            'cuit' => ['required', 'string', 'max:32'],
            'razon_social' => ['required', 'string', 'max:255'],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'nombre_cuenta' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'barrio' => ['nullable', 'string', 'max:255'],
            'provincia_id' => ['nullable', 'integer', 'exists:provincias,id'],
            'localidad_id' => ['nullable', 'integer', 'exists:localidades,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'enviar_comprobantes_por_email' => ['sometimes', 'boolean'],
            'cobrador_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'es_cliente' => ['required', 'boolean'],
            'es_proveedor' => ['required', 'boolean'],
        ]);

        $cleanCuit = preg_replace('/\D+/', '', $data['cuit']) ?? '';

        $tercero = Tercero::query()->firstOrCreate(
            ['cuit' => $cleanCuit],
            [
                'razon_social' => $data['razon_social'],
                'condicion_iva' => $data['condicion_iva'] ?: null,
            ]
        );

        $tercero->update([
            'razon_social' => $data['razon_social'],
            'condicion_iva' => $data['condicion_iva'] ?: null,
        ]);

        $cuenta->update([
            'tercero_id' => $tercero->id,
            'nombre_cuenta' => $data['nombre_cuenta'] ?: null,
            'localidad' => $data['localidad'] ?: null,
            'barrio' => $data['barrio'] ?: null,
            'provincia_id' => $data['provincia_id'] ?: null,
            'localidad_id' => $data['localidad_id'] ?: null,
            'email' => $data['email'] ?: null,
            'enviar_comprobantes_por_email' => (bool) ($data['enviar_comprobantes_por_email'] ?? false),
            'cobrador_user_id' => $data['cobrador_user_id'] ? (int) $data['cobrador_user_id'] : null,
        ]);

        TerceroEmpresa::query()->updateOrCreate(
            ['empresa_id' => $cuenta->empresa_id, 'tercero_cuenta_id' => $cuenta->id],
            ['es_cliente' => (bool) $data['es_cliente'], 'es_proveedor' => (bool) $data['es_proveedor']]
        );

        return back();
    }

    public function localidadesPorProvincia(Request $request, Provincia $provincia): JsonResponse
    {
        $localidades = Localidad::query()
            ->where('provincia_id', $provincia->id)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json($localidades);
    }

    public function lookupByCuit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cuit' => ['required', 'string', 'max:32'],
        ]);

        $cleanCuit = preg_replace('/\D+/', '', $data['cuit']) ?? '';
        $tercero = Tercero::query()->where('cuit', $cleanCuit)->first();

        if (! $tercero) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'tercero' => $tercero->only(['id', 'cuit', 'razon_social', 'condicion_iva']),
        ]);
    }
}
