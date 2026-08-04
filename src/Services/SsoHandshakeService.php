<?php

namespace DantePiazza\LaravelAuth\Services;

use DantePiazza\LaravelAuth\Support\SsoHandshakeEncryptor;
use DantePiazza\LaravelApiResponse\Exceptions\ResponseException;

/**
 * Handshake cross-domain (Fase 4 de SSO): permite que un dominio raíz distinto
 * al del Provider se autentique sin depender de cookie wildcard. Es un
 * servicio separado de AuthService para no tocar su superficie pública.
 *
 * Decisiones conscientes (ver .agents/ROADMAP.md "Notas de implementación"):
 * - Secret único compartido entre todos los consumidores habilitados.
 * - El token viaja en query string, cifrado, con TTL corto.
 * - No hay registro de tokens de handshake ya usados (single-use real queda
 *   en el backlog).
 */
class SsoHandshakeService
{
    public function __construct(protected SsoHandshakeEncryptor $encryptor) {}

    /**
     * Lado Consumer: arma la URL de redirección al login del Provider,
     * adjuntando un token cifrado con el dominio de origen, el path de
     * retorno final y la URL absoluta de SU PROPIO callback (route
     * 'laravel-auth.sso.callback', resuelta acá mismo — nunca adivinada por
     * el Provider). Así el paquete nunca asume bajo qué prefijo (api/, etc.)
     * cada proyecto host monta sus rutas.
     */
    public function buildProviderRedirectUrl(string $consumerDomain, string $redirectPath): string
    {
        $providerUrl = rtrim((string) config('laravel-auth.sso.provider_url'), '/');

        if (empty($providerUrl)) {
            throw new ResponseException('sso_provider_url_missing', 'AUTH_SSO_PROVIDER_URL no está configurado.', 500);
        }

        $token = $this->encryptor->encrypt([
            'consumer' => $consumerDomain,
            'redirect' => $redirectPath,
            'callback' => route('laravel-auth.sso.callback', absolute: true),
        ]);

        // El default real vive en config/laravel-auth.php ('login', la
        // convención estándar de Laravel) — este segundo argumento de
        // config() es solo un resguardo por si la key no llegó a mergearse
        // por algún motivo, nunca debe ser un default distinto al del config.
        $loginRoute = config('laravel-auth.sso.provider_login_route', 'login');
        $loginPath = route($loginRoute, ['type' => config('laravel-auth.sso.default_type')], false);

        return $providerUrl.'/'.ltrim($loginPath, '/').'?sso_handshake='.urlencode($token);
    }

    /**
     * Lado Provider: valida el token entrante (dominio consumidor contra la
     * allowlist, y que el callback recibido realmente pertenezca a ese mismo
     * dominio — evita que el token termine redirigiendo a un host distinto)
     * y devuelve sus datos ({consumer, redirect, callback}).
     */
    public function validateIncomingRequest(string $token): array
    {
        $payload = $this->encryptor->decrypt($token);

        $allowed = config('laravel-auth.sso.allowed_consumers', []);

        if (!in_array($payload['consumer'] ?? null, $allowed, true)) {
            throw new ResponseException('sso_consumer_not_allowed', 'El dominio consumidor no está autorizado.', 403);
        }

        $callbackHost = parse_url($payload['callback'] ?? '', PHP_URL_HOST);

        if (!$callbackHost || $callbackHost !== $payload['consumer']) {
            throw new ResponseException('sso_callback_mismatch', 'La URL de callback no corresponde al dominio consumidor.', 403);
        }

        return $payload;
    }

    /**
     * Lado Provider: tras el login normal, cifra los tokens de sesión para
     * devolverlos al Consumer.
     */
    public function buildResponseToken(string $accessToken, string $refreshToken): string
    {
        return $this->encryptor->encrypt([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Lado Consumer: descifra la respuesta del Provider para obtener los
     * tokens de sesión.
     */
    public function acceptOnConsumer(string $token): array
    {
        $payload = $this->encryptor->decrypt($token, (int) config('laravel-auth.sso.handshake_ttl', 60));

        if (!isset($payload['access_token'], $payload['refresh_token'])) {
            throw new ResponseException('sso_handshake_invalid', 'Token de enlace inválido.', 401);
        }

        return $payload;
    }
}
