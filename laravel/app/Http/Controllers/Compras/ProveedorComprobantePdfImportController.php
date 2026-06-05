<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Services\PdfImport\ComprobantePdfParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedorComprobantePdfImportController extends Controller
{
    public function __invoke(Request $request, ComprobantePdfParser $parser): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('file');

        $result = $parser->parse($file);

        return response()->json($result);
    }
}
