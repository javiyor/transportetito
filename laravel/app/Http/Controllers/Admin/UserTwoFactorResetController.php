<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserTwoFactorResetController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->flash('flash.bannerStyle', 'success');
        $request->session()->flash('flash.banner', '2FA reseteado.');

        return redirect()->route('admin.users.index');
    }
}
