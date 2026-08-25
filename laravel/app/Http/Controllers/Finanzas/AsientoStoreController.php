<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsientoStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($empresaId, 403);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'moneda' => ['sometimes', 'string', 'in:ARS,USD,EUR,BRL'],
            'lineas' => ['required', 'array', 'min:2'],
            'lineas.*.cuenta_contable_id' => ['required', 'integer', 'exists:cuentas_contables,id'],
            'lineas.*.debe' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.haber' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:500'],
            'lineas.*.tercero_cuenta_id' => ['nullable', 'integer', 'exists:tercero_cuentas,id'],
        ]);

        $moneda = $data['moneda'] ?? 'ARS';
        $descripcion = trim((string) ($data['descripcion'] ?? '')) ?: 'Asiento manual';

        // Validar que cuentas pertenezcan a la empresa y sean contabilizables
        $cuentaIds = collect($data['lineas'])->pluck('cuenta_contable_id')->unique()->values();
        $cuentasValidas = CuentaContable::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $cuentaIds)
            ->where('activo', true)
            ->pluck('id')->all();

        $invalidas = $cuentaIds->diff($cuentasValidas);
        if ($invalidas->isNotEmpty()) {
            return back()->withErrors(['lineas' => 'Alguna cuenta no pertenece a la empresa o no está activa.'])->withInput();
        }

        // Validar balanceo
        $totalDebe = 0;
        $totalHaber = 0;
        foreach ($data['lineas'] as $idx => $linea) {
            $debe = round((float) ($linea['debe'] ?? 0), 2);
            $haber = round((float) ($linea['haber'] ?? 0), 2);

            if ($debe < 0 || $haber < 0) {
                return back()->withErrors(['lineas' => "Línea ".($idx+1).": importes no pueden ser negativos."])->withInput();
            }
            if ($debe > 0 && $haber > 0) {
                return back()->withErrors(['lineas' => "Línea ".($idx+1).": debe o haber, no ambos."])->withInput();
            }
            if ($debe == 0 && $haber == 0) {
                return back()->withErrors(['lineas' => "Línea ".($idx+1).": debe tener Debe o Haber > 0."])->withInput();
            }

            $totalDebe += $debe;
            $totalHaber += $haber;
        }

        $totalDebe = round($totalDebe, 2);
        $totalHaber = round($totalHaber, 2);

        if ($totalDebe <= 0 || $totalHaber <= 0) {
            return back()->withErrors(['lineas' => 'El asiento debe tener al menos un movimiento.'])->withInput();
        }

        if (abs($totalDebe - $totalHaber) > 0.01) {
            return back()->withErrors(['lineas' => "Asiento desbalanceado: Debe {$totalDebe} != Haber {$totalHaber}."])->withInput();
        }

        DB::transaction(function () use ($empresaId, $data, $descripcion, $moneda) {
            $asiento = AsientoContable::query()->create([
                'empresa_id' => $empresaId,
                'fecha' => $data['fecha'],
                'moneda' => $moneda,
                'estado' => 'confirmado',
                'referencia_tipo' => 'manual',
                'referencia_id' => null,
                'descripcion' => $descripcion,
            ]);

            foreach ($data['lineas'] as $linea) {
                $asiento->lineas()->create([
                    'cuenta_contable_id' => $linea['cuenta_contable_id'],
                    'tercero_cuenta_id' => $linea['tercero_cuenta_id'] ?? null,
                    'debe' => round((float) ($linea['debe'] ?? 0), 2),
                    'haber' => round((float) ($linea['haber'] ?? 0), 2),
                    'descripcion' => $linea['descripcion'] ?? null,
                ]);
            }
        });

        return back()->with('flash.success', "Asiento creado: {$descripcion} — Debe/Haber $totalDebe.");
    }
}
