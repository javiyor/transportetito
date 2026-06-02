<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->foreignId('comprobante_origen_id')->nullable()->constrained('comprobantes');
            $table->string('motivo')->nullable();
            $table->index(['comprobante_origen_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['user_id', 'action', 'subject_type', 'subject_id', 'context']);
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropIndex(['comprobante_origen_id']);
            $table->dropConstrainedForeignId('comprobante_origen_id');
            $table->dropColumn('motivo');
        });
    }
};
