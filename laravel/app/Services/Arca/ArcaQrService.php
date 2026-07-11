<?php

namespace App\Services\Arca;

use App\Models\Comprobante;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ArcaQrService
{
    public function generar(Comprobante $comprobante): ?string
    {
        if (!$comprobante->arca_cae || !$comprobante->arca_tipo_cbte) {
            return null;
        }

        if (!class_exists(QRCode::class)) {
            return null;
        }

        $json = $this->jsonData($comprobante);
        if (!$json) return null;

        $options = new QROptions([
            'outputType' => 'png',
            'eccLevel' => 'M',
            'scale' => 4,
            'imageBase64' => true,
        ]);

        return (new QRCode($options))->render($json);
    }

    public function jsonData(Comprobante $comprobante): ?string
    {
        if (!$comprobante->arca_cae || !$comprobante->arca_tipo_cbte) {
            return null;
        }

        $empresa = $comprobante->empresa;
        $receptor = $comprobante->facturarCuenta?->tercero;

        $tipoCmp = (int) $comprobante->arca_tipo_cbte;
        $moneda = match ($comprobante->moneda) {
            'ARS' => 'PES',
            'USD' => 'DOL',
            'EUR' => 'EUR',
            'BRL' => 'BRL',
            default => 'PES',
        };

        $data = [
            'ver' => 1,
            'fecha' => optional($comprobante->fecha_emision)->format('Y-m-d'),
            'cuit' => (int) preg_replace('/[^0-9]/', '', $empresa->cuit ?? '0'),
            'ptoVta' => (int) ($comprobante->arca_punto_venta ?: $empresa->arca_pv_default ?: 0),
            'tipoCmp' => $tipoCmp,
            'nroCmp' => (int) ($comprobante->arca_numero ?: 0),
            'importe' => (float) ($comprobante->total ?: 0),
            'moneda' => $moneda,
            'ctz' => (float) (($comprobante->detalle_facturacion['calculo']['cotizacion']['tasa_ars'] ?? $comprobante->detalle_facturacion['cotizacion']['tasa_ars'] ?? 1)),
            'tipoDocRec' => 80,
            'nroDocRec' => (int) preg_replace('/[^0-9]/', '', $receptor?->cuit ?? '0'),
            'tipoCodAut' => 'E',
            'codAut' => (int) $comprobante->arca_cae,
        ];

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}