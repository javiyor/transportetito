<?php

namespace App\Services\Arca;

class ArcaTipoComprobanteResolver
{
    /**
     * @return array<int, array{code:string,label:string}>
     */
    public function opcionesFactura(?string $emisorCondicionIva, ?string $clienteCondicionIva): array
    {
        $emisor = $this->normalizarCondicionIva($emisorCondicionIva);
        $cliente = $this->normalizarCondicionIva($clienteCondicionIva);

        if ($emisor === 'ri') {
            if ($cliente === 'ri') {
                return [
                    ['code' => 'FA', 'label' => 'Factura A'],
                    ['code' => 'FCA', 'label' => 'Factura de Credito A'],
                ];
            }

            return [
                ['code' => 'FB', 'label' => 'Factura B'],
                ['code' => 'FCB', 'label' => 'Factura de Credito B'],
            ];
        }

        return [
            ['code' => 'FC', 'label' => 'Factura C'],
            ['code' => 'FCC', 'label' => 'Factura de Credito C'],
        ];
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
