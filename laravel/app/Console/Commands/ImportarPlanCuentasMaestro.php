<?php

namespace App\Console\Commands;

use App\Models\CuentaContable;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarPlanCuentasMaestro extends Command
{
    protected $signature = 'plan-cuentas:importar-maestro
        {archivo? : Ruta al archivo CSV/TXT con el plan maestro (por defecto storage/app/plan_maestro_cuentas.txt)}
        {--empresa_id= : ID de empresa a importar (por defecto todas)}
        {--dry-run : Solo mostrar vista previa, no insertar}
        {--sobrescribir-nombres : Actualizar nombre de cuentas existentes si difieren}
        {--no-crear-padres : No crear niveles padre faltantes automaticamente}';

    protected $description = 'Importa cuentas contables desde un plan maestro (solo agrega, no borra).';

    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, mixed> */
    private array $report = [
        'added' => [],
        'existing' => [],
        'updated' => [],
        'conflicts' => [],
        'errors' => [],
    ];

    public function handle(): int
    {
        $path = $this->argument('archivo') ?: storage_path('app/plan_maestro_cuentas.txt');

        if (! file_exists($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $this->info("Leyendo plan maestro: {$path}");
        $this->parseFile($path);

        if (empty($this->rows)) {
            $this->error('No se encontraron filas validas.');

            return self::FAILURE;
        }

        $this->info('Filas parseadas: '.count($this->rows));

        $empresas = $this->option('empresa_id')
            ? Empresa::where('id', $this->option('empresa_id'))->get()
            : Empresa::all();

        if ($empresas->isEmpty()) {
            $this->error('No se encontraron empresas.');

            return self::FAILURE;
        }

        foreach ($empresas as $empresa) {
            $this->importForEmpresa($empresa);
        }

        $this->printReport();

        return self::SUCCESS;
    }

    private function parseFile(string $path): void
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return;
        }

        $lineNumber = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            // Skip page/section headers and non-account lines
            if (! preg_match('/^\d+(\.\d+)+/', $line)) {
                continue;
            }

            $cols = str_getcsv($line, ';');
            $cols = array_pad($cols, 11, '');

            $codigoCompleto = trim($cols[0]);
            $codigoCorto = trim($cols[4]);
            $nombre = trim($cols[5]);
            $naturalezaRaw = strtolower(trim($cols[6]));
            $movRaw = strtolower(trim($cols[7]));

            if ($codigoCompleto === '' || $nombre === '') {
                continue;
            }

            $naturaleza = in_array($naturalezaRaw, ['deudor', 'acreedor'], true) ? $naturalezaRaw : null;
            $contabilizable = $movRaw === 'si';

            $parts = explode('.', $codigoCompleto);
            $nivelCount = count($parts);

            // Leaves ending in .000 map to the 4-level cuenta.
            $codigo = $codigoCompleto;
            $nivel = match (true) {
                $nivelCount === 1 => 'capitulo',
                $nivelCount === 2 => 'rubro',
                $nivelCount === 3 => 'cuenta_madre',
                $nivelCount === 4 => 'cuenta',
                default => 'subcuenta',
            };

            if ($nivelCount === 5 && end($parts) === '000') {
                array_pop($parts);
                $codigo = implode('.', $parts);
                $nivel = 'cuenta';
                $codigoCompleto = $codigo;
            }

            $this->rows[$codigo] = [
                'line' => $lineNumber,
                'codigo_completo' => $codigoCompleto,
                'codigo' => $codigo,
                'codigo_corto' => $codigoCorto !== '' ? $codigoCorto : null,
                'nombre' => $nombre,
                'tipo' => $this->tipoPorCodigo($codigo),
                'naturaleza' => $naturaleza,
                'contabilizable' => $contabilizable,
                'nivel' => $nivel,
            ];
        }

        fclose($handle);
    }

    private function tipoPorCodigo(string $codigo): string
    {
        $first = explode('.', $codigo)[0] ?? '';

        return match ($first) {
            '1' => 'activo',
            '2' => 'pasivo',
            '3' => 'patrimonio_neto',
            '4' => 'ingreso',
            '5' => 'egreso',
            default => 'activo',
        };
    }

    private function importForEmpresa(Empresa $empresa): void
    {
        $this->newLine();
        $this->info("Procesando empresa {$empresa->id}: {$empresa->nombre}");

        // Sort by segment count ascending to create parents first.
        $rows = $this->rows;
        uasort($rows, static function (array $a, array $b): int {
            return count(explode('.', $a['codigo'])) <=> count(explode('.', $b['codigo']));
        });

        /** @var array<string, CuentaContable> $created */
        $created = [];

        /** @var array<string, object{id:int}> $placeholders */
        $placeholders = [];

        $method = $this->option('dry-run') ? 'rollBack' : 'commit';
        DB::beginTransaction();

        try {
            foreach ($rows as $codigo => $row) {
                $existing = CuentaContable::where('empresa_id', $empresa->id)
                    ->where('codigo', $codigo)
                    ->first();

                if ($existing) {
                    if ($this->option('sobrescribir-nombres') && $existing->nombre !== $row['nombre']) {
                        if (! $this->option('dry-run')) {
                            $existing->update(['nombre' => $row['nombre']]);
                        }
                        $this->report['updated'][] = ['empresa' => $empresa->id, 'codigo' => $codigo, 'nombre' => $row['nombre']];
                    } else {
                        $this->report['existing'][] = ['empresa' => $empresa->id, 'codigo' => $codigo, 'nombre' => $row['nombre']];
                    }
                    $created[$codigo] = $existing;

                    continue;
                }

                // Detect short-code collisions as potential duplicates.
                if ($row['codigo_corto'] && CuentaContable::where('empresa_id', $empresa->id)
                    ->where('codigo_corto', $row['codigo_corto'])
                    ->exists()) {
                    $this->report['conflicts'][] = [
                        'empresa' => $empresa->id,
                        'codigo' => $codigo,
                        'codigo_corto' => $row['codigo_corto'],
                        'nombre' => $row['nombre'],
                        'motivo' => 'codigo_corto ya existe en otra cuenta',
                    ];

                    continue;
                }

                $parentId = $this->resolveParent($empresa, $codigo, $created, $placeholders);

                if ($parentId === false) {
                    $this->report['errors'][] = ['empresa' => $empresa->id, 'codigo' => $codigo, 'motivo' => 'No se pudo resolver padre'];

                    continue;
                }

                if (! $this->option('dry-run')) {
                    $account = CuentaContable::create([
                        'empresa_id' => $empresa->id,
                        'parent_id' => $parentId,
                        'codigo' => $codigo,
                        'codigo_completo' => $row['codigo_completo'],
                        'codigo_corto' => $row['codigo_corto'],
                        'nombre' => $row['nombre'],
                        'tipo' => $row['tipo'],
                        'naturaleza' => $row['naturaleza'],
                        'nivel' => $row['nivel'],
                        'activo' => true,
                        'contabilizable' => $row['contabilizable'],
                        'orden' => 0,
                    ]);
                    $created[$codigo] = $account;
                }

                $this->report['added'][] = [
                    'empresa' => $empresa->id,
                    'codigo' => $codigo,
                    'codigo_corto' => $row['codigo_corto'],
                    'nombre' => $row['nombre'],
                    'nivel' => $row['nivel'],
                ];
            }

            DB::$method();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, CuentaContable> $created
     * @param array<string, object{id:int}> $placeholders
     * @return int|false|null
     */
    private function resolveParent(Empresa $empresa, string $codigo, array &$created, array &$placeholders): int|false|null
    {
        $parts = explode('.', $codigo);
        if (count($parts) <= 1) {
            return null;
        }

        array_pop($parts);
        $parentCode = implode('.', $parts);

        if (isset($created[$parentCode])) {
            return $created[$parentCode]->id;
        }

        if (isset($placeholders[$parentCode])) {
            return $placeholders[$parentCode]->id;
        }

        $parent = CuentaContable::where('empresa_id', $empresa->id)
            ->where('codigo', $parentCode)
            ->first();

        if ($parent) {
            return $parent->id;
        }

        if ($this->option('no-crear-padres')) {
            return false;
        }

        $parentRow = $this->rows[$parentCode] ?? null;
        $grandparentId = $this->resolveParent($empresa, $parentCode, $created, $placeholders);
        if ($grandparentId === false) {
            return false;
        }

        $nombre = $parentRow['nombre'] ?? "Cuenta autogenerada {$parentCode}";
        $naturaleza = $parentRow['naturaleza'] ?? null;
        $tipo = $parentRow['tipo'] ?? $this->tipoPorCodigo($parentCode);

        if ($this->option('dry-run')) {
            $fake = (object) ['id' => -(count($placeholders) + 1)];
            $placeholders[$parentCode] = $fake;

            return $fake->id;
        }

        $placeholder = CuentaContable::create([
            'empresa_id' => $empresa->id,
            'parent_id' => $grandparentId,
            'codigo' => $parentCode,
            'codigo_completo' => $parentCode,
            'codigo_corto' => null,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'naturaleza' => $naturaleza,
            'nivel' => $this->nivelPorCodigo($parentCode),
            'activo' => true,
            'contabilizable' => false,
            'orden' => 0,
        ]);
        $placeholders[$parentCode] = (object) ['id' => $placeholder->id];
        $created[$parentCode] = $placeholder;

        return $placeholder->id;
    }

    private function nivelPorCodigo(string $codigo): string
    {
        return match (count(explode('.', $codigo))) {
            1 => 'capitulo',
            2 => 'rubro',
            3 => 'cuenta_madre',
            4 => 'cuenta',
            default => 'subcuenta',
        };
    }

    private function printReport(): void
    {
        $this->newLine();
        $this->info('=== RESUMEN ===');
        $this->info('Agregadas: '.count($this->report['added']));
        $this->info('Existentes (sin cambios): '.count($this->report['existing']));
        $this->info('Actualizadas (nombre): '.count($this->report['updated']));
        $this->warn('Conflictos (codigo_corto duplicado): '.count($this->report['conflicts']));
        $this->error('Errores: '.count($this->report['errors']));

        if (! empty($this->report['conflicts'])) {
            $this->newLine();
            $this->warn('Primeros conflictos:');
            foreach (array_slice($this->report['conflicts'], 0, 10) as $c) {
                $this->warn("  - [{$c['codigo']}] {$c['nombre']} (corto {$c['codigo_corto']})");
            }
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('Este fue un DRY-RUN. No se modifico la base de datos.');
        }
    }
}
