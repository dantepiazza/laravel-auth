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

        if ($user && is_null($user->email_verified_at)) {
            return ApiResponse::forbidden(message: 'Debés verificar tu dirección de correo electrónico antes de continuar.')->response();
        }

        return $next($request);
    }
}