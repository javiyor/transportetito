<?php

namespace App\Services\Arca;

use App\Models\Empresa;
use RuntimeException;
use SoapClient;
use SoapFault;

class ArcaPersonaService
{
    public function __construct(
        private WsaaClient $wsaa,
        private ArcaCertificateResolver $resolver,
    ) {}

    public function getPersona(Empresa $empresa, string $cuit): array
    {
        $auth = $this->wsaa->loginPadronA5($empresa);
        $env = (string) ($empresa->arca_env ?: 'homologacion');
        $wsdl = (string) config("arca.ws_sr_padron_a5.$env.wsdl");

        if ($wsdl === '') {
            throw new RuntimeException('WSDL de PersonaServiceA5 no configurado para '.$env);
        }

        $paths = $this->resolver->resolve($empresa);
        $cuitDigits = preg_replace('/\D+/', '', $cuit);

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

            $params = [
                'token' => $auth['token'],
                'sign' => $auth['sign'],
                'cuitRepresentada' => $empresa->cuit,
                'idPersona' => (int) $cuitDigits,
            ];

            $result = $client->getPersona($params);
        } catch (SoapFault $e) {
            throw new RuntimeException('PersonaServiceA5 getPersona fallo: '.$e->getMessage(), 0, $e);
        }

        $persona = $result?->persona ?? null;

        if (! $persona) {
            return ['found' => false];
        }

        $domicilio = null;
        if (! empty($persona->domicilio)) {
            $dom = $persona->domicilio;
            $domicilio = [
                'direccion' => (string) ($dom->direccion ?? ''),
                'localidad' => (string) ($dom->localidad ?? ''),
                'provincia' => (string) ($dom->provincia ?? ''),
                'codigo_postal' => (string) ($dom->codigoPostal ?? ''),
            ];
        }

        $condicionIvaNombre = $this->mapCondicionIva((int) ($persona->impuesto->id ?? 0));

        return [
            'found' => true,
            'cuit' => (string) ($persona->idPersona ?? $cuit),
            'razon_social' => (string) ($persona->nombre ?? ''),
            'condicion_iva' => $condicionIvaNombre,
            'domicilio' => $domicilio,
        ];
    }

    private function mapCondicionIva(int $codigo): string
    {
        return match ($codigo) {
            1 => 'IVA Responsable Inscripto',
            2 => 'IVA Responsable no Inscripto',
            3 => 'IVA no Responsable',
            4 => 'IVA Sujeto Exento',
            5 => 'Consumidor Final',
            6 => 'Monotributo',
            7 => 'Sujeto no Categorizado',
            8 => 'Proveedor del Exterior',
            9 => 'Cliente del Exterior',
            10 => 'Liberado (Ley 19640)',
            11 => 'IVA Responsable Inscripto (Agente de Percepción)',
            12 => 'Pequeño Contribuyente Eventual',
            13 => 'Monotributista Social',
            default => '',
        };
    }
}
