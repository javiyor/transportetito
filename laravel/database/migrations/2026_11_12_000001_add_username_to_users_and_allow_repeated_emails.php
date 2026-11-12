<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->string('username')->nullable();
        });

        $used = [];
        DB::table('users')
            ->orderBy('id')
            ->get(['id', 'name', 'email'])
            ->each(function ($user) use (&$used) {
                $source = $user->email ? Str::before($user->email, '@') : $user->name;
                $base = Str::slug((string) $source) ?: 'usuario';
                $base = Str::substr($base, 0, 220);
                $username = $base;
                $suffix = 1;

                while (isset($used[$username])) {
                    $ending = '-'.$suffix++;
                    $username = Str::substr($base, 0, 220 - strlen($ending)).$ending;
                }

                $used[$username] = true;

                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
            $table->unique('email');
        });
    }
};
