<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserUnblockController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'blocked_at' => null,
        ])->save();

        $request->session()->flash('flash.bannerStyle', 'success');
        $request->session()->flash('flash.banner', 'Usuario desbloqueado.');

        return redirect()->route('admin.users.index');
    }
}
