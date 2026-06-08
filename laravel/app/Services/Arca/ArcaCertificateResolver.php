<?php

namespace App\Services\Arca;

use App\Models\Empresa;
use RuntimeException;

class ArcaCertificateResolver
{
    /**
     * @return array{certPath:string,keyPath:string}
     */
    public function resolve(Empresa $empresa): array
    {
        $env = (string) ($empresa->arca_env ?: 'homologacion');
        if (! in_array($env, ['homologacion', 'produccion'], true)) {
            throw new RuntimeException('ARCA env invalido: '.$env);
        }

        $cuitDigits = preg_replace('/\D+/', '', (string) $empresa->cuit) ?? '';
        if ($cuitDigits === '') {
            throw new RuntimeException('Empresa sin CUIT para ARCA.');
        }

        $baseDir = (string) config('arca.certificates_base_dir');
        $certPath = $baseDir.DIRECTORY_SEPARATOR.$cuitDigits.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.'cert.pem';
        $keyPath = $baseDir.DIRECTORY_SEPARATOR.$cuitDigits.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.'key.pem';

        if (! is_file($certPath)) {
            throw new RuntimeException('Falta certificado ARCA. Ruta esperada: '.$certPath.'. Crear directorio certificados/'.$cuitDigits.'/'.$env.'/ con cert.pem y key.pem (certificado + clave privada AFIP).');
        }
        if (! is_file($keyPath)) {
            throw new RuntimeException('Falta clave privada ARCA. Ruta esperada: '.$keyPath);
        }

        return [
            'certPath' => $certPath,
            'keyPath' => $keyPath,
        ];
    }

    public function diagnostic(Empresa $empresa): array
    {
        $env = (string) ($empresa->arca_env ?: 'homologacion');
        $cuitDigits = preg_replace('/\D+/', '', (string) $empresa->cuit) ?? '';
        $baseDir = (string) config('arca.certificates_base_dir');

        $checks = [];

        $checks[] = [
            'check' => 'Extension SOAP',
            'status' => extension_loaded('soap') ? 'ok' : 'error',
            'detail' => extension_loaded('soap') ? 'Disponible' : 'NO disponible (docker-php-ext-install soap)',
        ];

        $checks[] = [
            'check' => 'Extension OpenSSL',
            'status' => extension_loaded('openssl') ? 'ok' : 'error',
            'detail' => extension_loaded('openssl') ? 'Disponible' : 'NO disponible',
        ];

        $certPath = $baseDir.'/'.$cuitDigits.'/'.$env.'/cert.pem';
        $keyPath = $baseDir.'/'.$cuitDigits.'/'.$env.'/key.pem';

        $checks[] = [
            'check' => 'Certificado (cert.pem)',
            'status' => is_file($certPath) ? 'ok' : 'error',
            'detail' => is_file($certPath) ? $certPath : 'NO encontrado en: '.$certPath,
        ];

        $checks[] = [
            'check' => 'Clave privada (key.pem)',
            'status' => is_file($keyPath) ? 'ok' : 'error',
            'detail' => is_file($keyPath) ? $keyPath : 'NO encontrado en: '.$keyPath,
        ];

        foreach (['wsaa', 'wsfe'] as $service) {
            $wsdl = config("arca.$service.$env.wsdl", '');
            $checks[] = [
                'check' => 'WSDL '.strtoupper($service).' ('.$env.')',
                'status' => $wsdl ? 'ok' : 'error',
                'detail' => $wsdl ?: 'No configurado en config/arca.php',
            ];
        }

        return $checks;
    }
}
