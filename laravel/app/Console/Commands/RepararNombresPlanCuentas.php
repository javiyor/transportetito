<?php

namespace App\Console\Commands;

use App\Models\CuentaContable;
use App\Models\Empresa;
use Illuminate\Console\Command;

class RepararNombresPlanCuentas extends Command
{
    protected $signature = 'plan-cuentas:reparar-nombres
        {archivo? : Ruta al CSV del plan (por defecto storage/app/plan_maestro_cuentas.csv)}
        {--empresa_id= : ID de empresa (por defecto todas)}
        {--dry-run : Solo mostrar vista previa, no modificar}';

    protected $description = 'Pone los nombres del CSV a las cuentas "Cuenta autogenerada" cuyo codigo tiene seccion.';

    /** @var array<string, string> codigo => nombre de seccion */
    private array $secciones = [];

    public function handle(): int
    {
        $path = $this->argument('archivo') ?: storage_path('app/plan_maestro_cuentas.csv');

        if (! file_exists($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $this->parseSecciones($path);
        $this->info('Secciones en archivo: '.count($this->secciones));

        $empresas = $this->option('empresa_id')
            ? Empresa::where('id', $this->option('empresa_id'))->get()
            : Empresa::all();

        $renombradas = 0;
        $sinNombre = [];

        foreach ($empresas as $empresa) {
            $autos = CuentaContable::query()
                ->where('empresa_id', $empresa->id)
                ->where('nombre', 'like', 'Cuenta autogenerada%')
                ->get();

            foreach ($autos as $cuenta) {
                $nombre = $this->secciones[$cuenta->codigo] ?? null;
                if ($nombre) {
                    $this->line("  [{$empresa->id}] {$cuenta->codigo}: '{$cuenta->nombre}' -> '{$nombre}'");
                    if (! $this->option('dry-run')) {
                        $cuenta->update(['nombre' => $nombre]);
                    }
                    $renombradas++;
                } else {
                    $sinNombre[] = "[{$empresa->id}] {$cuenta->codigo}";
                }
            }
        }

        $this->newLine();
        $this->info('Renombradas: '.$renombradas);
        if (! empty($sinNombre)) {
            $this->warn('Sin nombre en archivo ('.count($sinNombre).'): '.implode(', ', array_slice($sinNombre, 0, 20)));
        }
        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: no se modifico la base.');
        }

        return self::SUCCESS;
    }

    private function parseSecciones(string $path): void
    {
        $rawLines = @file($path);
        if (! $rawLines) {
            return;
        }

        foreach ($rawLines as $line) {
            if (! mb_check_encoding($line, 'UTF-8')) {
                $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1252');
            }
            $line = trim($line, "\r\n");
            if (! preg_match('/^(CAPITULO|RUBRO|CTA\.MADRE):/', $line, $mSec)) {
                continue;
            }
            $cols = str_getcsv($line, ';');
            $codigo = '';
            foreach ($cols as $c) {
                if (preg_match('/^\d+(\.\d+)*$/', trim($c))) {
                    $codigo = trim($c);
                    break;
                }
            }
            if ($mSec[1] === 'RUBRO' && preg_match('/^(\d)(\d{2})$/', $codigo, $mRub)) {
                $codigo = $mRub[1].'.'.$mRub[2];
            }
            $nombre = '';
            foreach (array_reverse($cols) as $c) {
                if (trim($c) !== '') {
                    $nombre = trim($c);
                    break;
                }
            }
            if ($codigo !== '' && $nombre !== '' && $nombre !== $codigo && $nombre !== ($cols[0] ?? '')) {
                $this->secciones[$codigo] = $nombre;
            }
        }
    }
}
