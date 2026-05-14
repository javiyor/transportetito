<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\StorePedidoRequest;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use App\Models\Tercero;

class PedidoStoreController extends Controller
{
    public function __invoke(StorePedidoRequest $request, ManifiestoIngreso $manifiesto)
    {
        $data = $request->validated();

        $remitente = Tercero::query()->firstOrCreate(
            ['cuit' => $data['remitente']['cuit']],
            ['razon_social' => $data['remitente']['razon_social']]
        );

        $destinatario = Tercero::query()->firstOrCreate(
            ['cuit' => $data['destinatario']['cuit']],
            ['razon_social' => $data['destinatario']['razon_social']]
        );

        Pedido::create([
            'empresa_id' => $manifiesto->empresa_id,
            'deposito_id' => $manifiesto->deposito_id,
            'manifiesto_ingreso_id' => $manifiesto->id,
            'remitente_tercero_id' => $remitente->id,
            'destinatario_tercero_id' => $destinatario->id,
            'paga' => $data['paga'],
            'remito_numero' => $data['remito_numero'] ?? null,
            'bultos' => $data['bultos'],
            'palets' => $data['palets'],
            'valor_declarado' => $data['valor_declarado'],
            'es_devolucion' => (bool) ($data['es_devolucion'] ?? false),
            'cr_importe' => $data['cr_importe'] ?? null,
            'estado' => 'en_deposito',
        ]);

        return redirect()->route('operacion.manifiestos.show', $manifiesto);
    }
}
