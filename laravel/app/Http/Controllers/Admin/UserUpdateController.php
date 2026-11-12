<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserUpdateController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
        ]);

        $user->forceFill(['name' => $data['name'], 'username' => $data['username']])->save();

        return back()->with('flash.success', 'Nombre de usuario actualizado.');
    }
}
