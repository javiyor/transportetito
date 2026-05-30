<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('sitio_web')->nullable();

            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('linkedin_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'telefono',
                'email',
                'whatsapp',
                'sitio_web',
                'instagram_url',
                'facebook_url',
                'linkedin_url',
            ]);
        });
    }
};
