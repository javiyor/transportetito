<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CondicionIva;
use App\Models\Empresa;
use App\Services\Arca\ArcaCertificateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EmpresaAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Empresas/Index', [
            'empresas' => Empresa::query()->with('condicionIva:id,nombre')->orderBy('razon_social')->get(),
            'condicionesIva' => CondicionIva::query()->orderBy('codigo_afip')->get(['id', 'codigo_afip', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'cuit' => ['required', 'string', 'max:32', 'unique:empresas,cuit'],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'condicion_iva_id' => ['nullable', 'integer', 'exists:condiciones_iva,id'],
            'moneda_base' => ['required', 'in:ARS,USD,EUR,BRL'],
            'arca_pv_default' => ['required', 'integer', 'min:1'],
            'arca_env' => ['required', 'in:homologacion,produccion'],
            'permite_guias_no_fiscales' => ['sometimes', 'boolean'],

            'telefono' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:64'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],

            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        } else {
            unset($data['logo']);
        }

        foreach (['telefono', 'email', 'whatsapp', 'sitio_web', 'instagram_url', 'facebook_url', 'linkedin_url', 'condicion_iva'] as $k) {
            if (array_key_exists($k, $data) && trim((string) $data[$k]) === '') {
                $data[$k] = null;
            }
        }

        $empresa = Empresa::query()->create($data);

        // Auto-crear plan de cuentas y configuración contable para que gastos/egresos contabilicen
        try {
            Artisan::call('cuentas:reparar-plan', ['empresa_id' => $empresa->id]);
            Artisan::call('db:seed', ['--class' => 'ConfiguracionContableSeeder', '--force' => true]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo auto-crear plan/config para empresa nueva', ['empresa_id' => $empresa->id, 'error' => $e->getMessage()]);
        }

        return back()->with('flash.success', 'Empresa creada y plan contable inicializado.');
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'cuit' => ['required', 'string', 'max:32', 'unique:empresas,cuit,'.$empresa->id],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'condicion_iva_id' => ['nullable', 'integer', 'exists:condiciones_iva,id'],
            'moneda_base' => ['required', 'in:ARS,USD,EUR,BRL'],
            'arca_pv_default' => ['required', 'integer', 'min:1'],
            'arca_env' => ['required', 'in:homologacion,produccion'],
            'permite_guias_no_fiscales' => ['sometimes', 'boolean'],

            'telefono' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:64'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],

            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        } else {
            unset($data['logo']);
        }

        foreach (['telefono', 'email', 'whatsapp', 'sitio_web', 'instagram_url', 'facebook_url', 'linkedin_url', 'condicion_iva'] as $k) {
            if (array_key_exists($k, $data) && trim((string) $data[$k]) === '') {
                $data[$k] = null;
            }
        }

        $empresa->update($data);

        return back()->with('flash.success', 'Empresa actualizada.');
    }

    public function destroy(Request $request, Empresa $empresa): RedirectResponse
    {
        $currentEmpresaId = (int) ($request->user()->current_empresa_id ?: 0);
        if ($currentEmpresaId === (int) $empresa->id) {
            return back()->with('flash.error', 'No se puede eliminar la empresa actualmente seleccionada. Cambiá la empresa activa arriba y reintentá.');
        }

        // Pre-chequeo para dar motivo detallado sin depender del mensaje PG
        $safeCount = function (string $table) use ($empresa): int {
            try {
                return DB::table($table)->where('empresa_id', $empresa->id)->count();
            } catch (\Throwable $e) {
                return 0;
            }
        };
        $checks = [
            'depósitos' => $safeCount('depositos'),
            'clientes/cuentas (tercero_cuentas)' => $safeCount('tercero_cuentas'),
            'zonas' => $safeCount('zonas'),
            'tarifas' => $safeCount('tarifas_relaciones'),
            'comprobantes' => $safeCount('comprobantes'),
            'pedidos' => $safeCount('pedidos'),
            'manifiestos' => $safeCount('manifiestos_ingreso'),
            'hojas de ruta' => $safeCount('hojas_ruta'),
            'vehículos' => $safeCount('vehiculos'),
            'empleados' => $safeCount('empleados'),
            'puestos' => $safeCount('empleado_puestos'),
            'usuarios asignados' => $safeCount('empresa_user'),
            'cta cte' => $safeCount('cta_cte_movimientos'),
            'asientos contables' => $safeCount('asientos_contables'),
            'cuentas contables' => $safeCount('cuentas_contables'),
        ];

        $bloqueos = [];
        foreach ($checks as $label => $cnt) {
            if ($cnt > 0) {
                $bloqueos[] = "{$cnt} {$label}";
            }
        }

        // Checks adicionales que no usan empresa_id directo pero sí relacionan
        $extra = [];
        if (DB::table('users')->where('current_empresa_id', $empresa->id)->exists()) {
            $extra[] = 'usuarios con empresa actual';
        }

        if ($bloqueos || $extra) {
            $detalle = implode(', ', array_merge($bloqueos, $extra));
            return back()->with('flash.error', "No se puede eliminar \"{$empresa->razon_social}\" porque tiene datos asociados: {$detalle}. Eliminá o trasladá esos datos (o usá Blanqueo) y reintentá.");
        }

        try {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }

            $empresa->delete();
        } catch (QueryException $e) {
            $msg = $e->getMessage();
            // Extrae detalle del constraint si PG lo informa
            $detalle = str_contains($msg, 'violates foreign key') ? ' (restricción de clave foránea)' : '';
            return back()->with('flash.error', "No se puede eliminar \"{$empresa->razon_social}\"{$detalle}: la base rechazó el borrado. Revisá Blanqueo/registros asociados. Detalle: ".substr($msg, 0, 400));
        }

        return back()->with('flash.success', "Empresa \"{$empresa->razon_social}\" eliminada.");
    }

    public function arcaDiagnostic(Request $request, ArcaCertificateResolver $resolver): JsonResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $empresa = Empresa::query()->find($empresaId);

        if (! $empresa) {
            return response()->json(['error' => 'No hay empresa activa.']);
        }

        $checks = $resolver->diagnostic($empresa);

        return response()->json([
            'empresa' => $empresa->only(['id', 'razon_social', 'cuit', 'arca_env']),
            'checks' => $checks,
        ]);
    }
}
