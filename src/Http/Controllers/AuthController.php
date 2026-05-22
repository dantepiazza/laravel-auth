<?php

namespace DantePiazza\LaravelAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DantePiazza\LaravelApiResponse\ApiResponse;

use DantePiazza\LaravelAuth\Services\AuthService;
use DantePiazza\LaravelAuth\Http\Requests\Auth\LoginRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\RegisterRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\VerifyEmailRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\RefreshTokenRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\CheckIdentityRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\ChangePasswordRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\RecoverPasswordRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\RestorePasswordRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\ResendVerificationCodeRequest;

class AuthController
{
    protected AuthService $service;

    public function __construct(Request $request, AuthService $service, private ApiResponse $api)
    {
        $this->middleware(function ($request, $next) use ($service) {
            $this->service = $service->modelSet($request->route('type'));
    
            return $next($request);
        });
    }
    
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $this->service->login($request->identity, $request->password);

        return $this->api->success([
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'model'         => $data['resource']
            ], 'Sesión iniciada correctamente.')
            ->withCookie($data['cookie'])
            ->response();
    }

    public function logout(Request $request): JsonResponse
    {
        $data = $this->service->logout();

        return $this->api->success('Sesión cerrada correctamente.')
            ->withoutCookie($data['cookie'])
            ->response();
    }

    public function current(Request $request): JsonResponse
    {
        $model = $this->service->current();

        return $this->api->success([
                'authenticated' => true,
                'model' => $model['resource']
            ], 'Sesión activa.')
            ->response();
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $accessToken = $this->service->refresh($request->refresh_token);

        return $this->api->success([
                'access_token'  => $accessToken,
                'refresh_token' => $request->refresh_token,
            ], 'Token de acceso renovado.')
            ->response();
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $this->service->register($request->validated());

        $loginAfterRegister = config('laravel-auth.register.login_after_register', true);

        $response = $loginAfterRegister
            ? $this->api->created([
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'model'         => $data['resource'],
            ], 'Cuenta creada correctamente.')
            : $this->api->created([
                'model' => $data['resource'],
            ], 'Cuenta creada correctamente.');

        if ($loginAfterRegister && isset($data['cookie'])) {
            $response->withCookie($data['cookie']);
        }

        return $response->response();
    }

	public function recoverPassword(RecoverPasswordRequest $request): JsonResponse
    {
        $this->service->sendRecoverCode($request->identity);

        return $this->api->success(message: 'Link de recuperación enviado al email.')->response();
    }

    public function restorePassword(RestorePasswordRequest $request): JsonResponse
    {
        $this->service->restorePassword($request->identity, $request->password, $request->code);

        return $this->api->success(message: 'Contraseña actualizada correctamente.')->response();
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->service->changePassword($request->current_password, $request->password);
 
        return $this->api->success(message: 'Contraseña actualizada correctamente.')->response();
    }

    public function checkIdentity(CheckIdentityRequest $request): JsonResponse
    {
        $available = $this->service->checkIdentity($request->validated('identity'));

        return $this->api->success([
            'available' => $available,
        ], $available ? 'Disponible.' : 'Ya se encuentra registrado.')->response();
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $this->service->verifyEmail(
            $request->validated('identity'),
            $request->validated('code'),
        );

        return $this->api->success(null, 'Email verificado correctamente.')->response();
    }

    public function resendVerificationCode(ResendVerificationCodeRequest $request): JsonResponse
    {
        $this->service->resendVerificationCode($request->validated('identity'));

        return $this->api->success(null, 'Código de verificación enviado.')->response();
    }
}