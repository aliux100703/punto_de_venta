<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequireSellerCash
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('Vendedor')) {
            return $next($request);
        }
        if ($request->isMethodSafe()) {
            if (! $user->cashRegisters()->where('status', 'ABIERTA')->exists() && $request->routeIs('pos.*', '*.create', '*.edit') && ! $request->routeIs('profile.edit')) {
                return to_route('cash.index')->with('warning', 'Modo consulta: abre tu caja para realizar operaciones.');
            }

            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $request->routeIs('logout', 'cash.open', 'cash.close', 'shift.decline', 'shift.finish')) {
                abort_unless($user->cashRegisters()->where('status', 'ABIERTA')->exists(), 403, 'Modo consulta: abre tu caja para realizar operaciones.');
            }

            return $next($request);
        });
    }
}
