<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Deposito;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacturasListController extends Controller
{
    public function __invoke(Request $request)
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $depositoId = (int) ($request->query('deposito_id') ?: 0);
        $tipo = (string) ($request->query('tipo') ?: 'todos');
        $fechaRaw = $request->query('fecha');
        $fecha = is_string($fechaRaw) ? trim($fechaRaw) : null;
        if ($fecha === '') {
            $fecha = null;
        }

        $depositos = Deposito::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $query = Comprobante::query()
            ->with([
                'entregaCuenta.tercero:id,cuit,razon_social',
                'entregaCuenta:id,empresa_id,tercero_id,numero_cliente,nombre_cuenta,direccion,localidad,cp,telefono,zona_id',
            ])
            ->where('empresa_id', $empresaId)
            ->where('estado', 'emitida')
            ->orderBy('id');

        if ($fecha) {
            $query->whereDate('fecha_emision', $fecha);
        }

        if ($depositoId > 0) {
            $query->where('deposito_id', $depositoId);
        }

        if (in_array($tipo, ['factura_interna', 'guia_envio'], true)) {
            $query->where('tipo', $tipo);
        }

        $comprobantes = $query->get();

        return Inertia::render('Operacion/Repartos/Facturas', [
            'depositos' => $depositos,
            'filters' => [
                'deposito_id' => $depositoId ?: null,
                'fecha' => $fecha,
                'tipo' => $tipo,
            ],
            'facturas' => $comprobantes,
        ]);
    }
}
