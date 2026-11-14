<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\PreRecibo;
use App\Services\Cobranzas\PreReciboConfirmer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PreReciboBulkConfirmController extends Controller
{
    public function __invoke(Request $request, PreReciboConfirmer $confirmer): RedirectResponse
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $data = $request->validate([
            'pre_recibo_ids' => ['required', 'array', 'min:1'],
            'pre_recibo_ids.*' => ['required', 'integer', 'distinct', 'exists:pre_recibos,id'],
        ]);
        $ids = array_map('intval', $data['pre_recibo_ids']);

        $preRecibos = PreRecibo::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->with(['items', 'aplicaciones'])
            ->lockForUpdate()
            ->get();

        if ($preRecibos->count() !== count($ids)) {
            return back()->with('flash.error', 'No se confirmó ningún pre-recibo: la selección contiene registros que no pertenecen a esta empresa.');
        }

        $errores = [];
        foreach ($preRecibos as $preRecibo) {
            $erroresPreRecibo = $confirmer->erroresValidacion($preRecibo);
            if ($erroresPreRecibo !== []) {
                $errores[] = '#'.$preRecibo->id.': '.implode(' ', $erroresPreRecibo);
            }
        }

        if ($errores !== []) {
            return back()->with('flash.error', 'No se confirmó ningún pre-recibo. Revisá: '.implode(' | ', $errores));
        }

        try {
            DB::transaction(function () use ($preRecibos, $confirmer, $request): void {
                foreach ($preRecibos as $preRecibo) {
                    $confirmer->confirm($preRecibo, (int) $request->user()->id);
                }
            });
        } catch (Throwable $exception) {
            Log::error('Falló la confirmación masiva de pre-recibos', [
                'empresa_id' => $empresaId,
                'pre_recibo_ids' => $ids,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('flash.error', 'No se confirmó el lote; ocurrió un error y se revirtió la operación. Revisá el log para más detalle.');
        }

        return back()->with('flash.success', count($ids).' pre-recibo(s) confirmados; se generaron sus recibos definitivos.');
    }
}
