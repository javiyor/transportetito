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
        $auth = $this->wsaa->loginConstanciaInscripcion($empresa);
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
                        'ciphers' => 'DEFAULT@SECLEVEL=1',
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

        $personaReturn = $result?->personaReturn ?? null;
        $datosGenerales = $personaReturn?->datosGenerales ?? null;

        if (! $datosGenerales) {
            $error = $this->formatErrors($personaReturn?->errorConstancia?->error ?? null);

            return [
                'found' => false,
                'error' => $error ?: 'ARCA no devolvió datos generales para el CUIT consultado.',
            ];
        }

        $cuitPersona = (string) ($datosGenerales->idPersona ?? $cuitDigits);
        if (preg_replace('/\D+/', '', $cuitPersona) !== $cuitDigits) {
            return [
                'found' => false,
                'error' => 'ARCA devolvió una persona con CUIT distinto al consultado.',
            ];
        }

        $domicilio = null;
        if (! empty($datosGenerales->domicilioFiscal)) {
            $dom = $datosGenerales->domicilioFiscal;
            $domicilio = [
                'direccion' => (string) ($dom->direccion ?? ''),
                'localidad' => (string) ($dom->localidad ?? ''),
                'provincia' => (string) ($dom->descripcionProvincia ?? ''),
                'codigo_postal' => (string) ($dom->codPostal ?? ''),
            ];
        }

        $razonSocial = trim((string) ($datosGenerales->razonSocial ?? ''));
        if ($razonSocial === '') {
            $razonSocial = trim((string) ($datosGenerales->apellido ?? '').' '.(string) ($datosGenerales->nombre ?? ''));
        }

        return [
            'found' => true,
            'cuit' => $cuitPersona,
            'razon_social' => $razonSocial,
            'condicion_iva' => $this->mapCondicionIva($personaReturn),
            'domicilio' => $domicilio,
        ];
    }

    private function mapCondicionIva(object $personaReturn): string
    {
        $datosMonotributo = $personaReturn->datosMonotributo ?? null;
        if ($datosMonotributo && (
            ! empty($datosMonotributo->categoriaMonotributo)
            || ! empty($datosMonotributo->actividadMonotributista)
            || ! empty($datosMonotributo->impuesto)
        )) {
            return 'Monotributo';
        }

        $impuestos = $personaReturn->datosRegimenGeneral->impuesto ?? null;
        foreach ($this->asList($impuestos) as $impuesto) {
            $id = (int) ($impuesto->idImpuesto ?? 0);
            $descripcion = mb_strtolower(trim((string) ($impuesto->descripcionImpuesto ?? '')));
            if (str_contains($descripcion, 'exent')) {
                return 'IVA Exento';
            }
            if ($id === 30 || str_contains($descripcion, 'iva responsable inscripto')) {
                return 'IVA Responsable Inscripto';
            }
            if (str_contains($descripcion, 'iva no responsable')) {
                return 'IVA no Responsable';
            }
        }

        return '';
    }

    /** @return array<int, mixed> */
    private function asList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    private function formatErrors(mixed $errors): string
    {
        return implode('; ', array_filter(array_map(
            static fn ($error) => trim((string) $error),
            $this->asList($errors),
        )));
    }
}
