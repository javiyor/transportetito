<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\TarifaRelacion;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Models\TerceroEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeduplicarTercerosPorCuit extends Command
{
    protected $signature = 'terceros:deduplicar-cuit {--cuit= : Procesar solo este CUIT} {--apply : Aplicar las fusiones; por defecto solo muestra una vista previa}';

    protected $description = 'Consolida terceros y cuentas duplicados por CUIT, conservando los registros más antiguos';

    public function handle(): int
    {
        $cuitFiltro = $this->option('cuit') !== null
            ? Tercero::soloDigitos((string) $this->option('cuit'))
            : null;

        if ($cuitFiltro !== null && strlen($cuitFiltro) !== 11) {
            $this->error('El filtro --cuit debe tener 11 dígitos.');

            return self::FAILURE;
        }

        $terceros = Tercero::query()->orderBy('id')->get();
        $cuentasPorTercero = TerceroCuenta::query()
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'tercero_id'])
            ->groupBy('tercero_id');
        $cuentasDuplicadasTotales = 0;
        foreach ($cuentasPorTercero as $terceroId => $cuentas) {
            $tercero = $terceros->firstWhere('id', $terceroId);
            if ($cuitFiltro !== null && Tercero::soloDigitos($tercero?->cuit) !== $cuitFiltro) {
                continue;
            }
            $cuentasDuplicadasTotales += $cuentas->groupBy('empresa_id')
                ->sum(fn (Collection $rows) => max(0, $rows->count() - 1));
        }

        $grupos = $terceros
            ->groupBy(fn (Tercero $tercero) => Tercero::soloDigitos($tercero->cuit))
            ->filter(function (Collection $grupo, $cuit) use ($cuitFiltro, $cuentasPorTercero) {
                $cuit = (string) $cuit;
                if (strlen($cuit) !== 11 || ($cuitFiltro !== null && $cuit !== $cuitFiltro)) {
                    return false;
                }

                $cuentas = collect($grupo->pluck('id')->all())
                    ->flatMap(fn ($id) => $cuentasPorTercero->get($id, collect()));
                $cuentasDuplicadas = $cuentas->groupBy('empresa_id')->contains(fn (Collection $rows) => $rows->count() > 1);
                $cuitSinNormalizar = (string) $grupo->first()->cuit !== $cuit;

                return $grupo->count() > 1 || $cuentasDuplicadas || $cuitSinNormalizar;
            })
            ->sortKeys();

        if ($grupos->isEmpty() && $cuentasDuplicadasTotales === 0) {
            $this->info('No se encontraron terceros ni cuentas duplicados para los CUIT consultados.');

            return self::SUCCESS;
        }

        $resumen = $grupos->map(function (Collection $grupo, $cuit) use ($cuentasPorTercero) {
            $cuit = (string) $cuit;
            $cuentas = collect($grupo->pluck('id')->all())
                ->flatMap(fn ($id) => $cuentasPorTercero->get($id, collect()));
            $cuentasAConsolidar = $cuentas->groupBy('empresa_id')->sum(fn (Collection $rows) => max(0, $rows->count() - 1));
            $tarifasColisionadas = $this->contarColisionesTarifas($grupo->pluck('id')->map(fn ($id) => (int) $id)->all(), (int) $grupo->first()->id);

            return [
                $cuit,
                (string) $grupo->first()->id,
                implode(', ', $grupo->skip(1)->pluck('id')->all()) ?: '—',
                (string) $cuentasAConsolidar,
                (string) $tarifasColisionadas,
            ];
        })->values()->all();

        if ($resumen !== []) {
            $this->table(['CUIT', 'Conservar tercero ID', 'Terceros a unir', 'Cuentas a consolidar', 'Tarifas que colisionan'], $resumen);
        }
        $this->info("Cuentas duplicadas por tercero y empresa (incluye CUIT no válido): {$cuentasDuplicadasTotales}");

        if (! $this->option('apply')) {
            $this->warn('Vista previa únicamente. Revise los grupos; agregue --apply para ejecutar las fusiones.');

            return self::SUCCESS;
        }

        $mapaCuentas = [];
        $colisionesTarifas = 0;

        DB::transaction(function () use ($grupos, &$mapaCuentas, &$colisionesTarifas): void {
            foreach ($grupos as $cuit => $grupo) {
                $cuit = (string) $cuit;
                $idsTerceros = $grupo->pluck('id')->map(fn ($id) => (int) $id)->all();
                $terceroPrincipal = Tercero::query()
                    ->whereKey(min($idsTerceros))
                    ->lockForUpdate()
                    ->firstOrFail();
                $duplicados = Tercero::query()
                    ->whereIn('id', $idsTerceros)
                    ->where('id', '!=', $terceroPrincipal->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $this->combinarDatosTercero($terceroPrincipal, $duplicados);

                $cuentasPorEmpresa = TerceroCuenta::query()
                    ->whereIn('tercero_id', $idsTerceros)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->groupBy('empresa_id');

                foreach ($cuentasPorEmpresa as $cuentas) {
                    /** @var TerceroCuenta $cuentaPrincipal */
                    $cuentaPrincipal = $cuentas->first();
                    $duplicadas = $cuentas->skip(1);

                    $this->combinarDatosCuenta($cuentaPrincipal, $duplicadas);
                    $cuentaPrincipal->tercero_id = $terceroPrincipal->id;
                    $cuentaPrincipal->save();

                    foreach ($duplicadas as $cuentaDuplicada) {
                        $this->moverRelacionesCuenta((int) $cuentaDuplicada->id, (int) $cuentaPrincipal->id);
                        $mapaCuentas[(int) $cuentaDuplicada->id] = (int) $cuentaPrincipal->id;
                        $cuentaDuplicada->delete();
                    }
                }

                foreach ($duplicados as $terceroDuplicado) {
                    DB::table('pedidos')->where('remitente_tercero_id', $terceroDuplicado->id)
                        ->update(['remitente_tercero_id' => $terceroPrincipal->id]);
                    DB::table('pedidos')->where('destinatario_tercero_id', $terceroDuplicado->id)
                        ->update(['destinatario_tercero_id' => $terceroPrincipal->id]);

                    if (Schema::hasTable('audit_logs')) {
                        DB::table('audit_logs')
                            ->where('subject_type', Tercero::class)
                            ->where('subject_id', $terceroDuplicado->id)
                            ->update(['subject_id' => $terceroPrincipal->id]);
                    }
                }

                $colisionesTarifas += $this->unificarTarifas($idsTerceros, (int) $terceroPrincipal->id);

                if ($duplicados->isNotEmpty()) {
                    Tercero::query()->whereIn('id', $duplicados->pluck('id'))->delete();
                }

                $terceroPrincipal->cuit = $cuit;
                $terceroPrincipal->save();
            }
        });

        $this->consolidarCuentasMismoTercero($mapaCuentas, $cuitFiltro);
        if ($mapaCuentas !== []) {
            $this->actualizarIdsEnDetallesComprobantes($mapaCuentas);
        }

        $this->info('Consolidación terminada; se conservó el tercero y la cuenta más antiguos por CUIT y empresa.');
        if ($colisionesTarifas > 0) {
            $this->warn("Se encontraron {$colisionesTarifas} tarifas duplicadas; se conservó la vinculada al tercero principal o, en su defecto, la más antigua.");
        }

        return self::SUCCESS;
    }

    private function combinarDatosTercero(Tercero $principal, Collection $duplicados): void
    {
        $domicilio = (array) ($principal->domicilio_fiscal ?? []);

        foreach ($duplicados as $duplicado) {
            foreach (['razon_social', 'condicion_iva', 'condicion_iva_id'] as $campo) {
                if ($this->vacio($principal->{$campo}) && ! $this->vacio($duplicado->{$campo})) {
                    $principal->{$campo} = $duplicado->{$campo};
                }
            }

            foreach ((array) ($duplicado->domicilio_fiscal ?? []) as $campo => $valor) {
                if ($this->vacio($domicilio[$campo] ?? null) && ! $this->vacio($valor)) {
                    $domicilio[$campo] = $valor;
                }
            }
        }

        $principal->domicilio_fiscal = $domicilio ?: null;
        $principal->save();
    }

    private function combinarDatosCuenta(TerceroCuenta $principal, Collection $duplicadas): void
    {
        $campos = [
            'nombre_cuenta', 'direccion', 'localidad', 'barrio', 'provincia_id', 'localidad_id',
            'zona_id', 'cp', 'telefono', 'email', 'whatsapp_numero', 'cobrador_user_id',
            'cuenta_contable_proveedor_id',
        ];

        foreach ($duplicadas as $duplicada) {
            foreach ($campos as $campo) {
                if ($this->vacio($principal->{$campo}) && ! $this->vacio($duplicada->{$campo})) {
                    $principal->{$campo} = $duplicada->{$campo};
                }
            }

            $principal->activo = (bool) $principal->activo || (bool) $duplicada->activo;
            $principal->enviar_comprobantes_por_email = (bool) $principal->enviar_comprobantes_por_email
                || (bool) $duplicada->enviar_comprobantes_por_email;
        }
    }

    /** @param array<int, int> $mapaCuentas */
    private function consolidarCuentasMismoTercero(array &$mapaCuentas, ?string $cuitFiltro = null): void
    {
        $cuentas = TerceroCuenta::query()
            ->orderBy('id')
            ->get();
        if ($cuitFiltro !== null) {
            $terceroIds = Tercero::query()->get(['id', 'cuit'])
                ->filter(fn (Tercero $tercero) => Tercero::soloDigitos($tercero->cuit) === $cuitFiltro)
                ->pluck('id')
                ->all();
            $cuentas = $cuentas->whereIn('tercero_id', $terceroIds);
        }

        $grupos = $cuentas
            ->groupBy(fn (TerceroCuenta $cuenta) => $cuenta->empresa_id.':'.$cuenta->tercero_id)
            ->filter(fn (Collection $cuentas) => $cuentas->count() > 1);

        foreach ($grupos as $cuentas) {
            $ids = $cuentas->pluck('id')->map(fn ($id) => (int) $id)->all();
            DB::transaction(function () use ($ids, &$mapaCuentas): void {
                $actuales = TerceroCuenta::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                if ($actuales->count() < 2) {
                    return;
                }

                /** @var TerceroCuenta $principal */
                $principal = $actuales->first();
                $duplicadas = $actuales->skip(1);
                $this->combinarDatosCuenta($principal, $duplicadas);
                $principal->save();

                foreach ($duplicadas as $duplicada) {
                    $this->moverRelacionesCuenta((int) $duplicada->id, (int) $principal->id);
                    $mapaCuentas[(int) $duplicada->id] = (int) $principal->id;
                    $duplicada->delete();
                }
            });
        }
    }

    private function moverRelacionesCuenta(int $cuentaOrigenId, int $cuentaDestinoId): void
    {
        $referencias = [
            'asiento_lineas' => ['tercero_cuenta_id'],
            'comprobantes' => ['facturar_cuenta_id', 'entrega_cuenta_id'],
            'cta_cte_movimientos' => ['tercero_cuenta_id'],
            'cotizaciones' => ['tercero_cuenta_id', 'tercero_destino_id'],
            'hoja_ruta_items' => ['entrega_cuenta_id'],
            'ordenes_pago' => ['tercero_cuenta_id'],
            'pedidos' => ['remitente_cuenta_id', 'destinatario_cuenta_id'],
            'pre_recibos' => ['tercero_cuenta_id'],
            'proveedor_comprobantes' => ['tercero_cuenta_id'],
            'recibos' => ['tercero_cuenta_id'],
        ];

        foreach ($referencias as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($columnas as $columna) {
                if (Schema::hasColumn($tabla, $columna)) {
                    DB::table($tabla)->where($columna, $cuentaOrigenId)->update([$columna => $cuentaDestinoId]);
                }
            }
        }

        $this->combinarRelacionEmpresa($cuentaOrigenId, $cuentaDestinoId);
    }

    private function combinarRelacionEmpresa(int $cuentaOrigenId, int $cuentaDestinoId): void
    {
        if (! Schema::hasTable('tercero_empresa')) {
            return;
        }

        $relaciones = TerceroEmpresa::query()->where('tercero_cuenta_id', $cuentaOrigenId)->get();
        foreach ($relaciones as $relacion) {
            $principal = TerceroEmpresa::query()
                ->where('empresa_id', $relacion->empresa_id)
                ->where('tercero_cuenta_id', $cuentaDestinoId)
                ->first();

            if ($principal) {
                $principal->es_cliente = (bool) $principal->es_cliente || (bool) $relacion->es_cliente;
                $principal->es_proveedor = (bool) $principal->es_proveedor || (bool) $relacion->es_proveedor;
                $principal->save();
                $relacion->delete();
            } else {
                $relacion->tercero_cuenta_id = $cuentaDestinoId;
                $relacion->save();
            }
        }
    }

    private function unificarTarifas(array $terceroIds, int $terceroPrincipalId): int
    {
        $tarifas = TarifaRelacion::query()
            ->where(function ($query) use ($terceroIds) {
                $query->whereIn('remitente_tercero_id', $terceroIds)
                    ->orWhereIn('destinatario_tercero_id', $terceroIds);
            })
            ->orderBy('id')
            ->get();

        $grupos = $tarifas->groupBy(function (TarifaRelacion $tarifa) use ($terceroIds, $terceroPrincipalId) {
            $remitenteId = in_array((int) $tarifa->remitente_tercero_id, $terceroIds, true)
                ? $terceroPrincipalId
                : $tarifa->remitente_tercero_id;
            $destinatarioId = in_array((int) $tarifa->destinatario_tercero_id, $terceroIds, true)
                ? $terceroPrincipalId
                : $tarifa->destinatario_tercero_id;

            return implode(':', [$tarifa->empresa_id, $remitenteId ?? 'null', $destinatarioId ?? 'null']);
        });

        $colisiones = 0;
        foreach ($grupos as $grupo) {
            $targetRemitente = in_array((int) $grupo->first()->remitente_tercero_id, $terceroIds, true)
                ? $terceroPrincipalId
                : $grupo->first()->remitente_tercero_id;
            $targetDestinatario = in_array((int) $grupo->first()->destinatario_tercero_id, $terceroIds, true)
                ? $terceroPrincipalId
                : $grupo->first()->destinatario_tercero_id;

            $principal = $grupo->first(fn (TarifaRelacion $tarifa) =>
                (int) ($tarifa->remitente_tercero_id ?? 0) === (int) ($targetRemitente ?? 0)
                && (int) ($tarifa->destinatario_tercero_id ?? 0) === (int) ($targetDestinatario ?? 0)
            ) ?? $grupo->first();

            if ($grupo->count() > 1) {
                $colisiones += $grupo->count() - 1;
                TarifaRelacion::query()->whereIn('id', $grupo->pluck('id')->reject(fn ($id) => (int) $id === (int) $principal->id))->delete();
            }

            $principal->remitente_tercero_id = $targetRemitente;
            $principal->destinatario_tercero_id = $targetDestinatario;
            $principal->save();
        }

        return $colisiones;
    }

    private function contarColisionesTarifas(array $terceroIds, int $terceroPrincipalId): int
    {
        return TarifaRelacion::query()
            ->where(function ($query) use ($terceroIds) {
                $query->whereIn('remitente_tercero_id', $terceroIds)
                    ->orWhereIn('destinatario_tercero_id', $terceroIds);
            })
            ->get()
            ->groupBy(function (TarifaRelacion $tarifa) use ($terceroIds, $terceroPrincipalId) {
                $remitenteId = in_array((int) $tarifa->remitente_tercero_id, $terceroIds, true)
                    ? $terceroPrincipalId
                    : $tarifa->remitente_tercero_id;
                $destinatarioId = in_array((int) $tarifa->destinatario_tercero_id, $terceroIds, true)
                    ? $terceroPrincipalId
                    : $tarifa->destinatario_tercero_id;

                return implode(':', [$tarifa->empresa_id, $remitenteId ?? 'null', $destinatarioId ?? 'null']);
            })
            ->sum(fn (Collection $grupo) => max(0, $grupo->count() - 1));
    }

    /** @param array<int, int> $mapaCuentas */
    private function actualizarIdsEnDetallesComprobantes(array $mapaCuentas): void
    {
        Comprobante::query()->whereNotNull('detalle_facturacion')->chunkById(250, function (Collection $comprobantes) use ($mapaCuentas): void {
            foreach ($comprobantes as $comprobante) {
                $detalle = $comprobante->detalle_facturacion;
                if (! is_array($detalle)) {
                    continue;
                }

                $cambio = false;
                foreach (['origen_cuenta_id', 'destino_cuenta_id', 'facturar_cuenta_id', 'entrega_cuenta_id'] as $campo) {
                    $cuentaId = (int) ($detalle[$campo] ?? 0);
                    if ($cuentaId > 0 && isset($mapaCuentas[$cuentaId])) {
                        $detalle[$campo] = $mapaCuentas[$cuentaId];
                        $cambio = true;
                    }
                }

                if ($cambio) {
                    $comprobante->forceFill(['detalle_facturacion' => $detalle])->saveQuietly();
                }
            }
        });
    }

    private function vacio(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
