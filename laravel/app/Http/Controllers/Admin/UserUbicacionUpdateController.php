<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserUbicacionUpdateController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'envia_ubicacion' => ['required', 'boolean'],
        ]);

        $user->forceFill(['envia_ubicacion' => (bool) $data['envia_ubicacion']])->save();

        return back()->with('flash.success', 'Flag de ubicacion actualizado.');
    }
}
