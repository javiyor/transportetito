<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionCorreo;
use App\Services\Mail\MailConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CorreoConfigController extends Controller
{
    public function index(): Response
    {
        $settings = ConfiguracionCorreo::query()->find(1);

        return Inertia::render('Admin/Correo/Index', [
            'settings' => [
                'scheme' => $settings?->scheme ?: 'smtp',
                'host' => $settings?->host ?: '',
                'port' => $settings?->port ?: 587,
                'username' => $settings?->username ?: '',
                'from_address' => $settings?->from_address ?: '',
                'from_name' => $settings?->from_name ?: config('app.name'),
                'password_configured' => filled($settings?->password),
            ],
        ]);
    }

    public function update(Request $request, MailConfigurationService $mailConfiguration): RedirectResponse
    {
        $data = $request->validate([
            'scheme' => ['required', 'in:smtp,smtps'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $settings = ConfiguracionCorreo::query()->find(1);
        if (! $settings) {
            $settings = new ConfiguracionCorreo;
            $settings->id = 1;
        }
        $settings->fill([
            'mailer' => 'smtp',
            'scheme' => $data['scheme'],
            'host' => trim($data['host']),
            'port' => (int) $data['port'],
            'username' => trim((string) ($data['username'] ?? '')) ?: null,
            'from_address' => trim($data['from_address']),
            'from_name' => trim($data['from_name']),
        ]);

        if (trim((string) ($data['password'] ?? '')) !== '') {
            $settings->password = $data['password'];
        }

        $settings->save();
        $mailConfiguration->apply($settings);

        return back()->with('flash.success', 'Configuración de correo guardada.');
    }

    public function test(Request $request, MailConfigurationService $mailConfiguration): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $settings = $mailConfiguration->loadAndApply();
        if (! $settings) {
            return back()->with('flash.error', 'Primero guardá la configuración SMTP.');
        }

        try {
            Mail::raw('Este es un correo de prueba de TransporteTito. La conexión SMTP está funcionando.', function ($message) use ($data): void {
                $message->to($data['email'])->subject('Prueba de correo - TransporteTito');
            });
        } catch (Throwable $exception) {
            Log::warning('Falló la prueba de configuración SMTP', [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('flash.error', 'Falló la conexión SMTP: '.$exception->getMessage());
        }

        return back()->with('flash.success', 'El servidor SMTP aceptó el correo de prueba. Revisá también la carpeta de spam.');
    }
}
