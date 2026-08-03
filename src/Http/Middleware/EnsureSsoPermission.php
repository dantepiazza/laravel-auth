<?php

namespace DantePiazza\LaravelAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware opt-in para proteger rutas internas por permiso explícito
 * (ej. Route::middleware('sso.permission:manage-infrastructure')).
 * No se registra automáticamente en ninguna ruta del paquete — el proyecto
 * consumidor lo aplica donde lo necesite.
 */
class EnsureSsoPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (!$request->user() || !$request->user()->can($permission)) {
            abort(403, 'No tenés permisos para acceder a este recurso.');
        }

        return $next($request);
    }
}
