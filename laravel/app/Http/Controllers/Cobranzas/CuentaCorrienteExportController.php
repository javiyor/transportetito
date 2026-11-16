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
        $user = $request->user();
        $verTodasEmpresas = $user->hasRole('cobrador') && ! $user->hasRole('admin') && $request->boolean('ver_todas_empresas');
        $empresaIds = $verTodasEmpresas
            ? TerceroCuenta::query()->where('cobrador_user_id', $user->id)->distinct()->pluck('empresa_id')
            : collect([$empresaId]);
        $tipoFiltro = (string) ($request->query('filtro') ?: 'todos');
        $cutoff = now()->subDays(30)->toDateString();
        $desde = (string) ($request->query('desde') ?: '');
        $hasta = (string) ($request->query('hasta') ?: '');
        $zonaId = (int) ($request->query('zona_id') ?: 0);
        $localidad = (string) ($request->query('localidad') ?: '');
        $barrio = (string) ($request->query('barrio') ?: '');
        $cobradorUserId = (int) ($request->query('cobrador_user_id') ?: 0);

        $cuentas = TerceroCuenta::query()
            ->with(['empresa:id,razon_social', 'tercero:id,cuit,razon_social', 'zona:id,nombre', 'cobradorUser:id,name'])
            ->when($verTodasEmpresas, fn ($q) => $q->whereIn('empresa_id', $empresaIds), fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereHas('movimientosCtaCte');

        if ($zonaId > 0) {
            $cuentas->where('zona_id', $zonaId);
        }

        if ($localidad !== '') {
            $cuentas->where('localidad', 'like', "%{$localidad}%");
        }

        if ($barrio !== '') {
            $cuentas->where('barrio', 'like', "%{$barrio}%");
        }

        if ($cobradorUserId > 0) {
            $cuentas->where('cobrador_user_id', $cobradorUserId);
        }

        if ($user->hasRole('cobrador') && !$user->hasRole('admin')) {
            $cuentas->where('cobrador_user_id', $user->id);
        }

        $cuentas = $cuentas->orderBy('numero_cliente')->get();

        $movimientosQuery = CtaCteMovimiento::query()
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('tercero_cuenta_id', $cuentas->pluck('id'))
            ->orderBy('fecha');

        if ($desde !== '') {
            $movimientosQuery->whereDate('fecha', '>=', $desde);
        }
        if ($hasta !== '') {
            $movimientosQuery->whereDate('fecha', '<=', $hasta);
        }

        $movimientos = $movimientosQuery->get()
            ->groupBy('tercero_cuenta_id');

        $rows = $cuentas->map(function (TerceroCuenta $cuenta) use ($movimientos, $cutoff, $verTodasEmpresas) {
            $items = $movimientos->get($cuenta->id, collect());
            $saldo = round((float) $items->sum('importe_signed'), 2);
            $vencido30 = round(max(0, (float) $items->where('fecha', '<=', $cutoff)->sum('importe_signed')), 2);

            return [
                'empresa' => $verTodasEmpresas ? $cuenta->empresa?->razon_social : null,
                'numero_cliente' => $cuenta->numero_cliente,
                'razon_social' => $cuenta->tercero?->razon_social,
                'cuit' => $cuenta->tercero?->cuit,
                'zona' => $cuenta->zona?->nombre,
                'localidad' => $cuenta->localidad,
                'barrio' => $cuenta->barrio,
                'cobrador' => $cuenta->cobradorUser?->name,
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
            $headers = ['Nro cliente', 'Razon social', 'CUIT', 'Zona', 'Ciudad', 'Barrio', 'Cobrador', 'Saldo', 'Vencido +30'];
            if (($rows->first()['empresa'] ?? null) !== null) {
                array_unshift($headers, 'Empresa');
            }
            fputcsv($fh, $headers);
            foreach ($rows as $row) {
                $values = [$row['numero_cliente'], $row['razon_social'], $row['cuit'], $row['zona'], $row['localidad'], $row['barrio'], $row['cobrador'], $row['saldo'], $row['vencido_30']];
                if ($row['empresa'] !== null) {
                    array_unshift($values, $row['empresa']);
                }
                fputcsv($fh, $values);
            }
            fclose($fh);
        }, 'cuentas_corrientes.csv', ['Content-Type' => 'text/csv']);
    }
}
