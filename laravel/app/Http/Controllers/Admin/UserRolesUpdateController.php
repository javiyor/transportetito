<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class UserRolesUpdateController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $availableRoles = config('roles.available', []);

        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in($availableRoles)],
        ]);

        $roles = array_values(array_unique($data['roles']));
        $currentlyAdmin = $user->hasRole('admin');
        $willBeAdmin = in_array('admin', $roles, true);

        if ($user->id === $request->user()->id && ! $willBeAdmin) {
            return back()->withErrors([
                'roles' => 'No podes removerte admin a vos mismo.',
            ]);
        }

        if ($currentlyAdmin && ! $willBeAdmin && User::role('admin')->count() === 1) {
            return back()->withErrors([
                'roles' => 'No se puede eliminar el ultimo admin.',
            ]);
        }

        $user->syncRoles($roles);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $request->session()->flash('flash.bannerStyle', 'success');
        $request->session()->flash('flash.banner', 'Roles actualizados.');

        return redirect()->route('admin.users.index');
    }
}
