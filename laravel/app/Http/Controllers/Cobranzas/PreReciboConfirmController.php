<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\PreRecibo;
use App\Services\Cobranzas\PreReciboConfirmer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreReciboConfirmController extends Controller
{
    public function __invoke(Request $request, PreRecibo $preRecibo, PreReciboConfirmer $confirmer): RedirectResponse
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless((int) $preRecibo->empresa_id === $empresaId, 404);

        if ($preRecibo->estado === 'confirmado') {
            return redirect()->route('cobranzas.pre-recibos.show', $preRecibo);
        }

        $recibo = $confirmer->confirm($preRecibo, (int) $request->user()->id);

        $confirmer->sendEmail($recibo);

        return redirect()
            ->route('cobranzas.pre-recibos.show', $preRecibo)
            ->with('success', 'Pre-recibo confirmado.');
    }
}
