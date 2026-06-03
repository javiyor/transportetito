<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\ProveedorComprobante;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Models\TerceroEmpresa;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorComprobanteIndexController extends Controller
{
    private function proveedorComprobantePayload(array $data, TerceroCuenta $cuenta, float $total, array $cotizacion, int $empresaId, int $userId): array
    {
        $tributos = (float) ($data['tributos_total'] ?? 0);

        return [
            'empresa_id' => $empresaId,
            'tercero_cuenta_id' => $cuenta->id,
            'tipo' => $data['tipo'],
            'numero' => $data['numero'] ?: null,
            'estado' => 'emitida',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'subtotal' => $data['subtotal'],
            'iva_total' => $data['iva_total'],
            'tributos_total' => $tributos,
            'total' => $total,
            'fecha_emision' => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'detalle' => [
                'cotizacion' => $cotizacion,
            ],
            'creado_por_user_id' => $userId,
        ];
    }

    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $proveedores = TerceroCuenta::query()
            ->with('tercero:id,cuit,razon_social')
            ->where('empresa_id', $empresaId)
            ->whereExists(function ($q) use ($empresaId) {
                $q->selectRaw('1')
                    ->from('tercero_empresa as te')
                    ->whereColumn('te.tercero_cuenta_id', 'tercero_cuentas.id')
                    ->where('te.empresa_id', $empresaId)
                    ->where('te.es_proveedor', true);
            })
            ->orderBy('numero_cliente')
            ->get(['id', 'tercero_id', 'numero_cliente', 'nombre_cuenta']);

        $comprobantes = ProveedorComprobante::query()
            ->where('empresa_id', $empresaId)
            ->with('cuenta.tercero:id,cuit,razon_social')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Compras/Proveedores/Comprobantes/Index', [
            'proveedores' => $proveedores,
            'comprobantes' => $comprobantes,
        ]);
    }

    public function store(Request $request, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $data = $request->validate([
            'tercero_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'tipo' => ['required', 'string', 'max:64'],
            'numero' => ['nullable', 'string', 'max:64'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'iva_total' => ['required', 'numeric', 'min:0'],
            'tributos_total' => ['nullable', 'numeric', 'min:0'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $cuenta = TerceroCuenta::query()->findOrFail($data['tercero_cuenta_id']);
        abort_unless((int) $cuenta->empresa_id === $empresaId, 422);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha_emision']);
        $tributos = (float) ($data['tributos_total'] ?? 0);
        $total = round((float) $data['subtotal'] + (float) $data['iva_total'] + $tributos, 2);

        $comprobante = ProveedorComprobante::query()->create(
            $this->proveedorComprobantePayload($data, $cuenta, $total, $cotizacion, $empresaId, $request->user()->id)
        );

        \App\Models\CtaCteMovimiento::query()->create([
            'empresa_id' => $empresaId,
            'tercero_cuenta_id' => $cuenta->id,
            'fecha' => $data['fecha_emision'],
            'tipo' => 'factura_proveedor',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe_signed' => $total,
            'referencia_tipo' => 'proveedor_comprobante',
            'referencia_id' => $comprobante->id,
            'observacion' => $data['observacion'] ?: ('Comprobante proveedor '.$comprobante->id),
        ]);

        return back()->with('success', 'Comprobante de proveedor registrado.');
    }

    public function lookupByCuit(Request $request): JsonResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $data = $request->validate([
            'cuit' => ['required', 'string', 'max:32'],
        ]);

        $cleanCuit = preg_replace('/\D+/', '', $data['cuit']) ?? '';
        $tercero = Tercero::query()->where('cuit', $cleanCuit)->first();

        if (! $tercero) {
            return response()->json(['found' => false]);
        }

        $cuenta = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_id', $tercero->id)
            ->first();

        return response()->json([
            'found' => true,
            'tercero' => $tercero->only(['id', 'cuit', 'razon_social', 'condicion_iva']),
            'cuenta' => $cuenta ? $cuenta->only(['id', 'numero_cliente', 'nombre_cuenta', 'email', 'localidad', 'barrio']) : null,
        ]);
    }

    public function storeProveedor(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $data = $request->validate([
            'cuit' => ['required', 'string', 'max:32'],
            'razon_social' => ['required', 'string', 'max:255'],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'nombre_cuenta' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'barrio' => ['nullable', 'string', 'max:255'],
        ]);

        $cleanCuit = preg_replace('/\D+/', '', $data['cuit']) ?? '';
        $tercero = Tercero::query()->firstOrCreate(
            ['cuit' => $cleanCuit],
            ['razon_social' => $data['razon_social'], 'condicion_iva' => $data['condicion_iva'] ?: null]
        );

        $tercero->update([
            'razon_social' => $data['razon_social'],
            'condicion_iva' => $data['condicion_iva'] ?: null,
        ]);

        $numeroCliente = ((int) TerceroCuenta::query()->where('empresa_id', $empresaId)->max('numero_cliente')) + 1;

        $cuenta = TerceroCuenta::query()->firstOrCreate(
            ['empresa_id' => $empresaId, 'tercero_id' => $tercero->id],
            [
                'numero_cliente' => $numeroCliente,
                'nombre_cuenta' => $data['nombre_cuenta'] ?: null,
                'email' => $data['email'] ?: null,
                'localidad' => $data['localidad'] ?: null,
                'barrio' => $data['barrio'] ?: null,
                'activo' => true,
            ]
        );

        $cuenta->update([
            'nombre_cuenta' => $data['nombre_cuenta'] ?: null,
            'email' => $data['email'] ?: null,
            'localidad' => $data['localidad'] ?: null,
            'barrio' => $data['barrio'] ?: null,
        ]);

        TerceroEmpresa::query()->updateOrCreate(
            ['empresa_id' => $empresaId, 'tercero_cuenta_id' => $cuenta->id],
            ['es_cliente' => false, 'es_proveedor' => true]
        );

        return back()->with('success', 'Proveedor guardado.');
    }

    public function updateProveedor(Request $request, TerceroCuenta $cuenta): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $cuenta->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'cuit' => ['required', 'string', 'max:32'],
            'razon_social' => ['required', 'string', 'max:255'],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'nombre_cuenta' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'barrio' => ['nullable', 'string', 'max:255'],
        ]);

        $cleanCuit = preg_replace('/\D+/', '', $data['cuit']) ?? '';
        $tercero = Tercero::query()->firstOrCreate(
            ['cuit' => $cleanCuit],
            ['razon_social' => $data['razon_social'], 'condicion_iva' => $data['condicion_iva'] ?: null]
        );

        $tercero->update([
            'razon_social' => $data['razon_social'],
            'condicion_iva' => $data['condicion_iva'] ?: null,
        ]);

        $cuenta->update([
            'tercero_id' => $tercero->id,
            'nombre_cuenta' => $data['nombre_cuenta'] ?: null,
            'email' => $data['email'] ?: null,
            'localidad' => $data['localidad'] ?: null,
            'barrio' => $data['barrio'] ?: null,
        ]);

        TerceroEmpresa::query()->updateOrCreate(
            ['empresa_id' => $empresaId, 'tercero_cuenta_id' => $cuenta->id],
            ['es_cliente' => false, 'es_proveedor' => true]
        );

        return back()->with('success', 'Proveedor actualizado.');
    }
}
