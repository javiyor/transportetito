<?php

namespace Tests\Feature;

use App\Models\Comprobante;
use App\Services\Cobranzas\SaldoNotasCreditoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaldoNotasCreditoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_note_excess_becomes_available_after_source_invoice_payments(): void
    {
        $factura = new Comprobante;
        $factura->setRawAttributes([
            'id' => 101,
            'tipo' => 'factura_interna',
            'estado' => 'emitida',
            'total' => '100.00',
            'fecha_emision' => '2026-11-01',
        ]);

        $notaCredito = new Comprobante;
        $notaCredito->setRawAttributes([
            'id' => 102,
            'tipo' => 'nota_credito_interna',
            'estado' => 'emitida',
            'total' => '-50.00',
            'fecha_emision' => '2026-11-10',
            'comprobante_origen_id' => 101,
        ]);

        $saldos = app(SaldoNotasCreditoService::class)->calcular(collect([$factura, $notaCredito]), [101 => 80]);

        $this->assertSame(0.0, $saldos[101]['pendiente']);
        $this->assertTrue($saldos[102]['is_credit']);
        $this->assertSame(-30.0, $saldos[102]['pendiente']);
    }

    public function test_credit_note_is_fully_consumed_when_source_invoice_has_enough_unpaid_balance(): void
    {
        $factura = new Comprobante;
        $factura->setRawAttributes([
            'id' => 201,
            'tipo' => 'factura_interna',
            'estado' => 'emitida',
            'total' => '100.00',
            'fecha_emision' => '2026-11-01',
        ]);

        $notaCredito = new Comprobante;
        $notaCredito->setRawAttributes([
            'id' => 202,
            'tipo' => 'nota_credito_interna',
            'estado' => 'emitida',
            'total' => '-20.00',
            'fecha_emision' => '2026-11-10',
            'comprobante_origen_id' => 201,
        ]);

        $saldos = app(SaldoNotasCreditoService::class)->calcular(collect([$factura, $notaCredito]), [201 => 50]);

        $this->assertSame(30.0, $saldos[201]['pendiente']);
        $this->assertSame(0.0, $saldos[202]['pendiente']);
    }
}
