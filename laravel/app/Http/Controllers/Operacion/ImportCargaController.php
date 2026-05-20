<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\ImportCargaRequest;
use App\Models\Empresa;
use App\Services\Import\ExternalCargaImporter;
use Carbon\CarbonImmutable;
use Throwable;
use Inertia\Inertia;

class ImportCargaController extends Controller
{
    public function index()
    {
        return Inertia::render('Operacion/Import/Carga', [
            'defaults' => [
                'since' => CarbonImmutable::now()->subDays(30)->toDateString(),
            ],
        ]);
    }

    public function store(ImportCargaRequest $request, ExternalCargaImporter $importer)
    {
        $empresa = Empresa::query()->orderBy('id')->firstOrFail();

        try {
            $result = $importer->importSince($empresa, $request->validated('since'));
            $request->session()->flash('tt.import_result', $result);
        } catch (Throwable $e) {
            $request->session()->flash('tt.import_error', $e->getMessage());
        }

        return redirect()->route('operacion.import.carga.index');
    }
}
