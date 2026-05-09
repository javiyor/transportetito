<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = 25;
        $roles = config('roles.available', []);

        $users = User::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'blocked_at', 'two_factor_secret'])
            ->orderBy('id')
            ->paginate($perPage)
            ->through(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'blocked_at' => $user->blocked_at,
                    'has_2fa' => ! empty($user->two_factor_secret),
                    'roles' => $user->getRoleNames()->values()->all(),
                ];
            });

        return Inertia::render('Admin/Users/Index', [
            'roles' => $roles,
            'users' => $users,
        ]);
    }
}
