<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Mail\CuentaCorrientePendientesMail;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use App\Services\Cobranzas\CuentaCorrienteAccess;
use App\Services\Cobranzas\CuentaCorrientePendientesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CuentaCorrientePendientesEmailController extends Controller
{
    public function __invoke(
        Request $request,
        TerceroCuenta $cuenta,
        CuentaCorrienteAccess $access,
        CuentaCorrientePendientesService $documentosService,
    ): RedirectResponse {
        abort_unless($access->puedeAcceder($request->user(), $cuenta), 404);

        $email = trim((string) $cuenta->email);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('flash.error', 'La cuenta no tiene un email válido para enviar el listado.');
        }

        $datos = $documentosService->paraCuenta($cuenta);
        $empresa = Empresa::query()->find($cuenta->empresa_id);
        $clienteNombre = $cuenta->tercero?->razon_social ?: $cuenta->nombre_cuenta ?: 'Cliente';

        try {
            Mail::to($email)->send(new CuentaCorrientePendientesMail(
                empresaNombre: $empresa?->razon_social ?: 'Transporte Tito',
                clienteNombre: $clienteNombre,
                clienteCuit: $cuenta->tercero?->cuit,
                documentos: $datos['documentos'],
                totales: $datos['totales'],
                fechaEmision: now()->format('d/m/Y H:i'),
            ));
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el estado de cuenta corriente por email', [
                'empresa_id' => $cuenta->empresa_id,
                'tercero_cuenta_id' => $cuenta->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('flash.error', 'No se pudo enviar el listado por email. Revisá la configuración de correo.');
        }

        return back()->with('flash.success', "Listado de documentos pendientes enviado a {$email}.");
    }
}
