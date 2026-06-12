<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_cuenta_combustibles', function (Blueprint $table) {
            $table->decimal('monto_fijo_mes', 14, 2)->nullable()->after('importe');
        });
    }

    public function down(): void
    {
        Schema::table('pago_cuenta_combustibles', function (Blueprint $table) {
            $table->dropColumn('monto_fijo_mes');
        });
    }
};
