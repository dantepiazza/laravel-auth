<?php

namespace DantePiazza\LaravelAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use DantePiazza\LaravelApiResponse\Facades\ApiResponse;

class VerifyEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('laravel-auth.email_verification.blocking')) {
            return $next($request);
        }

        $user = Auth::user();

        // Fail-closed: sin usuario autenticado (middleware sin `auth` delante, o un guard que
        // no resuelve usuario) no se deja pasar. Antes devolvía $next y abría un hueco.
        if (!$user) {
            return ApiResponse::unauthorized(data: ['reason' => 'unauthenticated'], message: 'Debés iniciar sesión para continuar.')->response();
        }

        if (is_null($user->email_verified_at)) {
            return ApiResponse::forbidden(data: ['reason' => 'email_unverified'], message: 'Debés verificar tu dirección de correo electrónico antes de continuar.')->response();
        }

        return $next($request);
    }
}