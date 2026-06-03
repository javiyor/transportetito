<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CuentaCorrienteExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $tipoFiltro = (string) ($request->query('filtro') ?: 'todos');
        $cutoff = now()->subDays(30)->toDateString();

        $cuentas = TerceroCuenta::query()
            ->with(['tercero:id,cuit,razon_social', 'zona:id,nombre'])
            ->where('empresa_id', $empresaId)
            ->whereHas('movimientosCtaCte')
            ->orderBy('numero_cliente')
            ->get();

        $movimientos = CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('tercero_cuenta_id', $cuentas->pluck('id'))
            ->get()
            ->groupBy('tercero_cuenta_id');

        $rows = $cuentas->map(function (TerceroCuenta $cuenta) use ($movimientos, $cutoff) {
            $items = $movimientos->get($cuenta->id, collect());
            $saldo = round((float) $items->sum('importe_signed'), 2);
            $vencido30 = round(max(0, (float) $items->where('fecha', '<=', $cutoff)->sum('importe_signed')), 2);

            return [
                'numero_cliente' => $cuenta->numero_cliente,
                'razon_social' => $cuenta->tercero?->razon_social,
                'cuit' => $cuenta->tercero?->cuit,
                'zona' => $cuenta->zona?->nombre,
                'localidad' => $cuenta->localidad,
                'barrio' => $cuenta->barrio,
                'saldo' => $saldo,
                'vencido_30' => $vencido30,
            ];
        })->filter(function (array $row) use ($tipoFiltro) {
            return match ($tipoFiltro) {
                'vencido' => $row['vencido_30'] > 0,
                'con_saldo' => $row['saldo'] > 0,
                'sin_saldo' => $row['saldo'] <= 0,
                default => true,
            };
        });

        return response()->streamDownload(function () use ($rows) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Nro cliente', 'Razon social', 'CUIT', 'Zona', 'Ciudad', 'Barrio', 'Saldo', 'Vencido +30']);
            foreach ($rows as $row) {
                fputcsv($fh, [$row['numero_cliente'], $row['razon_social'], $row['cuit'], $row['zona'], $row['localidad'], $row['barrio'], $row['saldo'], $row['vencido_30']]);
            }
            fclose($fh);
        }, 'cuentas_corrientes.csv', ['Content-Type' => 'text/csv']);
    }
}
