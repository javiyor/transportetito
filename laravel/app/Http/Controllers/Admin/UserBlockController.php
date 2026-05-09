<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserBlockController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole('admin') && User::role('admin')->count() === 1) {
            return back()->withErrors([
                'user' => 'No se puede bloquear el ultimo admin.',
            ]);
        }

        $user->forceFill([
            'blocked_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->flash('flash.bannerStyle', 'success');
        $request->session()->flash('flash.banner', 'Usuario bloqueado. Se desloguea en el proximo request.');

        return redirect()->route('admin.users.index');
    }
}
