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
        $cuitPatterns = [
            '/C[U0]IT[:\s]*([\d\-]{11,13})/',
            '/C[U0]IT\s*(?:DEL\s+)?(?:EMISOR|COMPRADOR|PROVEEDOR|CLIENTE)?[:\s]*(\d{2}[\-\.]?\d{8}[\-\.]?\d{1})/',
            '/C[U0][IL][T1][:\s]*([\d\-]{11,13})/',
        ];
        foreach ($cuitPatterns as $pat) {
            if (preg_match($pat, $fullText, $m)) {
                return preg_replace('/\D/', '', $m[1]);
            }
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
            if (preg_match('/RAZ[OÓ0][N]\s*S[O0]C[IL]AL/', $upper)) {
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
        $tipoPat = 'FACTURA|FACTURA\s*ELECTRONICA';
        $ncPat = 'NOTA\s*CR[EÉ][D]ITO|N[O0]TA\s*CR[EÉ][D]IT[O0]|N[O0][T1][A0]\s*CR[EÉ][D]IT[O0]';
        $ndPat = 'NOTA\s*D[EÉ]BITO|N[O0]TA\s*D[EÉ]BIT[O0]|N[O0][T1][A0]\s*D[EÉ]BIT[O0]';
        if (preg_match('/('.$tipoPat.'|'.$ncPat.'|'.$ndPat.')\s*([A-E])/i', $fullText, $m)) {
            $tipo = strtoupper($m[1]);
            $letra = strtoupper($m[2]);
            if (preg_match('/CR[EÉ][D]IT[O0]/', $tipo)) return 'NC'.$letra;
            if (preg_match('/D[EÉ]BIT[O0]/', $tipo)) return 'ND'.$letra;
            return 'FA'.$letra;
        }
        foreach ($lines as $line) {
            $upper = mb_strtoupper(trim($line));
            $pat = '/('.$tipoPat.'|NOTA)\s*(?:DE\s*)?(?:CR[EÉ][D]ITO|D[EÉ]BITO)?\s*([A-E])\b/i';
            if (preg_match($pat, $upper, $m)) {
                $tipo = strtoupper($m[1]);
                $letra = strtoupper($m[2]);
                if (str_contains($tipo, 'NOTA')) {
                    if (preg_match('/CR[EÉ][D]IT[O0]/', $upper)) return 'NC'.$letra;
                    if (preg_match('/D[EÉ]BIT[O0]/', $upper)) return 'ND'.$letra;
                }
                return 'FA'.$letra;
            }
        }
        return null;
    }

    private function extractNumero(string $fullText, array $lines): ?string
    {
        $fullText = preg_replace('/[|!\(\)\[\]{}_=]/', ' ', $fullText);
        if (preg_match('/(?:N[UÚ0]MER[O0]|COMP\w*)\s*(?:DE\s*)?:?\s*(\d{3,5}\s*-?\s*\d{6,8})/i', $fullText, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/(?:N[UÚ0]MER[O0]|COMP\w*)\s*(?:DE\s*)?:?\s*(\d{3,5}\s*\d{6,8})/i', $fullText, $m)) {
            return trim($m[1]);
        }
        foreach ($lines as $line) {
            if (preg_match('/(\d{3,5}\s*-?\s*\d{6,8})/', $line, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    }

    private function extractFecha(string $fullText, array $lines): ?string
    {
        $fullText = preg_replace('/[|!\(\)\[\]{}_=]/', ' ', $fullText);
        $patterns = [
            '/FECHA\s*(?:DE\s*)?(?:EMISI[OÓ0][N])?\s*:?\s*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i',
            '/FECHA\s*(?:DE\s*)?(?:EMISI[OÓ0][N])?\s*:?\s*(\d{1,2})\s*[\/\-]\s*(\d{1,2})\s*[\/\-]\s*(\d{2,4})/i',
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
        $fullText = preg_replace('/[|!\(\)\[\]{}]/', ' ', $fullText);
        $patterns = [
            '/IMPORTE\s*(?:TOTAL|C[O0]NSTANCIA)?\s*:?\s*\$?\s*([\d]\S*[\d])/i',
            '/TOTAL\s*(?:GENERAL)?\s*:?\s*\$?\s*([\d]\S*[\d])/i',
        ];
        foreach ($patterns as $pat) {
            if (preg_match($pat, $fullText, $m)) {
                $candidate = $this->parseOcrNumber($m[1]);
                if ($candidate !== null) return $candidate;
            }
        }
        return null;
    }

    private function parseOcrNumber(string $raw): ?float
    {
        $raw = trim($raw);
        $raw = preg_replace('/[^0-9\.,]/', '', $raw);
        if (preg_match('/^\d{1,3}(?:\.\d{3})*(?:\,\d{2})$/', $raw)) {
            return $this->parseDecimal($raw);
        }
        $raw = str_replace(',', '.', $raw);
        if (is_numeric($raw)) return (float) $raw;
        return null;
    }

    private function extractSubtotal(string $fullText, array $lines, ?float $total): ?float
    {
        $fullText = preg_replace('/[|!\(\)\[\]{}]/', ' ', $fullText);
        if (preg_match('/(?:S[U0]BTOTAL|IMP[O0]RTE\s*NET[O0]|NET[O0])\s*:?\s*\$?\s*([\d]\S*[\d])/i', $fullText, $m)) {
            $candidate = $this->parseOcrNumber($m[1]);
            if ($candidate !== null) return $candidate;
        }
        return $total;
    }

    private function extractIvaTotal(string $fullText, array $lines): ?float
    {
        $fullText = preg_replace('/[|!\(\)\[\]{}]/', ' ', $fullText);
        if (preg_match('/IVA\s*(?:TOTAL|?)\s*:?\s*\$?\s*([\d]\S*[\d])/i', $fullText, $m)) {
            $candidate = $this->parseOcrNumber($m[1]);
            if ($candidate !== null) return $candidate;
        }
        return null;
    }

    private function extractIvaItems(array $lines): array
    {
        $items = [];
        $inIva = false;
        foreach ($lines as $line) {
            $upper = mb_strtoupper(trim($line));
            if (preg_match('/ALICUOTA\s*IVA|DETALLE\s*IVA|IVA\s*D[EÉ]|D[EÉ]TALLE/', $upper)) {
                $inIva = true;
                continue;
            }
            if ($inIva && preg_match('/([\d]{1,2}(?:[\.,][\d]{1,4})?)\s*%\s*(?:\$?)\s*([\d][\d\.,]*[\d])\s*(?:\$?)\s*([\d][\d\.,]*[\d])/', $line, $m)) {
                $items[] = [
                    'alicuota' => $this->parseDecimal($m[1]),
                    'base_imponible' => $this->parseDecimal($m[2]),
                    'importe' => $this->parseDecimal(trim($m[3])),
                ];
                continue;
            }
            if ($inIva && preg_match('/([\d]{1,2}(?:[\.,][\d]{1,4})?)\s*%\s*(?:\$?)\s*([\d][\d\.,]*[\d])/', $line, $m)) {
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
            if (preg_match('/PERCEPCI[O0][N]/', $upper)) {
                $inPercep = true;
            }
            if ($inPercep && preg_match('/([\d][\d\.,]*[\d]{2})\s*$/', $line, $m)) {
                $importe = $this->parseOcrNumber($m[1]);
                if ($importe === null) continue;
                $concepto = $this->detectConcepto($upper);
                $items[] = ['concepto' => $concepto, 'importe' => $importe];
            }
            if ($inPercep && (preg_match('/RETENCI[O0][N]/', $upper) || str_contains($upper, 'TOTAL'))) {
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
            if (preg_match('/RETENCI[O0][N]/', $upper)) {
                $inRet = true;
            }
            if ($inRet && preg_match('/([\d][\d\.,]*[\d]{2})\s*$/', $line, $m)) {
                $importe = $this->parseOcrNumber($m[1]);
                if ($importe === null) continue;
                $concepto = $this->detectConceptoRetencion($upper);
                $items[] = ['concepto' => $concepto, 'importe' => $importe];
            }
            if ($inRet && (preg_match('/PERCEPCI[O0][N]/', $upper) || str_contains($upper, 'TOTAL'))) {
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
