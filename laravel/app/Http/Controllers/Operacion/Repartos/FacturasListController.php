<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Zona;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FacturasListController extends Controller
{
    public function __invoke(Request $request)
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $zonaId = (int) ($request->query('zona_id') ?: 0);
        $localidad = trim((string) ($request->query('localidad') ?: ''));
        $tipo = (string) ($request->query('tipo') ?: 'todos');
        $fechaRaw = $request->query('fecha');
        $fecha = is_string($fechaRaw) ? trim($fechaRaw) : null;
        if ($fecha === '') {
            $fecha = null;
        }
        $empresaFiltro = (int) ($request->query('empresa_id') ?: 0);

        $empresas = Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']);

        $zonas = Zona::query()
            ->when($empresaFiltro > 0, fn ($q) => $q->where('empresa_id', $empresaFiltro))
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $zonasHoja = Zona::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $localidades = TerceroCuenta::query()
            ->when($empresaFiltro > 0, fn ($q) => $q->where('empresa_id', $empresaFiltro))
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('localidad')->where('localidad', '!=', '');
                })->orWhereNotNull('localidad_id');
            })
            ->with('localidadRel:id,nombre')
            ->get(['localidad', 'localidad_id'])
            ->flatMap(fn (TerceroCuenta $cuenta) => [$cuenta->localidad, $cuenta->localidadRel?->nombre])
            ->map(fn ($nombre) => trim((string) $nombre))
            ->filter()
            ->unique(fn (string $nombre) => Str::lower(Str::ascii($nombre)))
            ->sort(fn (string $a, string $b) => strnatcasecmp(Str::ascii($a), Str::ascii($b)))
            ->values();

        $query = Comprobante::query()
            ->with([
                'empresa:id,razon_social',
                'entregaCuenta.tercero:id,cuit,razon_social,domicilio_fiscal',
                'entregaCuenta.localidadRel:id,nombre',
                'entregaCuenta:id,empresa_id,tercero_id,numero_cliente,nombre_cuenta,direccion,localidad,localidad_id,cp,telefono,zona_id',
                'pedidos:id,remitente_tercero_id,destinatario_tercero_id,destinatario_cuenta_id',
                'pedidos.remitente:id,razon_social',
                'pedidos.destinatario:id,razon_social,domicilio_fiscal',
                'pedidos.destinatarioCuenta:id,tercero_id,localidad,localidad_id',
                'pedidos.destinatarioCuenta.localidadRel:id,nombre',
            ])
            ->where('estado', 'emitida')
            ->where('disponible_para_hoja_ruta', true)
            ->where('tipo', 'not like', 'nota_credito%')
            ->whereDoesntHave('hojaRutaItems', fn ($q) => $q->whereIn('estado_entrega', ['entregado', 'entregado_con_diferencia']));

        if ($empresaFiltro > 0) {
            $query->where('empresa_id', $empresaFiltro);
        }

        if ($fecha) {
            $query->whereDate('fecha_emision', $fecha);
        }

        if ($zonaId > 0) {
            $query->whereHas('entregaCuenta', fn ($q) => $q->where('zona_id', $zonaId));
        }

        if (in_array($tipo, ['factura_interna', 'guia_envio'], true)) {
            $query->where('tipo', $tipo);
        }

        $comprobantes = $query->get();
        $terceroIds = $comprobantes->flatMap(function (Comprobante $comprobante) {
            return collect([$comprobante->entregaCuenta?->tercero_id])
                ->merge($comprobante->pedidos->pluck('destinatario_tercero_id'));
        })->filter()->unique()->values();
        $cuentasPorTercero = TerceroCuenta::query()
            ->whereIn('empresa_id', $comprobantes->pluck('empresa_id')->unique())
            ->whereIn('tercero_id', $terceroIds)
            ->with('localidadRel:id,nombre')
            ->get(['id', 'empresa_id', 'tercero_id', 'localidad', 'localidad_id'])
            ->groupBy(fn (TerceroCuenta $cuenta) => $cuenta->empresa_id.':'.$cuenta->tercero_id);

        foreach ($comprobantes as $comprobante) {
            $comprobante->setAttribute('localidad_destinatario', $this->resolverLocalidadDestinatario($comprobante, $cuentasPorTercero));
        }

        if ($localidad !== '') {
            $localidadNormalizada = Str::lower(Str::ascii($localidad));
            $comprobantes = $comprobantes->filter(
                fn (Comprobante $comprobante) => Str::lower(Str::ascii((string) $comprobante->localidad_destinatario)) === $localidadNormalizada
            );
        }

        $comprobantes = $comprobantes->sort(function (Comprobante $a, Comprobante $b) {
            $localidadA = trim((string) $a->localidad_destinatario);
            $localidadB = trim((string) $b->localidad_destinatario);
            if (($localidadA === '') !== ($localidadB === '')) {
                return $localidadA === '' ? 1 : -1;
            }

            $porLocalidad = strnatcasecmp(Str::ascii($localidadA), Str::ascii($localidadB));
            if ($porLocalidad !== 0) {
                return $porLocalidad;
            }

            $destinatarioA = (string) ($a->entregaCuenta?->tercero?->razon_social ?? '');
            $destinatarioB = (string) ($b->entregaCuenta?->tercero?->razon_social ?? '');
            $porDestinatario = strnatcasecmp(Str::ascii($destinatarioA), Str::ascii($destinatarioB));

            return $porDestinatario !== 0 ? $porDestinatario : ((int) $a->id <=> (int) $b->id);
        })->values();

        return Inertia::render('Operacion/Repartos/Facturas', [
            'zonas' => $zonas,
            'zonasHoja' => $zonasHoja,
            'localidades' => $localidades,
            'empresas' => $empresas,
            'filters' => [
                'zona_id' => $zonaId ?: null,
                'localidad' => $localidad !== '' ? $localidad : null,
                'fecha' => $fecha,
                'tipo' => $tipo,
                'empresa_id' => $empresaFiltro ?: null,
            ],
            'facturas' => $comprobantes,
            'vehiculos' => Vehiculo::query()->where('activo', true)->orderBy('patente')->get(['id', 'patente', 'marca', 'modelo']),
            'choferes' => User::query()->role('chofer')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function storeZona(Request $request): RedirectResponse
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:255']]);
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        abort_unless($empresaId > 0, 403);

        $zona = Zona::query()->firstOrCreate(
            ['empresa_id' => $empresaId, 'nombre' => trim($data['nombre'])],
            [
                'orden_default' => (int) Zona::query()->where('empresa_id', $empresaId)->max('orden_default') + 10,
                'activo' => true,
            ]
        );

        if (! $zona->activo) {
            $zona->update(['activo' => true]);
        }

        return back()->with('flash.success', 'Zona cargada.');
    }

    private function resolverLocalidadDestinatario(Comprobante $comprobante, Collection $cuentasPorTercero): string
    {
        $cuentaEntrega = $comprobante->entregaCuenta;
        $localidadCuenta = trim((string) ($cuentaEntrega?->localidad ?? ''));
        if ($localidadCuenta !== '') {
            return $localidadCuenta;
        }

        $localidadRelacion = trim((string) ($cuentaEntrega?->localidadRel?->nombre ?? ''));
        if ($localidadRelacion !== '') {
            return $localidadRelacion;
        }

        $localidadesPedidos = $comprobante->pedidos
            ->map(function ($pedido): string {
                $cuentaDestino = $pedido->destinatarioCuenta;
                $localidad = trim((string) ($cuentaDestino?->localidad ?? ''));
                if ($localidad !== '') {
                    return $localidad;
                }

                $localidad = trim((string) ($cuentaDestino?->localidadRel?->nombre ?? ''));
                if ($localidad !== '') {
                    return $localidad;
                }

                return trim((string) data_get($pedido->destinatario?->domicilio_fiscal, 'ciudad', ''));
            })
            ->filter()
            ->unique(fn (string $localidad) => Str::lower(Str::ascii($localidad)))
            ->sort(fn (string $a, string $b) => strnatcasecmp(Str::ascii($a), Str::ascii($b)))
            ->values();

        if ($localidadesPedidos->isNotEmpty()) {
            return $localidadesPedidos->implode(', ');
        }

        $terceroIds = collect([$cuentaEntrega?->tercero_id])
            ->merge($comprobante->pedidos->pluck('destinatario_tercero_id'))
            ->filter()
            ->unique();
        $localidadesMismoDestinatario = $terceroIds
            ->flatMap(fn ($terceroId) => $cuentasPorTercero
                ->get($comprobante->empresa_id.':'.$terceroId, collect())
                ->map(fn (TerceroCuenta $cuenta) => $this->localidadDeCuenta($cuenta)))
            ->filter()
            ->unique(fn (string $nombre) => Str::lower(Str::ascii($nombre)))
            ->sort(fn (string $a, string $b) => strnatcasecmp(Str::ascii($a), Str::ascii($b)))
            ->values();

        if ($localidadesMismoDestinatario->isNotEmpty()) {
            return $localidadesMismoDestinatario->implode(', ');
        }

        return trim((string) data_get($cuentaEntrega?->tercero?->domicilio_fiscal, 'ciudad', ''));
    }

    private function localidadDeCuenta(TerceroCuenta $cuenta): string
    {
        $localidad = trim((string) ($cuenta->localidad ?? ''));

        return $localidad !== '' ? $localidad : trim((string) ($cuenta->localidadRel?->nombre ?? ''));
    }
}
