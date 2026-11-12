<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email' => trim((string) $request->input('email')) ?: null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $password = Str::random(20);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);

        $request->session()->flash('flash.bannerStyle', 'success');
        $request->session()->flash('flash.banner', 'Usuario creado. Mostramos el password temporal una sola vez.');
        $request->session()->flash('tt.temp_password', $password);
        $request->session()->flash('tt.temp_password_username', $user->username);

        return redirect()->route('admin.users.index');
    }
}
