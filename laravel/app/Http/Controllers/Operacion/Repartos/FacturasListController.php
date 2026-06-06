<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Deposito;
use App\Models\TerceroCuenta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Zona;
use Illuminate\Http\Request;
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

        $zonas = Zona::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $localidades = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('localidad')
            ->where('localidad', '!=', '')
            ->distinct()
            ->orderBy('localidad')
            ->pluck('localidad');

        $query = Comprobante::query()
            ->with([
                'entregaCuenta.tercero:id,cuit,razon_social',
                'entregaCuenta:id,empresa_id,tercero_id,numero_cliente,nombre_cuenta,direccion,localidad,cp,telefono,zona_id',
            ])
            ->where('empresa_id', $empresaId)
            ->where('estado', 'emitida')
            ->whereDoesntHave('hojaRutaItems', fn ($q) => $q->where('estado_entrega', 'entregado'))
            ->orderBy('id');

        if ($fecha) {
            $query->whereDate('fecha_emision', $fecha);
        }

        if ($zonaId > 0) {
            $query->whereHas('entregaCuenta', fn ($q) => $q->where('zona_id', $zonaId));
        }

        if ($localidad !== '') {
            $query->whereHas('entregaCuenta', fn ($q) => $q->where('localidad', $localidad));
        }

        if (in_array($tipo, ['factura_interna', 'guia_envio'], true)) {
            $query->where('tipo', $tipo);
        }

        $comprobantes = $query->get();

        return Inertia::render('Operacion/Repartos/Facturas', [
            'zonas' => $zonas,
            'localidades' => $localidades,
            'filters' => [
                'zona_id' => $zonaId ?: null,
                'localidad' => $localidad !== '' ? $localidad : null,
                'fecha' => $fecha,
                'tipo' => $tipo,
            ],
            'facturas' => $comprobantes,
            'vehiculos' => Vehiculo::query()->where('empresa_id', $empresaId)->where('activo', true)->orderBy('patente')->get(['id', 'patente', 'marca', 'modelo']),
            'choferes' => User::query()->role('chofer')->orderBy('name')->get(['id', 'name', 'email']),
            'depositos' => Deposito::query()->where('empresa_id', $empresaId)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
