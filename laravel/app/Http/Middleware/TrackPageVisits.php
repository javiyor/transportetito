<?php

namespace App\Http\Middleware;

use App\Models\UserPageVisit;
use Closure;
use Illuminate\Http\Request;

class TrackPageVisits
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->user() && $request->isMethod('get') && !$request->expectsJson() && !$request->ajax() && !str_starts_with($request->path(), 'api/')) {
            try {
                $routeName = $request->route()?->getName();
                if ($routeName && !in_array($routeName, ['ignition.healthCheck', 'sanctum.csrf-cookie'])) {
                    UserPageVisit::create([
                        'user_id' => $request->user()->id,
                        'route_name' => $routeName,
                        'path' => $request->path(),
                        'title' => $routeName,
                    ]);
                    // Keep only last 200 per user to avoid bloat
                    $count = UserPageVisit::where('user_id', $request->user()->id)->count();
                    if ($count > 200) {
                        $ids = UserPageVisit::where('user_id', $request->user()->id)->orderBy('id')->limit($count - 200)->pluck('id');
                        UserPageVisit::whereIn('id', $ids)->delete();
                    }
                }
            } catch (\Throwable $e) {
                // silent
            }
        }

        return $response;
    }
}