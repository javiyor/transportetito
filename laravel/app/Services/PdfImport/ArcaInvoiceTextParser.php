<?php

namespace App\Services\PdfImport;

class ArcaInvoiceTextParser
{
    public function parse(string $text): array
    {
        $result = [
            'cuit' => null,
            'razon_social' => null,
            'tipo' => null,
            'numero' => null,
            'fecha_emision' => null,
            'subtotal' => null,
            'iva_total' => null,
            'total' => null,
            'percepciones' => [],
            'retenciones' => [],
            'iva_items' => [],
            'moneda' => 'ARS',
            'confianza' => 0,
        ];

        $lines = explode("\n", $text);
        $fullText = mb_strtoupper($text);

        $result['cuit'] = $this->extractCuit($fullText, $lines);
        $result['razon_social'] = $this->extractRazonSocial($lines);
        $result['tipo'] = $this->extractTipo($fullText, $lines);
        $result['numero'] = $this->extractNumero($fullText, $lines);
        $result['fecha_emision'] = $this->extractFecha($fullText, $lines);
        $result['total'] = $this->extractTotal($fullText, $lines);
        $result['subtotal'] = $this->extractSubtotal($fullText, $lines, $result['total']);
        $result['iva_total'] = $this->extractIvaTotal($fullText, $lines);
        $result['iva_items'] = $this->extractIvaItems($lines);
        $result['percepciones'] = $this->extractPercepciones($fullText, $lines);
        $result['retenciones'] = $this->extractRetenciones($fullText, $lines);
        $result['moneda'] = $this->extractMoneda($fullText);
        $result['confianza'] = $this->calcConfianza($result);

        return $result;
    }

    private function extractCuit(string $fullText, array $lines): ?string
    {
        if (preg_match('/CUIT[:\s]*([\d\-]{11,13})/', $fullText, $m)) {
            return preg_replace('/\D/', '', $m[1]);
        }
        if (preg_match('/CUIT\s*(?:DEL\s+)?(?:EMISOR|COMPRADOR|PROVEEDOR|CLIENTE)?[:\s]*(\d{2}[\-\.]?\d{8}[\-\.]?\d{1})/', $fullText, $m)) {
            return preg_replace('/\D/', '', $m[1]);
        }
        foreach ($lines as $line) {
            $line = mb_strtoupper(trim($line));
            if (preg_match('/(\d{2}[\-\.]?\d{8}[\-\.]?\d{1})/', $line, $m)) {
                $cuit = preg_replace('/\D/', '', $m[1]);
                if (strlen($cuit) === 11) {
                    return $cuit;
                }
            }
        }
        return null;
    }

    private function extractRazonSocial(array $lines): ?string
    {
        $searching = false;
        foreach ($lines as $line) {
            $upper = mb_strtoupper(trim($line));
            if (preg_match('/RAZ[OÓ]N\s*SOCIAL/', $upper)) {
                $searching = true;
                continue;
            }
            if ($searching && trim($line) !== '') {
                return trim($line);
            }
        }
        return null;
    }

    private function extractTipo(string $fullText, array $lines): ?string
    {
        if (preg_match('/(FACTURA|NOTA\s*CR[EÉ]DITO|NOTA\s*D[EÉ]BITO|RECIBO|COMPROBANTE)\s*([A-E])/i', $fullText, $m)) {
            $tipo = strtoupper($m[1]);
            $letra = strtoupper($m[2]);
            return match (true) {
                str_contains($tipo, 'CREDITO') || str_contains($tipo, 'CRÉDITO') => 'NC'.$letra,
                str_contains($tipo, 'DEBITO') || str_contains($tipo, 'DÉBITO') => 'ND'.$letra,
                str_contains($tipo, 'FACTURA') => 'FA'.$letra,
                default => null,
            };
        }
        foreach ($lines as $line) {
            $upper = mb_strtoupper(trim($line));
            if (preg_match('/(FACTURA|NOTA)\s*(?:DE\s*)?(?:CR[EÉ]DITO|D[EÉ]BITO)?\s*([A-E])\b/i', $upper, $m)) {
                $tipo = strtoupper($m[1]);
                $letra = strtoupper($m[2]);
                if ($tipo === 'NOTA') {
                    if (str_contains($upper, 'CREDITO') || str_contains($upper, 'CRÉDITO')) return 'NC'.$letra;
                    if (str_contains($upper, 'DEBITO') || str_contains($upper, 'DÉBITO')) return 'ND'.$letra;
                }
                return 'FA'.$letra;
            }
        }
        return null;
    }

