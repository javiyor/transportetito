<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserPasswordResetController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $password = Str::random(20);

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ])->save();

        $request->session()->flash('flash.bannerStyle', 'success');
        $request->session()->flash('flash.banner', 'Password reseteado. Mostramos el password temporal una sola vez.');
        $request->session()->flash('tt.temp_password', $password);
        $request->session()->flash('tt.temp_password_email', $user->email);

        return redirect()->route('admin.users.index');
    }
}
