<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\Arca\ArcaCertificateManager;
use App\Services\Arca\ArcaCertificateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArcaCertificateController extends Controller
{
    public function index(Request $request, ArcaCertificateManager $manager)
    {
        $empresa = Empresa::query()->find($request->user()->current_empresa_id);

        if (! $empresa) {
            return Inertia::render('Admin/Arca/Certificado', [
                'error' => 'No hay empresa activa. Selecciona una empresa primero.',
            ]);
        }

        $homologacion = $manager->getStatus($empresa, 'homologacion');
        $produccion = $manager->getStatus($empresa, 'produccion');

        $resolver = app(ArcaCertificateResolver::class);
        $diagnostic = $resolver->diagnostic($empresa);

        return Inertia::render('Admin/Arca/Certificado', [
            'empresa' => $empresa->only(['id', 'razon_social', 'cuit', 'arca_env']),
            'homologacion' => $homologacion,
            'produccion' => $produccion,
            'diagnostic' => $diagnostic,
        ]);
    }

    public function generate(Request $request, ArcaCertificateManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'env' => ['required', 'in:homologacion,produccion'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);

        try {
            $result = $manager->generateKeyAndCsr($empresa, $data['env']);
            session()->flash('arca.csr_content', $result['csrContent']);
            return back()->with('flash.success', 'Clave privada y CSR generados en: '.$result['csrPath']);
        } catch (\Throwable $e) {
            return back()->with('flash.error', 'Error: '.$e->getMessage());
        }
    }

    public function upload(Request $request, ArcaCertificateManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'env' => ['required', 'in:homologacion,produccion'],
            'cert_pem' => ['required', 'string'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);

        try {
            $result = $manager->uploadCert($empresa, $data['env'], $data['cert_pem']);
            return back()->with('flash.success', $result['message']);
        } catch (\Throwable $e) {
            return back()->with('flash.error', 'Error: '.$e->getMessage());
        }
    }

    public function downloadCsr(Request $request, ArcaCertificateManager $manager): StreamedResponse
    {
        $data = $request->validate([
            'env' => ['required', 'in:homologacion,produccion'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);
        $dir = $manager->getCertDir($empresa, $data['env']);
        $csrPath = $dir.DIRECTORY_SEPARATOR.'csr.pem';

        if (! is_file($csrPath)) {
            abort(404, 'CSR no encontrado. Generalo primero.');
        }

        $cuitDigits = preg_replace('/\D+/', '', (string) $empresa->cuit) ?? '';
        $filename = 'arca-'.$cuitDigits.'-'.$data['env'].'.csr';

        return response()->streamDownload(function () use ($csrPath) {
            readfile($csrPath);
        }, $filename, ['Content-Type' => 'application/pkcs10']);
    }

    public function downloadKey(Request $request, ArcaCertificateManager $manager): StreamedResponse
    {
        $data = $request->validate([
            'env' => ['required', 'in:homologacion,produccion'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);
        $dir = $manager->getCertDir($empresa, $data['env']);
        $keyPath = $dir.DIRECTORY_SEPARATOR.'key.pem';

        if (! is_file($keyPath)) {
            abort(404, 'Clave privada no encontrada. Generala primero.');
        }

        $cuitDigits = preg_replace('/\D+/', '', (string) $empresa->cuit) ?? '';
        $filename = 'arca-'.$cuitDigits.'-'.$data['env'].'-key.pem';

        return response()->streamDownload(function () use ($keyPath) {
            readfile($keyPath);
        }, $filename, ['Content-Type' => 'application/x-pem-file']);
    }

    public function setEnv(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'env' => ['required', 'in:homologacion,produccion'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);
        $empresa->forceFill(['arca_env' => $data['env']])->save();

        return back()->with('flash.success', 'Entorno ARCA cambiado a: '.$data['env']);
    }
}
