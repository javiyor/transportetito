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
        $rawLines = @file($path);
        if (! $rawLines) {
            return;
        }

        // Primera pasada: códigos de detalle para decidir alias .000 solo sin hijos
        $detailCodes = [];
        foreach ($rawLines as $line) {
            $line = $this->toUtf8($line);
            $line = trim($line, "\r\n");
            if (! preg_match('/^(\d+(\.\d+)+);/', $line, $mCod)) {
                continue;
            }
            $cols = str_getcsv($line, ';');
            $cols = array_pad($cols, 11, '');
            if (trim($cols[0]) === '' || trim($cols[5]) === '') {
                continue;
            }
            $detailCodes[trim($cols[0])] = true;
        }

        $lineNumber = 0;
        foreach ($rawLines as $line) {
            $lineNumber++;
            $line = $this->toUtf8($line);
            $line = trim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            // Niveles padre desde las secciones (CAPITULO / RUBRO / CTA.MADRE)
            if (preg_match('/^(CAPITULO|RUBRO|CTA\.MADRE):/', $line, $mSec)) {
                $cols = str_getcsv($line, ';');
                $codigoSec = '';
                foreach ($cols as $c) {
                    if (preg_match('/^\d+(\.\d+)*$/', trim($c))) {
                        $codigoSec = trim($c);
                        break;
                    }
                }
                // Los rubros vienen como 101/102...: normalizar a 1.01/1.02...
                if ($mSec[1] === 'RUBRO' && preg_match('/^(\d)(\d{2})$/', $codigoSec, $mRub)) {
                    $codigoSec = $mRub[1].'.'.$mRub[2];
                }
                $nombreSec = '';
                foreach (array_reverse($cols) as $c) {
                    if (trim($c) !== '') {
                        $nombreSec = trim($c);
                        break;
                    }
                }
                if ($codigoSec !== '' && $nombreSec !== '' && $nombreSec !== $codigoSec && $nombreSec !== ($cols[0] ?? '')) {
                    $nivelSec = $mSec[1] === 'CAPITULO' ? 'capitulo' : ($mSec[1] === 'RUBRO' ? 'rubro' : 'cuenta_madre');
                    $this->rows[$codigoSec] = [
                        'line' => $lineNumber,
                        'codigo_completo' => $codigoSec,
                        'codigo' => $codigoSec,
                        'codigo_corto' => null,
                        'nombre' => $nombreSec,
                        'tipo' => $this->tipoPorCodigo($codigoSec),
                        'naturaleza' => null,
                        'contabilizable' => false,
                        'nivel' => $nivelSec,
                    ];
                }
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

            // Hojas terminadas en .000 mapean a la cuenta de 4 niveles, SALVO que
            // tengan hijas en el archivo (grupo intermedio real: se conserva).
            $codigo = $codigoCompleto;
            $nivel = match (true) {
                $nivelCount === 1 => 'capitulo',
                $nivelCount === 2 => 'rubro',
                $nivelCount === 3 => 'cuenta_madre',
                $nivelCount === 4 => 'cuenta',
                default => 'subcuenta',
            };

            if ($nivelCount === 5 && end($parts) === '000' && ! $this->tieneHijas($codigoCompleto, $detailCodes)) {
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
    }

    private function toUtf8(string $line): string
    {
        // El export del sistema contable viene en Latin1: convertir a UTF-8
        if (! mb_check_encoding($line, 'UTF-8')) {
            $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1252');
        }

        return $line;
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

                // Si el codigo_corto ya existe en otra cuenta, importar igual pero sin corto
                // (el corto se usa para el mapeo de configuracion; no se pisa el existente).
                $codigoCorto = $row['codigo_corto'];
                if ($codigoCorto && CuentaContable::where('empresa_id', $empresa->id)
                    ->where('codigo_corto', $codigoCorto)
                    ->exists()) {
                    $this->report['conflicts'][] = [
                        'empresa' => $empresa->id,
                        'codigo' => $codigo,
                        'codigo_corto' => $codigoCorto,
                        'nombre' => $row['nombre'],
                        'motivo' => 'codigo_corto ya existe en otra cuenta: se importa sin corto',
                    ];
                    $codigoCorto = null;
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
                        'codigo_corto' => $codigoCorto,
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
                    'codigo_corto' => $codigoCorto,
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
     * Resuelve el padre usando el ancestro más profundo disponible (creados,
     * DB o filas del archivo). Los niveles intermedios sin nombre en el archivo
     * se saltean en lugar de crear "Cuenta autogenerada".
     *
     * @param array<string, CuentaContable> $created
     * @param array<string, object{id:int}> $placeholders
     * @return int|false|null
     */
    private function resolveParent(Empresa $empresa, string $codigo, array &$created, array &$placeholders): int|false|null
    {
        foreach ($this->ancestros($codigo) as $parentCode) {
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
                $created[$parentCode] = $parent;

                return $parent->id;
            }

            if (isset($this->rows[$parentCode])) {
                $id = $this->crearPadreDesdeFila($empresa, $parentCode, $created, $placeholders);
                if ($id !== false && $id !== null) {
                    return $id;
                }
            }
        }

        if ($this->option('no-crear-padres')) {
            return false;
        }

        // Último recurso: placeholder del padre inmediato
        $parts = explode('.', $codigo);
        array_pop($parts);
        if (empty($parts)) {
            return null;
        }

        return $this->crearPlaceholder($empresa, implode('.', $parts), $created, $placeholders);
    }

    /** @return list<string> ancestros del más profundo al más alto */
    private function ancestros(string $codigo): array
    {
        $parts = explode('.', $codigo);
        array_pop($parts);
        $out = [];
        while (count($parts) >= 1) {
            $out[] = implode('.', $parts);
            array_pop($parts);
        }

        return $out;
    }

    /**
     * @param array<string, CuentaContable> $created
     * @param array<string, object{id:int}> $placeholders
     * @return int|false|null
     */
    private function crearPadreDesdeFila(Empresa $empresa, string $parentCode, array &$created, array &$placeholders): int|false|null
    {
        $parentRow = $this->rows[$parentCode];
        $grandparentId = $this->resolveParent($empresa, $parentCode, $created, $placeholders);
        if ($grandparentId === false) {
            return false;
        }

        if ($this->option('dry-run')) {
            $fake = (object) ['id' => -(count($placeholders) + 1)];
            $placeholders[$parentCode] = $fake;

            return $fake->id;
        }

        $padre = CuentaContable::create([
            'empresa_id' => $empresa->id,
            'parent_id' => $grandparentId,
            'codigo' => $parentCode,
            'codigo_completo' => $parentRow['codigo_completo'],
            'codigo_corto' => null,
            'nombre' => $parentRow['nombre'],
            'tipo' => $parentRow['tipo'],
            'naturaleza' => $parentRow['naturaleza'],
            'nivel' => $parentRow['nivel'],
            'activo' => true,
            'contabilizable' => false,
            'orden' => 0,
        ]);
        $placeholders[$parentCode] = (object) ['id' => $padre->id];
        $created[$parentCode] = $padre;

        return $padre->id;
    }

    /**
     * @param array<string, CuentaContable> $created
     * @param array<string, object{id:int}> $placeholders
     * @return int|false|null
     */
    private function crearPlaceholder(Empresa $empresa, string $parentCode, array &$created, array &$placeholders): int|false|null
    {
        $grandparentId = $this->resolveParent($empresa, $parentCode, $created, $placeholders);
        if ($grandparentId === false) {
            return false;
        }

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
            'nombre' => "Cuenta autogenerada {$parentCode}",
            'tipo' => $this->tipoPorCodigo($parentCode),
            'naturaleza' => null,
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
        $this->warn('Importadas sin corto (corto en uso por otra cuenta): '.count($this->report['conflicts']));
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
