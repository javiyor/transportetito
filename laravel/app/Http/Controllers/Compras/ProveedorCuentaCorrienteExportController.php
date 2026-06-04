<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProveedorCuentaCorrienteExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $filtro = (string) ($request->query('filtro') ?: 'todos');
        $buscar = trim((string) ($request->query('buscar') ?: ''));

        $cuentas = TerceroCuenta::query()
            ->with(['tercero:id,cuit,razon_social'])
            ->where('empresa_id', $empresaId)
            ->whereHas('movimientosCtaCte', fn ($q) => $q->whereIn('tipo', ['factura_proveedor', 'pago_proveedor']))
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->whereHas('tercero', function ($qq) use ($buscar) {
                    $qq->where('razon_social', 'ilike', '%'.$buscar.'%')
                        ->orWhere('cuit', 'ilike', '%'.$buscar.'%');
                });
            })
            ->orderBy('numero_cliente')
            ->get();

        $movs = CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('tercero_cuenta_id', $cuentas->pluck('id'))
            ->whereIn('tipo', ['factura_proveedor', 'pago_proveedor'])
            ->get()
            ->groupBy('tercero_cuenta_id');

        return response()->streamDownload(function () use ($cuentas, $movs, $filtro) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Proveedor', 'CUIT', 'Saldo']);
            foreach ($cuentas as $cuenta) {
                $saldo = round((float) $movs->get($cuenta->id, collect())->sum('importe_signed'), 2);
                if ($filtro === 'con_saldo' && $saldo <= 0) {
                    continue;
                }
                if ($filtro === 'sin_saldo' && $saldo > 0) {
                    continue;
                }
                fputcsv($fh, [$cuenta->tercero?->razon_social, $cuenta->tercero?->cuit, $saldo]);
            }
            fclose($fh);
        }, 'proveedores_ctacte.csv', ['Content-Type' => 'text/csv']);
    }
}
