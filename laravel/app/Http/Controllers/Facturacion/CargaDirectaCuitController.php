<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\CondicionIva;
use App\Models\Empresa;
use App\Models\Localidad;
use App\Models\Provincia;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Services\Arca\ArcaPersonaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargaDirectaCuitController extends Controller
{
    public function consultar(Request $request, ArcaPersonaService $arca): JsonResponse
    {
        $cuentaIds = $this->validarCuentaIds($request);
        $checks = [];

        foreach ($cuentaIds as $cuentaId) {
            $cuenta = $this->cuentaActiva($request, $cuentaId);
            $persona = $this->consultarPersona($request, $cuenta, $arca);
            if ($persona instanceof JsonResponse) {
                $checks[] = [
                    'cuenta_id' => $cuenta->id,
                    'cuenta_nombre' => $cuenta->nombre_cuenta ?: $cuenta->tercero?->razon_social,
                    'cuit' => $cuenta->tercero?->cuit,
                    'cambios' => [],
                    'error' => $persona->getData(true)['error'] ?? 'No se pudo verificar el CUIT en ARCA.',
                ];

                continue;
            }

            $checks[] = [
                'cuenta_id' => $cuenta->id,
                'cuenta_nombre' => $cuenta->nombre_cuenta ?: $cuenta->tercero?->razon_social,
                'cuit' => $persona['cuit'] ?? $cuenta->tercero?->cuit,
                'cambios' => $this->diferencias($cuenta, $persona),
                'error' => null,
            ];
        }

        return response()->json(['found' => true, 'checks' => $checks]);
    }

    public function actualizar(Request $request, ArcaPersonaService $arca): RedirectResponse
    {
        $cuentaIds = $this->validarCuentaIds($request);
        $cuentaFacturadaId = (int) $request->validate([
            'cuenta_facturada_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
        ])['cuenta_facturada_id'];
        $this->cuentaActiva($request, $cuentaFacturadaId);
        $actualizaciones = [];
        $avisos = [];

        foreach ($cuentaIds as $cuentaId) {
            $cuenta = $this->cuentaActiva($request, $cuentaId);
            $persona = $this->consultarPersona($request, $cuenta, $arca);
            if ($persona instanceof JsonResponse) {
                $error = $persona->getData(true)['error'] ?? 'No se pudo consultar el padrón ARCA.';
                if ($cuentaId === $cuentaFacturadaId) {
                    return back()->withErrors(['cuit_arca' => $error]);
                }

                $avisos[] = ($cuenta->nombre_cuenta ?: $cuenta->tercero?->razon_social ?: 'El otro cliente').': no se pudieron verificar los datos en ARCA.';
                continue;
            }

            $actualizaciones[] = [$cuenta, $persona];
        }

        DB::transaction(function () use ($actualizaciones): void {
            foreach ($actualizaciones as [$cuenta, $persona]) {
                $this->aplicarDatos($cuenta, $persona);
            }
        });

        $response = back();
        if ($actualizaciones !== []) {
            $response->with('flash.success', 'Los datos verificados fueron actualizados según ARCA.');
        }
        if ($avisos !== []) {
            $response->with('flash.warning', implode(' ', $avisos));
        }

        return $response;
    }

    /** @return array<int, int> */
    private function validarCuentaIds(Request $request): array
    {
        $data = $request->validate([
            'cuenta_ids' => ['sometimes', 'array', 'min:1'],
            'cuenta_ids.*' => ['required', 'integer', 'distinct', 'exists:tercero_cuentas,id'],
            'cuenta_id' => ['required_without:cuenta_ids', 'integer', 'exists:tercero_cuentas,id'],
        ]);

        $ids = $data['cuenta_ids'] ?? [$data['cuenta_id']];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function cuentaActiva(Request $request, int $cuentaId): TerceroCuenta
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($empresaId > 0, 403);

        return TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->with(['tercero', 'localidadRel', 'provincia'])
            ->findOrFail($cuentaId);
    }

    private function consultarPersona(Request $request, TerceroCuenta $cuenta, ArcaPersonaService $arca): array|JsonResponse
    {
        $nombreCuenta = $cuenta->nombre_cuenta ?: $cuenta->tercero?->razon_social ?: 'Cuenta #'.$cuenta->id;
        $cuit = Tercero::soloDigitos((string) $cuenta->tercero?->cuit);
        if (strlen($cuit) !== 11) {
            return response()->json(['error' => 'La cuenta '.$nombreCuenta.' no tiene un CUIT válido de 11 dígitos.'], 422);
        }

        $empresaId = (int) $request->user()->current_empresa_id;
        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa) {
            return response()->json(['error' => 'No hay una empresa activa para consultar ARCA.'], 422);
        }

        try {
            $persona = $arca->getPersona($empresa, $cuit);
        } catch (\Throwable $e) {
            Log::warning('Error consultando CUIT de carga directa en ARCA', [
                'empresa_id' => $empresaId,
                'cuenta_id' => $cuenta->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'No se pudo consultar el CUIT de '.$nombreCuenta.' en el padrón de ARCA. Verifique la conexión y vuelva a intentar.'], 502);
        }

        if (! ($persona['found'] ?? false)) {
            return response()->json([
                'error' => 'ARCA no encontró el CUIT de '.$nombreCuenta.'. '.($persona['error'] ?? ''),
            ], 404);
        }

        return $persona;
    }

    /** @return array<int, array{campo:string,actual:string,arca:string}> */
    private function diferencias(TerceroCuenta $cuenta, array $persona): array
    {
        $tercero = $cuenta->tercero;
        $domicilioActual = (array) ($tercero?->domicilio_fiscal ?? []);
        $domicilioArca = (array) ($persona['domicilio'] ?? []);

        $actuales = [
            'razon_social' => (string) ($tercero?->razon_social ?? ''),
            'condicion_iva' => (string) ($tercero?->condicion_iva ?? ''),
            'direccion' => (string) ($cuenta->direccion ?: ($domicilioActual['calle'] ?? $domicilioActual['direccion'] ?? '')),
            'localidad' => (string) ($cuenta->localidad ?: $cuenta->localidadRel?->nombre ?: ($domicilioActual['ciudad'] ?? '')),
            'provincia' => (string) ($cuenta->provincia?->nombre ?: ($domicilioActual['provincia'] ?? '')),
            'codigo_postal' => (string) ($cuenta->cp ?: ($domicilioActual['codigo_postal'] ?? '')),
        ];
        $desdeArca = [
            'razon_social' => (string) ($persona['razon_social'] ?? ''),
            'condicion_iva' => (string) ($persona['condicion_iva'] ?? ''),
            'direccion' => (string) ($domicilioArca['direccion'] ?? ''),
            'localidad' => (string) ($domicilioArca['localidad'] ?? ''),
            'provincia' => (string) ($domicilioArca['provincia'] ?? ''),
            'codigo_postal' => (string) ($domicilioArca['codigo_postal'] ?? ''),
        ];
        $labels = [
            'razon_social' => 'Razón social',
            'condicion_iva' => 'Condición frente al IVA',
            'direccion' => 'Dirección',
            'localidad' => 'Localidad',
            'provincia' => 'Provincia',
            'codigo_postal' => 'Código postal',
        ];

        $cambios = [];
        foreach ($labels as $campo => $label) {
            $arcaValue = trim($desdeArca[$campo]);
            if ($arcaValue === '' || $this->normalizar($actuales[$campo]) === $this->normalizar($arcaValue)) {
                continue;
            }

            $cambios[] = [
                'campo' => $label,
                'actual' => trim($actuales[$campo]) ?: '—',
                'arca' => $arcaValue,
            ];
        }

        return $cambios;
    }

    private function aplicarDatos(TerceroCuenta $cuenta, array $persona): void
    {
        $tercero = $cuenta->tercero;
        if (! $tercero) {
            return;
        }

        $domicilioArca = (array) ($persona['domicilio'] ?? []);

        $tercero->razon_social = trim((string) ($persona['razon_social'] ?? '')) ?: $tercero->razon_social;
        $tercero->condicion_iva = trim((string) ($persona['condicion_iva'] ?? '')) ?: $tercero->condicion_iva;

        if ($tercero->condicion_iva !== '') {
            $condicion = CondicionIva::query()->where('nombre', $tercero->condicion_iva)->first();
            if ($condicion) {
                $tercero->condicion_iva_id = $condicion->id;
            }
        }

        if ($domicilioArca !== []) {
            $domicilioActual = (array) ($tercero->domicilio_fiscal ?? []);
            $tercero->domicilio_fiscal = array_replace($domicilioActual, array_filter([
                'calle' => $domicilioArca['direccion'] ?? null,
                'ciudad' => $domicilioArca['localidad'] ?? null,
                'provincia' => $domicilioArca['provincia'] ?? null,
                'codigo_postal' => $domicilioArca['codigo_postal'] ?? null,
            ], fn ($value) => $value !== null && trim((string) $value) !== ''));

            if (! empty($domicilioArca['direccion'])) {
                $cuenta->direccion = $domicilioArca['direccion'];
            }
            if (! empty($domicilioArca['localidad'])) {
                $cuenta->localidad = $domicilioArca['localidad'];
                $provincia = $this->buscarProvincia((string) ($domicilioArca['provincia'] ?? ''));
                if ($provincia) {
                    $cuenta->provincia_id = $provincia->id;
                    $localidad = Localidad::query()
                        ->where('provincia_id', $provincia->id)
                        ->get(['id', 'nombre'])
                        ->first(fn (Localidad $localidad) => $this->normalizar($localidad->nombre) === $this->normalizar((string) $domicilioArca['localidad']));
                    if ($localidad) {
                        $cuenta->localidad_id = $localidad->id;
                    }
                }
            }
            if (! empty($domicilioArca['codigo_postal'])) {
                $cuenta->cp = $domicilioArca['codigo_postal'];
            }
        }

        $tercero->save();
        $cuenta->save();
    }

    private function buscarProvincia(string $nombre): ?Provincia
    {
        if (trim($nombre) === '') {
            return null;
        }

        return Provincia::query()
            ->get(['id', 'nombre'])
            ->first(fn (Provincia $provincia) => $this->normalizar($provincia->nombre) === $this->normalizar($nombre));
    }

    private function normalizar(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }
}
