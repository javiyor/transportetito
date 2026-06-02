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
            throw new RuntimeException('Falta certificado ARCA: '.$certPath);
        }
        if (! is_file($keyPath)) {
            throw new RuntimeException('Falta clave privada ARCA: '.$keyPath);
        }

        return [
            'certPath' => $certPath,
            'keyPath' => $keyPath,
        ];
    }
}
