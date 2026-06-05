<?php

namespace App\Services\PdfImport;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

class ComprobantePdfParser
{
    private ArcaInvoiceTextParser $textParser;

    private const TESSERACT_BIN = '/usr/bin/tesseract';
    private const PDFTOPPM_BIN = '/usr/bin/pdftoppm';

    public function __construct()
    {
        $this->textParser = new ArcaInvoiceTextParser;
    }

    public function parse(UploadedFile $file): array
    {
        $tmpPath = $file->getPathname();
        $text = $this->extractText($tmpPath);

        if (trim($text) === '') {
            $text = $this->ocrPdf($tmpPath);
        }

        if (trim($text) === '') {
            return [
                'success' => false,
                'error' => 'No se pudo extraer texto del PDF (ni digital ni por OCR).',
            ];
        }

        $data = $this->textParser->parse($text);

        if ($data['confianza'] < 20) {
            return [
                'success' => false,
                'error' => 'No se pudieron reconocer datos de factura en el PDF. Confianza: '.$data['confianza'].'%',
                'texto_extraido' => mb_substr($text, 0, 2000),
                'datos_parciales' => $data,
            ];
        }

        return [
            'success' => true,
            'confianza' => $data['confianza'],
            'datos' => $data,
            'texto_extraido' => mb_substr($text, 0, 2000),
        ];
    }

    private function extractText(string $path): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
            return trim($text);
        } catch (\Throwable $e) {
            Log::warning('PDF text extraction failed: '.$e->getMessage());
            return '';
        }
    }

    private function ocrPdf(string $path): string
    {
        $tmpDir = storage_path('app/pdf_ocr_'.uniqid());
        @mkdir($tmpDir, 0755, true);

        try {
            $this->pdfToImages($path, $tmpDir);
            $text = $this->ocrImages($tmpDir);
        } catch (\Throwable $e) {
            Log::warning('PDF OCR failed: '.$e->getMessage());
            $text = '';
        } finally {
            $this->cleanTempDir($tmpDir);
        }

        return $text;
    }

    private function pdfToImages(string $pdfPath, string $outputDir): void
    {
        if (! file_exists(self::PDFTOPPM_BIN)) {
            throw new \RuntimeException('pdftoppm not found at '.self::PDFTOPPM_BIN);
        }

        $escaped = escapeshellarg($pdfPath);
        $outPrefix = escapeshellarg($outputDir.'/page');
        $cmd = self::PDFTOPPM_BIN.' -png -r 300 '.$escaped.' '.$outPrefix.' 2>&1';

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('pdftoppm failed: '.implode("\n", $output));
        }
    }

    private function ocrImages(string $dir): string
    {
        if (! file_exists(self::TESSERACT_BIN)) {
            throw new \RuntimeException('tesseract not found at '.self::TESSERACT_BIN);
        }

        $fullText = '';
        $files = glob($dir.'/page-*.png');

        if (! $files) {
            $files = glob($dir.'/*.png');
        }

        sort($files);

        foreach ($files as $imagePath) {
            $baseName = pathinfo($imagePath, PATHINFO_FILENAME);
            $outputBase = $dir.'/ocr_'.$baseName;

            $escapedImage = escapeshellarg($imagePath);
            $escapedOutput = escapeshellarg($outputBase);
            $cmd = self::TESSERACT_BIN.' '.$escapedImage.' '.$escapedOutput.' -l spa 2>&1';

            exec($cmd, $output, $exitCode);

            if ($exitCode === 0) {
                $txtFile = $outputBase.'.txt';
                if (file_exists($txtFile)) {
                    $fullText .= file_get_contents($txtFile)."\n";
                }
            }
        }

        return trim($fullText);
    }

    private function cleanTempDir(string $dir): void
    {
        if (is_dir($dir)) {
            $files = glob($dir.'/*');
            foreach ($files as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }
}
