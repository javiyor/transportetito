<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\ProveedorComprobante;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Models\TerceroEmpresa;
use App\Services\Arca\ArcaTipoComprobanteResolver;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorComprobanteIndexController extends Controller
{
    private const PERCEPCIONES_CATALOGO = [
        'iva' => 'Percepcion IVA',
        'iibb' => 'Percepcion Ingresos Brutos',
        'municipal' => 'Percepcion Municipal',
        'aduana' => 'Percepcion Aduanera',
    ];

    private const RETENCIONES_CATALOGO = [
        'ganancias' => 'Retencion Ganancias',
        'suss' => 'Retencion SUSS',
        'iibb' => 'Retencion Ingresos Brutos',
        'iva' => 'Retencion IVA',
    ];

    private function fiscalDetail(array $data, ?string $tipo = null): array
    {
        $ivaDesglosado = $tipo && str_ends_with($tipo, 'A');

        $ivaItems = collect($data['iva_items'] ?? [])
            ->map(function ($item) {
                $alicuota = (float) ($item['alicuota'] ?? 0);
                $base = round((float) ($item['base_imponible'] ?? 0), 2);
                $importe = round($base * ($alicuota / 100), 2);

                return [
                    'alicuota' => $alicuota,
                    'base_imponible' => $base,
                    'importe' => $importe,
                ];
            })
            ->filter(fn ($item) => $item['base_imponible'] > 0)
            ->values()
            ->all();

        $percepciones = collect($data['percepciones'] ?? [])
            ->map(fn ($item) => [
                'concepto' => trim((string) ($item['concepto'] ?? '')),
                'importe' => round((float) ($item['importe'] ?? 0), 2),
            ])
            ->filter(fn ($item) => $item['concepto'] !== '' || $item['importe'] > 0)
            ->values()
            ->all();

        $retenciones = collect($data['retenciones'] ?? [])
            ->map(fn ($item) => [
                'concepto' => trim((string) ($item['concepto'] ?? '')),
                'importe' => round((float) ($item['importe'] ?? 0), 2),
            ])
            ->filter(fn ($item) => $item['concepto'] !== '' || $item['importe'] > 0)
            ->values()
            ->all();

        $combustible = [
            'tipo' => $data['combustible_tipo'] ?? null,
            'litros' => $data['litros_combustible'] ?? null,
            'impuestos_combustible' => round((float) ($data['impuestos_combustible'] ?? 0), 2),
            'pago_cuenta_combustible' => round((float) ($data['pago_cuenta_combustible'] ?? 0), 2),
        ];

        $subtotal = !empty($ivaItems)
            ? round(collect($ivaItems)->sum('base_imponible'), 2)
            : round((float) ($data['subtotal'] ?? 0), 2);
        $ivaTotal = round(collect($ivaItems)->sum('importe'), 2);
        $tributos = round(collect($percepciones)->sum('importe') + $combustible['impuestos_combustible'], 2);
        $retencionesTotal = round(collect($retenciones)->sum('importe') + $combustible['pago_cuenta_combustible'], 2);
        $total = $ivaDesglosado
            ? round($subtotal + $ivaTotal + $tributos - $retencionesTotal, 2)
            : round($subtotal + $tributos - $retencionesTotal, 2);

        return [
            'subtotal' => $subtotal,
            'iva_total' => $ivaTotal,
            'tributos_total' => $tributos,
            'retenciones_total' => $retencionesTotal,
            'total' => $total,
            'detalle' => [
                'iva_items' => $ivaItems,
                'percepciones' => $percepciones,
                'retenciones' => $retenciones,
                'combustible' => $combustible,
            ],
        ];
    }

    private function proveedorComprobantePayload(array $data, TerceroCuenta $cuenta, float $total, array $cotizacion, int $empresaId, int $userId): array
    {
        $fiscal = $this->fiscalDetail($data, $data['tipo'] ?? null);

        return [
            'empresa_id' => $empresaId,
            'tercero_cuenta_id' => $cuenta->id,
            'tipo' => $data['tipo'],
            'numero' => $data['numero'] ?: null,
            'estado' => 'emitida',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'subtotal' => $fiscal['subtotal'],
            'iva_total' => $fiscal['iva_total'],
            'tributos_total' => $fiscal['tributos_total'],
            'total' => $fiscal['total'],
            'fecha_emision' => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'detalle' => array_merge($fiscal['detalle'], [
                'cotizacion' => $cotizacion,
                'retenciones_total' => $fiscal['retenciones_total'],
            ]),
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

        $resumen = ProveedorComprobante::query()
            ->where('empresa_id', $empresaId)
            ->get()
            ->reduce(function ($acc, ProveedorComprobante $c) {
                $acc['subtotal'] += (float) $c->subtotal;
                $acc['iva_total'] += (float) $c->iva_total;
                $acc['tributos_total'] += (float) $c->tributos_total;
                $acc['retenciones_total'] += (float) (($c->detalle ?? [])['retenciones_total'] ?? 0);
                $acc['total'] += (float) $c->total;
                return $acc;
            }, ['subtotal' => 0, 'iva_total' => 0, 'tributos_total' => 0, 'retenciones_total' => 0, 'total' => 0]);

        return Inertia::render('Compras/Proveedores/Comprobantes/Index', [
            'proveedores' => $proveedores,
            'comprobantes' => $comprobantes,
            'resumen' => array_map(fn ($v) => round((float) $v, 2), $resumen),
            'catalogos' => [
                'percepciones' => collect(self::PERCEPCIONES_CATALOGO)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'retenciones' => collect(self::RETENCIONES_CATALOGO)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            ],
        ]);
    }

    public function tiposArca(Request $request, ArcaTipoComprobanteResolver $arcaTipos): JsonResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $data = $request->validate([
            'tercero_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
        ]);

        $cuenta = TerceroCuenta::query()
            ->with('tercero:id,cuit,razon_social,condicion_iva')
            ->where('empresa_id', $empresaId)
            ->findOrFail($data['tercero_cuenta_id']);

        $empresa = $cuenta->empresa()->firstOrFail();

        $opciones = $arcaTipos->opcionesFactura(
            $cuenta->tercero?->condicion_iva,
            $empresa->condicion_iva,
        );

        $tipos = [];
        $letras = [];
        foreach ($opciones as $opt) {
            $tipos[] = $opt;
            $letras[substr($opt['code'], -1)] = true;
        }

        foreach (array_keys($letras) as $letra) {
            $tipos[] = ['code' => 'NC'.$letra, 'label' => 'Nota de Credito '.$letra];
            $tipos[] = ['code' => 'ND'.$letra, 'label' => 'Nota de Debito '.$letra];
        }

        $condicionProveedor = $arcaTipos->normalizarCondicionIva($cuenta->tercero?->condicion_iva);
        $esRI = $condicionProveedor === 'ri';

        $percepciones = [
            ['value' => 'iibb', 'label' => 'Percepcion Ingresos Brutos'],
            ['value' => 'municipal', 'label' => 'Percepcion Municipal'],
        ];
        if ($esRI) {
            $percepciones[] = ['value' => 'iva', 'label' => 'Percepcion IVA'];
            $percepciones[] = ['value' => 'aduana', 'label' => 'Percepcion Aduanera'];
        }

        $retenciones = [
            ['value' => 'iibb', 'label' => 'Retencion Ingresos Brutos'],
        ];
        if ($esRI) {
            $retenciones[] = ['value' => 'ganancias', 'label' => 'Retencion Ganancias'];
            $retenciones[] = ['value' => 'suss', 'label' => 'Retencion SUSS'];
            $retenciones[] = ['value' => 'iva', 'label' => 'Retencion IVA'];
        }

        return response()->json([
            'tipos' => $tipos,
            'catalogos_impuestos' => [
                'percepciones' => $percepciones,
                'retenciones' => $retenciones,
            ],
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
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'iva_items' => ['nullable', 'array'],
            'iva_items.*.alicuota' => ['required_with:iva_items.*.base_imponible', 'numeric', 'min:0'],
            'iva_items.*.base_imponible' => ['required_with:iva_items.*.alicuota', 'numeric', 'min:0'],
            'percepciones' => ['nullable', 'array'],
            'percepciones.*.concepto' => ['nullable', 'string', 'max:255'],
            'percepciones.*.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones' => ['nullable', 'array'],
            'retenciones.*.concepto' => ['nullable', 'string', 'max:255'],
            'retenciones.*.importe' => ['nullable', 'numeric', 'min:0'],
            'combustible_tipo' => ['nullable', 'string', 'max:64'],
            'litros_combustible' => ['nullable', 'numeric', 'min:0'],
            'impuestos_combustible' => ['nullable', 'numeric', 'min:0'],
            'pago_cuenta_combustible' => ['nullable', 'numeric', 'min:0'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $cuenta = TerceroCuenta::query()->findOrFail($data['tercero_cuenta_id']);
        abort_unless((int) $cuenta->empresa_id === $empresaId, 422);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha_emision']);
        $fiscal = $this->fiscalDetail($data, $data['tipo']);

        $comprobante = ProveedorComprobante::query()->create(
            $this->proveedorComprobantePayload($data, $cuenta, $fiscal['total'], $cotizacion, $empresaId, $request->user()->id)
        );

        \App\Models\CtaCteMovimiento::query()->create([
            'empresa_id' => $empresaId,
            'tercero_cuenta_id' => $cuenta->id,
            'fecha' => $data['fecha_emision'],
            'tipo' => 'factura_proveedor',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe_signed' => $fiscal['total'],
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
