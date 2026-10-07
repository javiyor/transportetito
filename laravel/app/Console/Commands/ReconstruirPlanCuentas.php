<?php

namespace App\Console\Commands;

use App\Models\CuentaContable;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Borra el plan de cuentas de una o todas las empresas y lo recrea desde el CSV,
 * remapeando referencias por codigo y regenerando asientos. DESTRUCTIVO:
 * se pierden asientos manuales y pagos de pasivos (no regenerables).
 */
class ReconstruirPlanCuentas extends Command
{
    protected $signature = 'plan-cuentas:reconstruir
        {archivo? : Ruta al CSV del plan (por defecto storage/app/plan_maestro_cuentas.csv)}
        {--empresa_id= : ID de empresa (por defecto todas)}
        {--force : Ejecuta el borrado y reconstruccion (sin esto solo informa)}
        {--no-recontabilizar : No regenera asientos al final}';

    protected $description = 'Reconstruye planes de cuenta desde cero a partir del CSV (borra todo lo anterior).';

    /** @var array<string, true> codigos presentes en el CSV */
    private array $codigosCsv = [];

    public function handle(): int
    {
        $path = $this->argument('archivo') ?: storage_path('app/plan_maestro_cuentas.csv');

        if (! file_exists($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $this->cargarCodigosCsv($path);
        $this->info('Codigos en CSV: '.count($this->codigosCsv));

        $empresas = $this->option('empresa_id')
            ? Empresa::where('id', $this->option('empresa_id'))->get()
            : Empresa::all();

        if ($empresas->isEmpty()) {
            $this->error('No se encontraron empresas.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        foreach ($empresas as $empresa) {
            $this->reportarEmpresa($empresa);
        }

        if (! $force) {
            $this->newLine();
            $this->warn('Sin --force solo se informa. Los asientos MANUALES y PAGOS DE PASIVOS se perderan al reconstruir.');

            return self::SUCCESS;
        }

        foreach ($empresas as $empresa) {
            $this->reconstruirEmpresa($empresa, $path);
        }

        $this->newLine();
        $this->info('Re-sembrando configuracion contable...');
        Artisan::call('db:seed', ['--class' => 'ConfiguracionContableSeeder', '--force' => true]);
        $this->line(Artisan::output());

        if (! $this->option('no-recontabilizar')) {
            $this->newLine();
            $this->info('Recontabilizando todo...');
            Artisan::call('contabilidad:recontabilizar', ['--tipo' => 'todos', '--force' => true]);
            $this->line(Artisan::output());
        }

        $this->newLine();
        $this->info('Reconstruccion completa.');

        return self::SUCCESS;
    }

    private function reportarEmpresa(Empresa $empresa): void
    {
        $eid = $empresa->id;
        $nCuentas = CuentaContable::where('empresa_id', $eid)->count();
        $asientos = DB::table('asientos_contables')->where('empresa_id', $eid);
        $nAsientos = (clone $asientos)->count();
        $nManuales = (clone $asientos)->where('referencia_tipo', 'manual')->count();
        $nPagoPasivo = (clone $asientos)->where('referencia_tipo', 'pago_pasivo')->count();
        $nCats = DB::table('gasto_operativo_categorias as c')
            ->join('gastos_operativos as g', 'g.id', '=', 'c.gasto_operativo_id')
            ->where('g.empresa_id', $eid)->count()
            + DB::table('ingreso_operativo_categorias as c')
            ->join('ingresos_operativos as i', 'i.id', '=', 'c.ingreso_operativo_id')
            ->where('i.empresa_id', $eid)->count();

        $this->newLine();
        $this->info("Empresa {$eid}: {$empresa->razon_social}");
        $this->line("  cuentas: {$nCuentas} | asientos: {$nAsientos} (manuales: {$nManuales}, pago_pasivo: {$nPagoPasivo}) | distribuciones: {$nCats}");

        $faltantes = $this->codigosReferenciadosFaltantes($eid);
        if (! empty($faltantes)) {
            $this->error('  Codigos referenciados que NO estan en el CSV ('.count($faltantes).'): '.implode(', ', array_slice($faltantes, 0, 15)));
        }
    }

    /** Codigos usados por documentos que no existen en el CSV */
    private function codigosReferenciadosFaltantes(int $empresaId): array
    {
        $usados = [];
        $map = CuentaContable::where('empresa_id', $empresaId)->pluck('codigo', 'id');

        $tablas = [
            ['gasto_operativo_categorias', 'cuenta_contable_id', null],
            ['ingreso_operativo_categorias', 'cuenta_contable_id', null],
            ['proveedor_comprobantes', 'cuenta_contable_id', 'empresa_id'],
            ['tercero_cuentas', 'cuenta_contable_proveedor_id', 'empresa_id'],
        ];

        foreach ($tablas as [$tabla, $col, $empCol]) {
            $q = DB::table($tabla)->whereNotNull($col);
            if ($empCol) {
                $q->where($empCol, $empresaId);
            } else {
                // categorias: filtrar por empresa del documento padre
                $parent = str_contains($tabla, 'gasto') ? 'gastos_operativos' : 'ingresos_operativos';
                $fk = str_contains($tabla, 'gasto') ? 'gasto_operativo_id' : 'ingreso_operativo_id';
                $q->join("{$parent} as p", "p.id", '=', "{$tabla}.{$fk}")->where('p.empresa_id', $empresaId);
            }
            foreach ($q->pluck($col)->unique() as $id) {
                $cod = $map[$id] ?? null;
                if ($cod && ! isset($this->codigosCsv[$cod])) {
                    $usados[$cod] = true;
                }
            }
        }

        return array_keys($usados);
    }

    private function reconstruirEmpresa(Empresa $empresa, string $path): void
    {
        $eid = $empresa->id;
        $this->newLine();
        $this->info("Reconstruyendo empresa {$eid}: {$empresa->razon_social}");

        $faltantes = $this->codigosReferenciadosFaltantes($eid);
        if (! empty($faltantes)) {
            throw new \RuntimeException('Empresa '.$eid.': codigos referenciados fuera del CSV: '.implode(', ', $faltantes));
        }

        DB::transaction(function () use ($empresa, $eid, $path) {
            // Mapa id => codigo actual
            $map = CuentaContable::where('empresa_id', $eid)->pluck('codigo', 'id');

            // Snapshot distribuciones (columna NOT NULL: se borran y recrean)
            $gastoCats = DB::table('gasto_operativo_categorias as c')
                ->join('gastos_operativos as g', 'g.id', '=', 'c.gasto_operativo_id')
                ->where('g.empresa_id', $eid)
                ->get(['c.id', 'c.gasto_operativo_id', 'c.cuenta_contable_id', 'c.importe'])
                ->map(fn ($r) => ['id' => $r->id, 'gasto_operativo_id' => $r->gasto_operativo_id, 'codigo' => $map[$r->cuenta_contable_id] ?? null, 'importe' => $r->importe]);
            $ingresoCats = DB::table('ingreso_operativo_categorias as c')
                ->join('ingresos_operativos as i', 'i.id', '=', 'c.ingreso_operativo_id')
                ->where('i.empresa_id', $eid)
                ->get(['c.id', 'c.ingreso_operativo_id', 'c.cuenta_contable_id', 'c.importe'])
                ->map(fn ($r) => ['id' => $r->id, 'ingreso_operativo_id' => $r->ingreso_operativo_id, 'codigo' => $map[$r->cuenta_contable_id] ?? null, 'importe' => $r->importe]);

            // Snapshot refs nulables
            $provComp = DB::table('proveedor_comprobantes')->where('empresa_id', $eid)->whereNotNull('cuenta_contable_id')->get(['id', 'cuenta_contable_id']);
            $terceroCtas = DB::table('tercero_cuentas')->where('empresa_id', $eid)->whereNotNull('cuenta_contable_proveedor_id')->get(['id', 'cuenta_contable_proveedor_id']);

            $nAsientos = DB::table('asientos_contables')->where('empresa_id', $eid)->count();

            // Borrar: lineas+asientos, config, distribuciones
            $asientoIds = DB::table('asientos_contables')->where('empresa_id', $eid)->pluck('id');
            DB::table('asiento_lineas')->whereIn('asiento_id', $asientoIds)->delete();
            DB::table('asientos_contables')->where('empresa_id', $eid)->delete();
            DB::table('configuracion_contable')->where('empresa_id', $eid)->delete();
            DB::table('gasto_operativo_categorias')->whereIn('id', $gastoCats->pluck('id'))->delete();
            DB::table('ingreso_operativo_categorias')->whereIn('id', $ingresoCats->pluck('id'))->delete();
            DB::table('proveedor_comprobantes')->where('empresa_id', $eid)->update(['cuenta_contable_id' => null]);
            DB::table('tercero_cuentas')->where('empresa_id', $eid)->update(['cuenta_contable_proveedor_id' => null]);

            // Borrar plan (cascada de parent_id lo resuelve en cualquier orden)
            CuentaContable::where('empresa_id', $eid)->delete();

            $this->line("  eliminados: {$nAsientos} asientos, plan anterior + config. Distribuciones a remapear: ".($gastoCats->count() + $ingresoCats->count()));

            // Reimportar
            Artisan::call('plan-cuentas:importar-maestro', [
                'archivo' => $path,
                '--empresa_id' => $eid,
            ]);
            $this->line(Artisan::output());

            $nuevos = CuentaContable::where('empresa_id', $eid)->pluck('id', 'codigo');

            // Recrear distribuciones con nuevos ids
            foreach ($gastoCats as $cat) {
                $nid = $nuevos[$cat['codigo']] ?? null;
                if (! $nid) {
                    throw new \RuntimeException("Empresa {$eid}: sin cuenta nueva para codigo {$cat['codigo']} (gasto cat #{$cat['id']})");
                }
                DB::table('gasto_operativo_categorias')->insert([
                    'gasto_operativo_id' => $cat['gasto_operativo_id'],
                    'cuenta_contable_id' => $nid,
                    'importe' => $cat['importe'],
                ]);
            }
            foreach ($ingresoCats as $cat) {
                $nid = $nuevos[$cat['codigo']] ?? null;
                if (! $nid) {
                    throw new \RuntimeException("Empresa {$eid}: sin cuenta nueva para codigo {$cat['codigo']} (ingreso cat #{$cat['id']})");
                }
                DB::table('ingreso_operativo_categorias')->insert([
                    'ingreso_operativo_id' => $cat['ingreso_operativo_id'],
                    'cuenta_contable_id' => $nid,
                    'importe' => $cat['importe'],
                ]);
            }

            // Remapear refs nulables
            foreach ($provComp as $row) {
                $cod = $map[$row->cuenta_contable_id] ?? null;
                if ($cod && isset($nuevos[$cod])) {
                    DB::table('proveedor_comprobantes')->where('id', $row->id)->update(['cuenta_contable_id' => $nuevos[$cod]]);
                }
            }
            foreach ($terceroCtas as $row) {
                $cod = $map[$row->cuenta_contable_proveedor_id] ?? null;
                if ($cod && isset($nuevos[$cod])) {
                    DB::table('tercero_cuentas')->where('id', $row->id)->update(['cuenta_contable_proveedor_id' => $nuevos[$cod]]);
                }
            }

            $this->line('  plan recreado: '.$nuevos->count().' cuentas. Referencias remapeadas por codigo.');
        });
    }

    private function cargarCodigosCsv(string $path): void
    {
        $rawLines = @file($path);
        if (! $rawLines) {
            return;
        }

        $norm = function ($line) {
            if (! mb_check_encoding($line, 'UTF-8')) {
                $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1252');
            }

            return trim($line, "\r\n");
        };

        // Primera pasada: codigos de detalle (para alias .000 solo sin hijas)
        $detailCodes = [];
        foreach ($rawLines as $line) {
            $line = $norm($line);
            if (! preg_match('/^(\d+(\.\d+)+);/', $line)) {
                continue;
            }
            $cols = str_getcsv($line, ';');
            $cols = array_pad($cols, 11, '');
            if (trim($cols[0]) === '' || trim($cols[5]) === '') {
                continue;
            }
            $detailCodes[trim($cols[0])] = true;
        }

        foreach ($rawLines as $line) {
            $line = $norm($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(CAPITULO|RUBRO|CTA\.MADRE):/', $line, $mSec)) {
                $cols = str_getcsv($line, ';');
                foreach ($cols as $c) {
                    if (preg_match('/^\d+(\.\d+)*$/', trim($c))) {
                        $cod = trim($c);
                        if ($mSec[1] === 'RUBRO' && preg_match('/^(\d)(\d{2})$/', $cod, $mRub)) {
                            $cod = $mRub[1].'.'.$mRub[2];
                        }
                        $this->codigosCsv[$cod] = true;
                        break;
                    }
                }
                continue;
            }
            if (! preg_match('/^(\d+(\.\d+)+);/', $line)) {
                continue;
            }
            $cols = str_getcsv($line, ';');
            $cols = array_pad($cols, 11, '');
            if (trim($cols[0]) === '' || trim($cols[5]) === '') {
                continue;
            }
            $codigo = trim($cols[0]);
            $parts = explode('.', $codigo);
            if (count($parts) === 5 && end($parts) === '000' && ! $this->tieneHijas($codigo, $detailCodes)) {
                array_pop($parts);
                $codigo = implode('.', $parts);
            }
            $this->codigosCsv[$codigo] = true;
        }
    }

    private function tieneHijas(string $codigo, array $detailCodes): bool
    {
        $prefix = $codigo.'.';
        foreach ($detailCodes as $otro => $_) {
            if ($otro !== $codigo && str_starts_with($otro, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
