<?php

namespace App\Services\Arca;

use App\Models\Empresa;
use App\Models\Deposito;
use App\Models\Comprobante;
use App\Services\Moneda\MonedaAfipMapper;
use Carbon\CarbonImmutable;
use RuntimeException;
use SoapClient;
use SoapFault;

class WsfeClient
{
    public function __construct(private WsaaClient $wsaa, private MonedaAfipMapper $monedaAfipMapper)
    {
    }

    public function getUltimoAutorizado(Empresa $empresa, int $puntoVenta, int $tipoCbte): int
    {
        $client = $this->soapClient($empresa);
        $auth = $this->auth($empresa);

        try {
            $res = $client->FECompUltimoAutorizado([
                'Auth' => $auth,
                'PtoVta' => $puntoVenta,
                'CbteTipo' => $tipoCbte,
            ]);
        } catch (SoapFault $e) {
            throw new RuntimeException('WSFE FECompUltimoAutorizado fallo: '.$e->getMessage(), 0, $e);
        }

        $cbteNro = (int) ($res->FECompUltimoAutorizadoResult->CbteNro ?? 0);
        return $cbteNro;
    }

    /**
     * Autoriza un comprobante interno como factura WSFE (CAE).
     * v1: usa concepto Servicios y 1 item total.
     */
    public function autorizarComprobante(Comprobante $comprobante, Deposito $depositoCentral, string $tipoArca): Comprobante
    {
        $empresa = $comprobante->empresa()->first();
        if (! $empresa) {
            throw new RuntimeException('Comprobante sin empresa.');
        }

        $tipoCbte = $this->mapTipoCbte($tipoArca, $comprobante);
        $puntoVenta = (int) $depositoCentral->punto_venta_numero;
        if ($puntoVenta <= 0) {
            throw new RuntimeException('Deposito central sin punto de venta.');
        }

        $ultimo = $this->getUltimoAutorizado($empresa, $puntoVenta, $tipoCbte);
        $nro = $ultimo + 1;

        $fecha = $comprobante->fecha_emision
            ? CarbonImmutable::parse($comprobante->fecha_emision)
            : CarbonImmutable::now();

        $fechaYmd = $fecha->format('Ymd');

        $auth = $this->auth($empresa);
        $client = $this->soapClient($empresa);

        // Receptor: v1 CUIT del facturarCuenta; si no existe, aborta
        $cuenta = $comprobante->facturarCuenta()->with('tercero')->first();
        $cuit = preg_replace('/\D+/', '', (string) ($cuenta?->tercero?->cuit ?? '')) ?? '';
        if ($cuit === '') {
            throw new RuntimeException('Cuenta a facturar sin CUIT.');
        }

        $importeTotal = abs((float) $comprobante->total);
        $importeNeto = round($importeTotal / 1.21, 2);
        $importeIva = round($importeTotal - $importeNeto, 2);
        $detalleFacturacion = (array) ($comprobante->detalle_facturacion ?? []);
        $cotizacion = (array) (($detalleFacturacion['calculo']['cotizacion'] ?? $detalleFacturacion['cotizacion'] ?? []) ?: []);
        $moneda = strtoupper((string) ($comprobante->moneda ?: 'ARS'));
        $monId = $this->monedaAfipMapper->toAfipCode($moneda);
        $monCotiz = $moneda === 'ARS' ? 1 : (float) ($cotizacion['tasa_ars'] ?? 0);
        if ($moneda !== 'ARS' && $monCotiz <= 0) {
            throw new RuntimeException('Falta cotizacion para autorizar comprobante en moneda '.$moneda.'.');
        }

        $detalleRequest = [
            'Concepto' => 2, // Servicios
            'DocTipo' => 80, // CUIT
            'DocNro' => (int) $cuit,
            'CbteDesde' => $nro,
            'CbteHasta' => $nro,
            'CbteFch' => $fechaYmd,
            'ImpTotal' => $importeTotal,
            'ImpTotConc' => 0,
            'ImpNeto' => $importeNeto,
            'ImpOpEx' => 0,
            'ImpIVA' => $importeIva,
            'ImpTrib' => 0,
            'MonId' => $monId,
            'MonCotiz' => $monCotiz,
            'FchServDesde' => $fechaYmd,
            'FchServHasta' => $fechaYmd,
            'FchVtoPago' => $fechaYmd,
            'Iva' => [
                'AlicIva' => [
                    [
                        'Id' => 5,
                        'BaseImp' => $importeNeto,
                        'Importe' => $importeIva,
                    ],
                ],
            ],
        ];

        if ((string) $comprobante->tipo === 'nota_credito_interna') {
            $origen = $comprobante->comprobanteOrigen()->first();
            if (! $origen || ! $origen->arca_punto_venta || ! $origen->arca_numero) {
                throw new RuntimeException('La nota de credito no tiene comprobante origen fiscal asociado.');
            }

            $detalleRequest['CbtesAsoc'] = [
                'CbteAsoc' => [
                    [
                        'Tipo' => $this->mapTipoCbte((string) $origen->arca_tipo_cbte, $origen),
                        'PtoVta' => (int) $origen->arca_punto_venta,
                        'Nro' => (int) $origen->arca_numero,
                    ],
                ],
            ];
        }

        $req = [
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $puntoVenta,
                    'CbteTipo' => $tipoCbte,
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => $detalleRequest,
                ],
            ],
        ];

        $requestId = 'wsfe-'.bin2hex(random_bytes(8));

        try {
            $res = $client->FECAESolicitar([
                'Auth' => $auth,
                ...$req,
            ]);
        } catch (SoapFault $e) {
            $comprobante->forceFill([
                'arca_request_id' => $requestId,
                'arca_resultado' => 'error',
                'arca_error' => $e->getMessage(),
            ])->save();

            throw new RuntimeException('WSFE FECAESolicitar fallo: '.$e->getMessage(), 0, $e);
        }

        $r = $res->FECAESolicitarResult ?? null;
        $resultado = (string) ($r->FeDetResp->FECAEDetResponse->Resultado ?? '');
        $cae = (string) ($r->FeDetResp->FECAEDetResponse->CAE ?? '');
        $caeVto = (string) ($r->FeDetResp->FECAEDetResponse->CAEFchVto ?? '');

        $errorMsg = null;
        if (isset($r->Errors->Err)) {
            $err = $r->Errors->Err;
            $errorMsg = ((string) ($err->Code ?? '')).' '.((string) ($err->Msg ?? ''));
        }

        if ($resultado !== 'A' || $cae === '') {
            $comprobante->forceFill([
                'arca_request_id' => $requestId,
                'arca_resultado' => $resultado !== '' ? $resultado : 'R',
                'arca_error' => $errorMsg ?: 'WSFE rechazo o sin CAE',
            ])->save();

            throw new RuntimeException('WSFE rechazo: '.($errorMsg ?: $resultado));
        }

        $comprobante->forceFill([
            'requiere_autorizacion_arca' => true,
            'arca_request_id' => $requestId,
            'arca_punto_venta' => $puntoVenta,
            'arca_tipo_cbte' => $tipoArca,
            'arca_numero' => $nro,
            'arca_cae' => $cae,
            'arca_cae_vto' => $caeVto !== '' ? CarbonImmutable::createFromFormat('Ymd', $caeVto)->toDateString() : null,
            'arca_resultado' => $resultado,
            'arca_error' => null,
            'estado' => 'emitida',
        ])->save();

        return $comprobante->refresh();
    }

    private function auth(Empresa $empresa): array
    {
        $creds = $this->wsaa->loginWsfe($empresa);
        $cuit = preg_replace('/\D+/', '', (string) $empresa->cuit) ?? '';
        if ($cuit === '') {
            throw new RuntimeException('Empresa sin CUIT para WSFE.');
        }

        return [
            'Token' => $creds['token'],
            'Sign' => $creds['sign'],
            'Cuit' => (int) $cuit,
        ];
    }

    private function soapClient(Empresa $empresa): SoapClient
    {
        if (! extension_loaded('soap')) {
            throw new RuntimeException('PHP extension soap no disponible.');
        }

        $env = (string) ($empresa->arca_env ?: 'homologacion');
        $wsdl = (string) config("arca.wsfe.$env.wsdl");
        if ($wsdl === '') {
            throw new RuntimeException('WSDL WSFE no configurado para '.$env);
        }

        return new SoapClient($wsdl, ['exceptions' => true, 'trace' => false]);
    }

    private function mapTipoCbte(string $tipo, ?Comprobante $comprobante = null): int
    {
        if ($comprobante && (string) $comprobante->tipo === 'nota_credito_interna') {
            return match ($tipo) {
                'FA' => 3,
                'FB' => 8,
                'FC' => 13,
                'FCA' => 203,
                'FCB' => 208,
                'FCC' => 213,
                default => throw new RuntimeException('Tipo ARCA origen no soportado para nota de credito: '.$tipo),
            };
        }

        return match ($tipo) {
            'FA' => 1,
            'FB' => 6,
            'FC' => 11,
            'FCA' => 201,
            'FCB' => 206,
            'FCC' => 211,
            default => throw new RuntimeException('Tipo ARCA no soportado: '.$tipo),
        };
    }
}
