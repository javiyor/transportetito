<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\ImportCargaRequest;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Services\Import\ExternalCargaImporter;
use Carbon\CarbonImmutable;
use Throwable;
use Inertia\Inertia;

class ImportCargaController extends Controller
{
    public function index()
    {
        $empresa = Empresa::query()->findOrFail((int) request()->user()->current_empresa_id);

        return Inertia::render('Operacion/Import/Carga', [
            'defaults' => [
                'since' => CarbonImmutable::now()->subDays(30)->toDateString(),
            ],
            'empresa' => $empresa->only(['id', 'razon_social']),
            'depositos' => Deposito::query()
                ->where('empresa_id', $empresa->id)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ]);
    }

    public function store(ImportCargaRequest $request, ExternalCargaImporter $importer)
    {
        $empresa = Empresa::query()->findOrFail((int) $request->user()->current_empresa_id);
        $deposito = Deposito::query()->findOrFail((int) $request->validated('deposito_id'));
        abort_unless((int) $deposito->empresa_id === (int) $empresa->id, 422);

        try {
            $result = $importer->importSince($empresa, $deposito, $request->validated('since'));
            $request->session()->flash('tt.import_result', $result);
        } catch (Throwable $e) {
            $request->session()->flash('tt.import_error', $e->getMessage());
        }

        return redirect()->route('operacion.import.carga.index');
    }
}
