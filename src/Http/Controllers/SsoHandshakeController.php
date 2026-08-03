<?php

namespace DantePiazza\LaravelAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use DantePiazza\LaravelAuth\Services\AuthService;
use DantePiazza\LaravelAuth\Services\SsoHandshakeService;

/**
 * Controlador del handshake cross-domain (Fase 4 de SSO). Cada acción valida
 * que el modo SSO correspondiente esté activo; si no, la ruta ni siquiera
 * llega a registrarse (ver routes/auth.php), pero se deja el chequeo también
 * acá por si se invoca la clase directamente.
 */
class SsoHandshakeController
{
    public function __construct(protected SsoHandshakeService $handshake, protected AuthService $authService) {}

    /**
     * Lado Consumer: redirige al usuario al login del Provider con un token
     * de handshake cifrado.
     */
    public function redirectToProvider(Request $request): RedirectResponse
    {
        abort_unless(config('laravel-auth.sso.mode') === 'consumer', 404);

        $consumerDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? $request->getHost();
        $redirectPath   = $request->query('redirect', '/');

        return redirect()->away(
            $this->handshake->buildProviderRedirectUrl($consumerDomain, $redirectPath)
        );
    }

    /**
     * Lado Provider: se invoca autenticado (después del login normal) para
     * cerrar el handshake y redirigir de vuelta al Consumer con un token de
     * respuesta cifrado.
     */
    public function completeHandshake(Request $request): RedirectResponse
    {
        abort_unless(config('laravel-auth.sso.mode') === 'provider', 404);

        $incoming = $this->handshake->validateIncomingRequest($request->query('sso_handshake', ''));

        $accessToken = $request->user()->createToken(
            'access_token',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 15))
        );

        $refreshToken = $request->user()->createRefreshToken($accessToken->accessToken->id);

        $responseToken = $this->handshake->buildResponseToken($accessToken->plainTextToken, $refreshToken);

        $separator = str_contains($incoming['callback'], '?') ? '&' : '?';

        return redirect()->away(
            $incoming['callback'].$separator.'sso_handshake='.urlencode($responseToken).'&redirect='.urlencode($incoming['redirect'])
        );
    }

    /**
     * Lado Consumer: recibe el token de respuesta del Provider, setea la
     * cookie local de refresh token y redirige a la página final.
     */
    public function receiveHandshake(Request $request): RedirectResponse
    {
        abort_unless(config('laravel-auth.sso.mode') === 'consumer', 404);

        $tokens = $this->handshake->acceptOnConsumer($request->query('sso_handshake', ''));

        $this->authService->modelSet(config('laravel-auth.sso.default_type'));
        $cookie = $this->authService->makeRefreshCookie($tokens['refresh_token']);

        return redirect($request->query('redirect', '/'))->withCookie($cookie);
    }
}
