<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpleadoAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $empleados = Empleado::query()
            ->with('telefonos')
            ->where('empresa_id', $empresaId)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Empleados/Index', [
            'empleados' => $empleados,
            'empresaId' => $empresaId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'max:20', 'unique:empleados,dni'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'puesto' => ['nullable', 'string', 'max:255'],
            'fecha_ingreso' => ['nullable', 'date'],
            'dias_vacaciones' => ['nullable', 'integer', 'min:0', 'max:99'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'telefonos' => ['nullable', 'array'],
            'telefonos.*.numero' => ['required_with:telefonos', 'string', 'max:50'],
            'telefonos.*.referencia' => ['nullable', 'string', 'max:255'],
        ]);

        $empleado = Empleado::query()->create([
            'empresa_id' => $empresaId,
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            'dni' => $data['dni'],
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?: null,
            'domicilio' => $data['domicilio'] ?: null,
            'puesto' => $data['puesto'] ?: null,
            'fecha_ingreso' => $data['fecha_ingreso'] ?: null,
            'dias_vacaciones' => $data['dias_vacaciones'] ?? 14,
            'razon_social' => $data['razon_social'] ?: null,
            'observaciones' => $data['observaciones'] ?: null,
        ]);

        if (! empty($data['telefonos'])) {
            foreach ($data['telefonos'] as $tel) {
                $empleado->telefonos()->create([
                    'numero' => $tel['numero'],
                    'referencia' => $tel['referencia'] ?: null,
                ]);
            }
        }

        return back()->with('success', 'Empleado creado.');
    }

    public function update(Request $request, Empleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'max:20', 'unique:empleados,dni,'.$empleado->id],
            'fecha_nacimiento' => ['nullable', 'date'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'puesto' => ['nullable', 'string', 'max:255'],
            'fecha_ingreso' => ['nullable', 'date'],
            'dias_vacaciones' => ['nullable', 'integer', 'min:0', 'max:99'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'telefonos' => ['nullable', 'array'],
            'telefonos.*.numero' => ['required_with:telefonos', 'string', 'max:50'],
            'telefonos.*.referencia' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $empleado->update([
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            'dni' => $data['dni'],
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?: null,
            'domicilio' => $data['domicilio'] ?: null,
            'puesto' => $data['puesto'] ?: null,
            'fecha_ingreso' => $data['fecha_ingreso'] ?: null,
            'dias_vacaciones' => $data['dias_vacaciones'] ?? 14,
            'razon_social' => $data['razon_social'] ?: null,
            'observaciones' => $data['observaciones'] ?: null,
            'activo' => $data['activo'] ?? true,
        ]);

        $empleado->telefonos()->delete();
        if (! empty($data['telefonos'])) {
            foreach ($data['telefonos'] as $tel) {
                $empleado->telefonos()->create([
                    'numero' => $tel['numero'],
                    'referencia' => $tel['referencia'] ?: null,
                ]);
            }
        }

        return back()->with('success', 'Empleado actualizado.');
    }

    public function destroy(Empleado $empleado): RedirectResponse
    {
        $empleado->delete();
        return back()->with('success', 'Empleado eliminado.');
    }
}