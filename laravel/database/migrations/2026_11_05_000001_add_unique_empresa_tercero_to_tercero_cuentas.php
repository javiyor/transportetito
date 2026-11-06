<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicada = DB::table('tercero_cuentas')
            ->select('empresa_id', 'tercero_id')
            ->groupBy('empresa_id', 'tercero_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicada) {
            throw new \RuntimeException(
                'Hay cuentas de cliente duplicadas. Ejecute primero php artisan terceros:deduplicar-cuit --dry-run y luego --apply antes de migrar.'
            );
        }

        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->unique(['empresa_id', 'tercero_id'], 'tercero_cuentas_empresa_tercero_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->dropUnique('tercero_cuentas_empresa_tercero_unique');
        });
    }
};