    private function extractNumero(string $fullText, array $lines): ?string
    {
        if (preg_match('/(?:N[UÚ]MERO|COMP\w*)\s*(?:DE\s*)?:?\s*(\d{4,5}\s*-?\s*\d{6,8})/i', $fullText, $m)) {
            return trim($m[1]);
        }
        foreach ($lines as $line) {
            if (preg_match('/(\d{4,5}\s*-?\s*\d{6,8})/', $line, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    }

    private function extractFecha(string $fullText, array $lines): ?string
    {
        $patterns = [
            '/FECHA\s*(?:DE\s*)?(?:EMISI[OÓ]N)?\s*:?\s*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i',
            '/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/',
        ];
        foreach ($patterns as $pat) {
            if (preg_match($pat, $fullText, $m)) {
                $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $y = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];
                if (checkdate((int) $mo, (int) $d, (int) $y)) {
                    return "$y-$mo-$d";
                }
            }
        }
        return null;
    }

    private function extractTotal(string $fullText, array $lines): ?float
    {
        if (preg_match('/IMPORTE\s*(?:TOTAL|CONSTANCIA)?\s*:?\s*\$?\s*([\d\.]+\,[\d]{2})/i', $fullText, $m)) {
            return $this->parseDecimal($m[1]);
        }
        if (preg_match('/TOTAL\s*:?\s*\$?\s*([\d\.]+\,[\d]{2})/i', $fullText, $m)) {
            return $this->parseDecimal($m[1]);
        }
        return null;
    }

    private function extractSubtotal(string $fullText, array $lines, ?float $total): ?float
    {
        if (preg_match('/(?:SUBTOTAL|IMPORTE\s*NETO|NETO)\s*:?\s*\$?\s*([\d\.]+\,[\d]{2})/i', $fullText, $m)) {
            return $this->parseDecimal($m[1]);
        }
        return $total;
    }

    private function extractIvaTotal(string $fullText, array $lines): ?float
    {
        if (preg_match('/IVA\s*(?:TOTAL|?)\s*:?\s*\$?\s*([\d\.]+\,[\d]{2})/i', $fullText, $m)) {
            return $this->parseDecimal($m[1]);
        }
        return null;
    }

    private function extractIvaItems(array $lines): array
    {
        $items = [];
        $inIva = false;
        foreach ($lines as $line) {
            $upper = mb_strtoupper(trim($line));
            if (preg_match('/ALICUOTA\s*IVA|DETALLE\s*IVA/', $upper)) {
                $inIva = true;
                continue;
            }
            if ($inIva && preg_match('/([\d\.]+\,[\d]{1,4})\s*%\s*(?:SOBRE)?\s*\$?\s*([\d\.]+\,[\d]{2})\s*\$?\s*([\d\.]+\,[\d]{2})/', $line, $m)) {
                $items[] = [
                    'alicuota' => $this->parseDecimal($m[1]),
                    'base_imponible' => $this->parseDecimal($m[2]),
                    'importe' => $this->parseDecimal($m[3]),
                ];
                continue;
            }
            if ($inIva && preg_match('/([\d\.]+\,[\d]{1,4})\s*%\s*\$?\s*([\d\.]+\,[\d]{2})/', $line, $m)) {
                $items[] = [
                    'alicuota' => $this->parseDecimal($m[1]),
                    'base_imponible' => $this->parseDecimal($m[2]),
                    'importe' => round($this->parseDecimal($m[2]) * $this->parseDecimal($m[1]) / 100, 2),
                ];
            }
        }
        return $items;
    }

    private function extractPercepciones(string $fullText, array $lines): array
    {
        $items = [];
        $inPercep = false;
        foreach ($lines as $line) {
            $upper = mb_strtoupper(trim($line));
            if (str_contains($upper, 'PERCEPCION') || str_contains($upper, 'PERCEPCIÓN')) {
                $inPercep = true;
            }
            if ($inPercep && preg_match('/([\d\.]+\,[\d]{2})\s*$/', $line, $m)) {
                $importe = $this->parseDecimal($m[1]);
                $concepto = $this->detectConcepto($upper);
                $items[] = ['concepto' => $concepto, 'importe' => $importe];
            }
            if ($inPercep && (str_contains($upper, 'RETENCION') || str_contains($upper, 'RETENCIÓN') || str_contains($upper, 'TOTAL'))) {
                $inPercep = false;
            }
        }
        return $items;
    }

    private function extractRetenciones(string $fullText, array $lines): array
    {
        $items = [];
        $inRet = false;
        foreach ($lines as $line) {
            $upper = mb_strtoupper(trim($line));
            if (str_contains($upper, 'RETENCION') || str_contains($upper, 'RETENCIÓN')) {
                $inRet = true;
            }
            if ($inRet && preg_match('/([\d\.]+\,[\d]{2})\s*$/', $line, $m)) {
                $importe = $this->parseDecimal($m[1]);
                $concepto = $this->detectConceptoRetencion($upper);
                $items[] = ['concepto' => $concepto, 'importe' => $importe];
            }
            if ($inRet && (str_contains($upper, 'PERCEPCION') || str_contains($upper, 'PERCEPCIÓN') || str_contains($upper, 'TOTAL'))) {
                $inRet = false;
            }
        }
        return $items;
    }

    private function extractMoneda(string $fullText): string
    {
        if (preg_match('/MONEDA\s*:?\s*(USD|D[OÓ]LAR|DOLAR)/i', $fullText)) return 'USD';
        if (preg_match('/MONEDA\s*:?\s*(EUR|EURO)/i', $fullText)) return 'EUR';
        if (preg_match('/MONEDA\s*:?\s*(BRL|REAL)/i', $fullText)) return 'BRL';
        return 'ARS';
    }

    private function detectConcepto(string $upper): string
    {
        if (str_contains($upper, 'IVA')) return 'Percepcion IVA';
        if (str_contains($upper, 'INGRESOS BRUTOS') || str_contains($upper, 'IIBB')) return 'Percepcion Ingresos Brutos';
        if (str_contains($upper, 'MUNICIPAL')) return 'Percepcion Municipal';
        if (str_contains($upper, 'ADUANA')) return 'Percepcion Aduanera';
        return 'Percepcion';
    }

    private function detectConceptoRetencion(string $upper): string
    {
        if (str_contains($upper, 'GANANCIAS')) return 'Retencion Ganancias';
        if (str_contains($upper, 'SUSS') || str_contains($upper, 'SEGURO')) return 'Retencion SUSS';
        if (str_contains($upper, 'INGRESOS BRUTOS') || str_contains($upper, 'IIBB')) return 'Retencion Ingresos Brutos';
        if (str_contains($upper, 'IVA')) return 'Retencion IVA';
        return 'Retencion';
    }

    private function parseDecimal(string $value): float
    {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }

    private function calcConfianza(array $data): int
    {
        $score = 0;
        if ($data['cuit']) $score += 20;
        if ($data['tipo']) $score += 15;
        if ($data['numero']) $score += 10;
        if ($data['fecha_emision']) $score += 15;
        if ($data['total']) $score += 20;
        if ($data['subtotal']) $score += 10;
        if ($data['razon_social']) $score += 10;
        return $score;
    }
}
