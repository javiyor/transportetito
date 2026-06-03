<?php

namespace App\Services\Arca;

class ArcaTipoComprobanteResolver
{
    /**
     * @return array<int, array{code:string,label:string}>
     */
    public function opcionesFactura(?string $emisorCondicionIva, ?string $clienteCondicionIva, ?float $importeTotal = null, ?string $clienteCuit = null): array
    {
        $emisor = $this->normalizarCondicionIva($emisorCondicionIva);
        $cliente = $this->normalizarCondicionIva($clienteCondicionIva);
        $permiteCredito = $this->permiteFacturaCredito($emisor, $cliente, $importeTotal, $clienteCuit);

        if ($emisor === 'ri') {
            if ($cliente === 'ri') {
                $out = [
                    ['code' => 'FA', 'label' => 'Factura A'],
                ];
                if ($permiteCredito) {
                    $out[] = ['code' => 'FCA', 'label' => 'Factura de Credito A'];
                }

                return $out;
            }

            $out = [
                ['code' => 'FB', 'label' => 'Factura B'],
            ];
            if ($permiteCredito) {
                $out[] = ['code' => 'FCB', 'label' => 'Factura de Credito B'];
            }

            return $out;
        }

        $out = [
            ['code' => 'FC', 'label' => 'Factura C'],
        ];
        if ($permiteCredito) {
            $out[] = ['code' => 'FCC', 'label' => 'Factura de Credito C'];
        }

        return $out;
    }

    public function permiteFacturaCredito(?string $emisorCondicionIva, ?string $clienteCondicionIva, ?float $importeTotal = null, ?string $clienteCuit = null): bool
    {
        $emisor = $this->normalizarCondicionIva($emisorCondicionIva);
        $cliente = $this->normalizarCondicionIva($clienteCondicionIva);
        $cuit = preg_replace('/\D+/', '', (string) $clienteCuit) ?? '';

        return $emisor === 'ri'
            && $cliente === 'ri'
            && $cuit !== ''
            && strlen($cuit) === 11
            && ((float) ($importeTotal ?? 0)) >= 0;
    }

    public function normalizarCondicionIva(?string $value): string
    {
        $v = mb_strtolower(trim((string) $value));
        $v = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $v);

        return match (true) {
            in_array($v, ['ri', 'responsable inscripto', 'responsable_inscripto'], true) => 'ri',
            in_array($v, ['monotributo', 'monotributista', 'mt'], true) => 'monotributo',
            in_array($v, ['exento', 'iva exento', 'iva_exento'], true) => 'exento',
            in_array($v, ['consumidor final', 'consumidor_final', 'cf'], true) => 'consumidor_final',
            default => $v,
        };
    }
}
