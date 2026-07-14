<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanDeCuentasController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $tipoFiltro = $request->query('tipo', '');

        $capitulos = CuentaContable::query()
            ->where('empresa_id', $empresaId)
            ->where('nivel', 'capitulo')
            ->when($tipoFiltro, fn ($q) => $q->where('tipo', $tipoFiltro))
            ->orderBy('orden')
            ->get(['id', 'codigo', 'nombre', 'tipo']);

        $arbol = $capitulos->map(fn ($c) => $this->buildTree($c, $empresaId));

        return Inertia::render('Admin/PlanDeCuentas/Index', [
            'arbol' => $arbol,
            'empresaId' => $empresaId,
            'filtroTipo' => $tipoFiltro,
            'totales' => [
                'capitulos' => CuentaContable::where('empresa_id', $empresaId)->where('nivel', 'capitulo')->count(),
                'cuentas' => CuentaContable::where('empresa_id', $empresaId)->whereIn('nivel', ['cuenta', 'subcuenta'])->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:cuentas_contables,id'],
            'codigo' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:activo,pasivo,patrimonio_neto,ingreso,egreso'],
            'naturaleza' => ['nullable', 'in:deudor,acreedor'],
            'nivel' => ['required', 'in:capitulo,rubro,cuenta_madre,cuenta,subcuenta'],
            'contabilizable' => ['sometimes', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        $parent = $data['parent_id'] ? CuentaContable::find($data['parent_id']) : null;
        $codigoCompleto = $parent ? $parent->codigo_completo.'.'.$data['codigo'] : $data['codigo'];

        CuentaContable::create([
            'empresa_id' => $empresaId,
            'parent_id' => $data['parent_id'],
            'codigo' => $data['codigo'],
            'codigo_completo' => $codigoCompleto,
            'codigo_corto' => $request->input('codigo_corto'),
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'naturaleza' => $data['naturaleza'],
            'nivel' => $data['nivel'],
            'activo' => true,
            'contabilizable' => $request->boolean('contabilizable', true),
            'orden' => $data['orden'] ?? 0,
        ]);

        return back()->with('success', 'Cuenta creada.');
    }

    public function update(Request $request, CuentaContable $cuentaContable): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:activo,pasivo,patrimonio_neto,ingreso,egreso'],
            'naturaleza' => ['nullable', 'in:deudor,acreedor'],
            'nivel' => ['required', 'in:capitulo,rubro,cuenta_madre,cuenta,subcuenta'],
            'contabilizable' => ['sometimes', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $parent = $cuentaContable->parent;
        $codigoCompleto = $parent ? $parent->codigo_completo.'.'.$data['codigo'] : $data['codigo'];

        $cuentaContable->update([
            'codigo' => $data['codigo'],
            'codigo_completo' => $codigoCompleto,
            'codigo_corto' => $request->input('codigo_corto'),
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'naturaleza' => $data['naturaleza'],
            'nivel' => $data['nivel'],
            'activo' => $request->boolean('activo', true),
            'contabilizable' => $request->boolean('contabilizable', true),
            'orden' => $data['orden'] ?? 0,
        ]);

        return back()->with('success', 'Cuenta actualizada.');
    }

    public function destroy(CuentaContable $cuentaContable): RedirectResponse
    {
        $cuentaContable->delete();
        return back()->with('success', 'Cuenta eliminada.');
    }

    public function export(Request $request): StreamedResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $empresa = Empresa::find($empresaId);

        $cuentas = CuentaContable::where('empresa_id', $empresaId)
            ->orderBy('codigo_completo')
            ->get(['codigo_completo', 'codigo_corto', 'nombre', 'naturaleza', 'nivel', 'tipo', 'activo', 'contabilizable']);

        return response()->streamDownload(function () use ($cuentas) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Codigo', 'Codigo Corto', 'Denominacion', 'Naturaleza', 'Nivel', 'Tipo', 'Activo', 'Contabilizable']);
            foreach ($cuentas as $c) {
                fputcsv($fh, [
                    $c->codigo_completo,
                    $c->codigo_corto,
                    $c->nombre,
                    $c->naturaleza ?? '',
                    $c->nivel,
                    $c->tipo,
                    $c->activo ? 'Si' : 'No',
                    $c->contabilizable ? 'Si' : 'No',
                ]);
            }
            fclose($fh);
        }, 'plan_de_cuentas.csv', ['Content-Type' => 'text/csv']);
    }

    private function buildTree(CuentaContable $cuenta, int $empresaId): array
    {
        $children = CuentaContable::where('empresa_id', $empresaId)
            ->where('parent_id', $cuenta->id)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'codigo_completo', 'codigo_corto', 'nombre', 'naturaleza', 'nivel', 'tipo', 'activo', 'contabilizable', 'orden']);

        return [
            'id' => $cuenta->id,
            'codigo' => $cuenta->codigo,
            'codigo_completo' => $cuenta->codigo_completo,
            'codigo_corto' => $cuenta->codigo_corto,
            'nombre' => $cuenta->nombre,
            'naturaleza' => $cuenta->naturaleza,
            'nivel' => $cuenta->nivel,
            'tipo' => $cuenta->tipo,
            'activo' => $cuenta->activo,
            'contabilizable' => $cuenta->contabilizable,
            'children' => $children->map(fn ($c) => $this->buildTree($c, $empresaId)),
        ];
    }
}
