<?php

namespace App\Services\Arca;

use App\Models\Empresa;
use RuntimeException;

class ArcaCertificateManager
{
    private const KEY_BITS = 2048;
    private const KEY_TYPE = OPENSSL_KEYTYPE_RSA;
    private const DIGEST_ALGO = 'sha256';
    private const CSR_DAYS = 365 * 2;

    public function __construct(private ArcaCertificateResolver $resolver) {}

    public function getCertDir(Empresa $empresa, string $env): string
    {
        $cuitDigits = preg_replace('/\D+/', '', (string) $empresa->cuit) ?? '';
        $baseDir = (string) config('arca.certificates_base_dir');
        $dir = $baseDir.DIRECTORY_SEPARATOR.$cuitDigits.DIRECTORY_SEPARATOR.$env;
        return $dir;
    }

    public function ensureDir(Empresa $empresa, string $env): string
    {
        $dir = $this->getCertDir($empresa, $env);
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio: '.$dir);
        }
        return $dir;
    }

    public function getStatus(Empresa $empresa, string $env): array
    {
        $dir = $this->getCertDir($empresa, $env);
        $certPath = $dir.DIRECTORY_SEPARATOR.'cert.pem';
        $keyPath = $dir.DIRECTORY_SEPARATOR.'key.pem';
        $csrPath = $dir.DIRECTORY_SEPARATOR.'csr.pem';

        $hasKey = is_file($keyPath);
        $hasCert = is_file($certPath);
        $hasCsr = is_file($csrPath);

        $certInfo = null;
        if ($hasCert) {
            $cert = @openssl_x509_read(file_get_contents($certPath));
            if ($cert) {
                $parsed = openssl_x509_parse($cert);
                $certInfo = [
                    'subject' => $parsed['subject'] ?? [],
                    'issuer' => $parsed['issuer'] ?? [],
                    'validFrom' => $parsed['validFrom_time_t'] ?? null,
                    'validTo' => $parsed['validTo_time_t'] ?? null,
                    'serial' => $parsed['serialNumber'] ?? '',
                    'hash' => $parsed['hash'] ?? '',
                ];
            }
        }

        $keyInfo = null;
        if ($hasKey) {
            $key = @openssl_get_privatekey(file_get_contents($keyPath));
            if ($key) {
                $details = openssl_pkey_get_details($key);
                $keyInfo = [
                    'bits' => $details['bits'] ?? 0,
                    'type' => $details['type'] ?? 0,
                ];
            }
        }

        $keyPairMatch = false;
        if ($hasCert && $hasKey) {
            $cert = @openssl_x509_read(file_get_contents($certPath));
            $key = @openssl_get_privatekey(file_get_contents($keyPath));
            if ($cert && $key) {
                $keyPairMatch = openssl_x509_check_private_key($cert, $key);
            }
        }

        return [
            'env' => $env,
            'dir' => $dir,
            'hasKey' => $hasKey,
            'hasCsr' => $hasCsr,
            'hasCert' => $hasCert,
            'keyPairMatch' => $keyPairMatch,
            'certInfo' => $certInfo,
            'keyInfo' => $keyInfo,
        ];
    }

    public function generateKeyAndCsr(Empresa $empresa, string $env): array
    {
        $dir = $this->ensureDir($empresa, $env);
        $keyPath = $dir.DIRECTORY_SEPARATOR.'key.pem';
        $csrPath = $dir.DIRECTORY_SEPARATOR.'csr.pem';

        $key = openssl_pkey_new([
            'private_key_bits' => self::KEY_BITS,
            'private_key_type' => self::KEY_TYPE,
        ]);

        if (! $key) {
            throw new RuntimeException('Error al generar clave privada: '.openssl_error_string());
        }

        $csr = openssl_csr_new([
            'commonName' => (string) $empresa->cuit,
            'organizationName' => mb_substr((string) $empresa->razon_social, 0, 64),
            'countryName' => 'AR',
            'serialNumber' => 'CUIT '.preg_replace('/\D+/', '', (string) $empresa->cuit),
        ], $key, [
            'digest_alg' => self::DIGEST_ALGO,
        ]);

        if (! $csr) {
            throw new RuntimeException('Error al generar CSR: '.openssl_error_string());
        }

        $keyOut = '';
        openssl_pkey_export($key, $keyOut);
        file_put_contents($keyPath, $keyOut, LOCK_EX);

        openssl_csr_export($csr, $csrOut);
        file_put_contents($csrPath, $csrOut, LOCK_EX);

        @chmod($keyPath, 0600);
        @chmod($csrPath, 0644);

        return [
            'keyPath' => $keyPath,
            'csrPath' => $csrPath,
            'csrContent' => file_get_contents($csrPath),
        ];
    }

    public function uploadCert(Empresa $empresa, string $env, string $certPem): array
    {
        $dir = $this->ensureDir($empresa, $env);
        $certPath = $dir.DIRECTORY_SEPARATOR.'cert.pem';
        $keyPath = $dir.DIRECTORY_SEPARATOR.'key.pem';

        if (! is_file($keyPath)) {
            throw new RuntimeException('Debe generar primero la clave privada (key.pem) antes de subir el certificado.');
        }

        $cert = @openssl_x509_read($certPem);
        if (! $cert) {
            throw new RuntimeException('El contenido no es un certificado X.509 válido. Verificar que sea formato PEM.');
        }

        $key = @openssl_get_privatekey(file_get_contents($keyPath));
        if (! $key) {
            throw new RuntimeException('La clave privada existente es inválida. Regenerar clave y CSR.');
        }

        $match = openssl_x509_check_private_key($cert, $key);

        if (! $match) {
            throw new RuntimeException('El certificado no corresponde a la clave privada existente. Asegúrate de subir el certificado firmado para el CSR generado desde este sistema.');
        }

        file_put_contents($certPath, $certPem, LOCK_EX);
        @chmod($certPath, 0644);

        return [
            'certPath' => $certPath,
            'message' => 'Certificado subido y verificado correctamente.',
        ];
    }
}
