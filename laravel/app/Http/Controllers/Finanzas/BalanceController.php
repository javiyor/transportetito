<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoLinea;
use App\Models\CuentaContable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $capitulos = CuentaContable::query()
            ->where('empresa_id', $empresaId)
            ->where('nivel', 'capitulo')
            ->orderBy('orden')
            ->get(['id', 'codigo', 'nombre', 'tipo']);

        $balance = $capitulos->map(fn ($c) => $this->buildBalanceNode($c, $empresaId));

        $totalDebe = round($balance->sum(fn ($n) => $n['debe']), 2);
        $totalHaber = round($balance->sum(fn ($n) => $n['haber']), 2);

        return Inertia::render('Finanzas/Balance/Index', [
            'balance' => $balance,
            'totales' => [
                'debe' => $totalDebe,
                'haber' => $totalHaber,
                'diferencia' => round($totalDebe - $totalHaber, 2),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $cuentas = CuentaContable::query()
            ->where('empresa_id', $empresaId)
            ->where('contabilizable', true)
            ->orderBy('codigo_completo')
            ->get();

        return response()->streamDownload(function () use ($cuentas) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Codigo', 'Cuenta', 'Debe', 'Haber', 'Saldo Deudor', 'Saldo Acreedor']);

            foreach ($cuentas as $cuenta) {
                $debe = round((float) AsientoLinea::where('cuenta_contable_id', $cuenta->id)->sum('debe'), 2);
                $haber = round((float) AsientoLinea::where('cuenta_contable_id', $cuenta->id)->sum('haber'), 2);
                $saldoDeudor = $cuenta->naturaleza === 'deudor' ? max($debe - $haber, 0) : 0;
                $saldoAcreedor = $cuenta->naturaleza === 'acreedor' ? max($haber - $debe, 0) : 0;

                fputcsv($fh, [
                    $cuenta->codigo_completo,
                    $cuenta->nombre,
                    $debe,
                    $haber,
                    $saldoDeudor,
                    $saldoAcreedor,
                ]);
            }

            fclose($fh);
        }, 'balance_sumas_saldos.csv', ['Content-Type' => 'text/csv']);
    }

    private function buildBalanceNode(CuentaContable $capitulo, int $empresaId): array
    {
        $children = CuentaContable::where('empresa_id', $empresaId)
            ->where('parent_id', $capitulo->id)
            ->orderBy('orden')
            ->get();

        $childNodes = $children->map(fn ($c) => $this->buildBalanceNode($c, $empresaId));

        if ($capitulo->contabilizable) {
            $debe = round((float) AsientoLinea::where('cuenta_contable_id', $capitulo->id)->sum('debe'), 2);
            $haber = round((float) AsientoLinea::where('cuenta_contable_id', $capitulo->id)->sum('haber'), 2);
        } else {
            $debe = 0;
            $haber = 0;
        }

        foreach ($childNodes as $child) {
            $debe = round($debe + $child['debe'], 2);
            $haber = round($haber + $child['haber'], 2);
        }

        $saldoDeudor = 0;
        $saldoAcreedor = 0;
        if ($capitulo->naturaleza === 'deudor' || ! $capitulo->naturaleza) {
            $saldoDeudor = round(max($debe - $haber, 0), 2);
        }
        if ($capitulo->naturaleza === 'acreedor' || ! $capitulo->naturaleza) {
            $saldoAcreedor = round(max($haber - $debe, 0), 2);
        }

        return [
            'id' => $capitulo->id,
            'codigo' => $capitulo->codigo_completo ?? $capitulo->codigo,
            'nombre' => $capitulo->nombre,
            'nivel' => $capitulo->nivel,
            'naturaleza' => $capitulo->naturaleza,
            'debe' => $debe,
            'haber' => $haber,
            'saldo_deudor' => $saldoDeudor,
            'saldo_acreedor' => $saldoAcreedor,
            'children' => $childNodes,
        ];
    }
}
