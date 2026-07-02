<?php

namespace App\Services\Arca;

use App\Models\Empresa;
use DateTimeImmutable;
use RuntimeException;
use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Cache;

class WsaaClient
{
    public function __construct(private ArcaCertificateResolver $resolver)
    {
    }

    /**
     * @return array{token:string,sign:string,expiresAt:DateTimeImmutable}
     */
    public function loginWsfe(Empresa $empresa): array
    {
        return $this->login($empresa, 'wsfe');
    }

    public function loginPadronA5(Empresa $empresa): array
    {
        return $this->login($empresa, 'ws_sr_padron_a5');
    }

    private function login(Empresa $empresa, string $service): array
    {
        $cacheKey = 'arca.wsaa.'.$service.'.'.(int) $empresa->id.'.'.(string) ($empresa->arca_env ?: 'homologacion');

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['token'], $cached['sign'], $cached['expiresAt'])) {
            try {
                $exp = new DateTimeImmutable((string) $cached['expiresAt']);
                if ($exp->getTimestamp() - time() > 60) {
                    return [
                        'token' => (string) $cached['token'],
                        'sign' => (string) $cached['sign'],
                        'expiresAt' => $exp,
                    ];
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if (! extension_loaded('soap')) {
            throw new RuntimeException('PHP extension soap no disponible.');
        }

        $paths = $this->resolver->resolve($empresa);
        $env = (string) ($empresa->arca_env ?: 'homologacion');

        $wsdl = (string) config("arca.wsaa.$env.wsdl");
        if ($wsdl === '') {
            throw new RuntimeException('WSDL WSAA no configurado para '.$env);
        }

        $traXml = $this->buildTra($service);
        $cms = $this->signTra($traXml, $paths['certPath'], $paths['keyPath']);

        try {
            $client = new SoapClient($wsdl, [
                'exceptions' => true,
                'trace' => false,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ]),
                'connection_timeout' => 30,
            ]);
            $result = $client->loginCms(['in0' => $cms]);
        } catch (SoapFault $e) {
            throw new RuntimeException('WSAA loginCms fallo: '.$e->getMessage(), 0, $e);
        }

        $xml = (string) ($result->loginCmsReturn ?? '');
        if ($xml === '') {
            throw new RuntimeException('WSAA loginCms retorno vacio.');
        }

        $parsed = @simplexml_load_string($xml);
        if (! $parsed) {
            throw new RuntimeException('WSAA devolvio XML invalido.');
        }

        $token = (string) ($parsed->credentials->token ?? '');
        $sign = (string) ($parsed->credentials->sign ?? '');
        $exp = (string) ($parsed->header->expirationTime ?? '');

        if ($token === '' || $sign === '' || $exp === '') {
            throw new RuntimeException('WSAA devolvio credenciales incompletas.');
        }

        $expiresAt = new DateTimeImmutable($exp);

        $ttl = max(60, $expiresAt->getTimestamp() - time() - 60);
        Cache::put($cacheKey, [
            'token' => $token,
            'sign' => $sign,
            'expiresAt' => $expiresAt->format('c'),
        ], $ttl);

        return [
            'token' => $token,
            'sign' => $sign,
            'expiresAt' => $expiresAt,
        ];
    }

    private function buildTra(string $service): string
    {
        $now = new DateTimeImmutable('now');
        $gen = $now->modify('-60 seconds');
        $exp = $now->modify('+12 hours');

        $uniqueId = (string) $now->getTimestamp();

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
  <header>
    <uniqueId>{$uniqueId}</uniqueId>
    <generationTime>{$gen->format('c')}</generationTime>
    <expirationTime>{$exp->format('c')}</expirationTime>
  </header>
  <service>{$service}</service>
</loginTicketRequest>
XML;
    }

    private function signTra(string $traXml, string $certPath, string $keyPath): string
    {
        $tmpDir = sys_get_temp_dir();
        $traFile = tempnam($tmpDir, 'tra_');
        $outFile = tempnam($tmpDir, 'tra_sig_');
        if (! $traFile || ! $outFile) {
            throw new RuntimeException('No se pudo crear archivo temporal para WSAA.');
        }

        file_put_contents($traFile, $traXml);

        $flags = PKCS7_BINARY | PKCS7_DETACHED;
        $ok = openssl_pkcs7_sign(
            $traFile,
            $outFile,
            'file://'.$certPath,
            'file://'.$keyPath,
            [],
            $flags
        );

        @unlink($traFile);

        if (! $ok) {
            @unlink($outFile);
            throw new RuntimeException('No se pudo firmar TRA (openssl_pkcs7_sign).');
        }

        $signed = (string) file_get_contents($outFile);
        @unlink($outFile);

        // Extract base64 payload from S/MIME output
        $parts = preg_split("/\R\R/", $signed, 2);
        $payload = $parts[1] ?? '';
        $payload = preg_replace('/[^A-Za-z0-9+\/=]/', '', (string) $payload) ?? '';

        if ($payload === '') {
            throw new RuntimeException('No se pudo extraer CMS firmado.');
        }

        return $payload;
    }
}
