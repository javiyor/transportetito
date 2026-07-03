<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->decimal('subtotal', 14, 2)->default(0)->after('total');
            $table->decimal('iva_total', 14, 2)->default(0)->after('subtotal');
            $table->decimal('tributos_total', 14, 2)->default(0)->after('iva_total');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'iva_total', 'tributos_total']);
        });
    }
};
