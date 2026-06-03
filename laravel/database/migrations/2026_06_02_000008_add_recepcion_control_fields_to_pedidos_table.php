<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('recepcion_estado')->nullable()->after('external_retiro');
            $table->text('recepcion_observacion')->nullable()->after('recepcion_estado');
            $table->timestamp('recepcion_controlado_at')->nullable()->after('recepcion_observacion');
            $table->foreignId('recepcion_controlado_por_user_id')->nullable()->after('recepcion_controlado_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recepcion_controlado_por_user_id');
            $table->dropColumn(['recepcion_estado', 'recepcion_observacion', 'recepcion_controlado_at']);
        });
    }
};
