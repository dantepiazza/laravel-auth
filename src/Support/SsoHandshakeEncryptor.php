<?php

namespace DantePiazza\LaravelAuth\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use DantePiazza\LaravelApiResponse\Exceptions\ResponseException;

/**
 * Cifrado simétrico dedicado al handshake cross-domain (Fase 4 de SSO).
 *
 * Usa una instancia propia de Encrypter (no el facade Crypt, que está atado
 * a APP_KEY) construida con laravel-auth.sso.secret, para que el secret del
 * SSO sea independiente de la app key de cada proyecto consumidor y pueda
 * rotarse por separado. Se reutiliza Encrypter en vez de openssl_encrypt a
 * mano porque ya implementa AES-256-CBC con MAC de integridad, evitando
 * reimplementar cifrado autenticado desde cero.
 */
class SsoHandshakeEncryptor
{
    protected Encrypter $encrypter;

    public function __construct()
    {
        $secret = config('laravel-auth.sso.secret');

        if (empty($secret)) {
            throw new ResponseException('El SSO no tiene configurado AUTH_SSO_SECRET.', 'sso_secret_missing', 500);
        }

        $key = strlen($secret) >= 32 ? substr($secret, 0, 32) : str_pad($secret, 32, '0');

        $this->encrypter = new Encrypter($key, 'AES-256-CBC');
    }

    public function encrypt(array $payload): string
    {
        $payload['issued_at'] = $payload['issued_at'] ?? now()->timestamp;

        return $this->encrypter->encrypt(json_encode($payload));
    }

    /**
     * Descifra y valida el TTL del token de handshake.
     */
    public function decrypt(string $token, ?int $ttlSeconds = null): array
    {
        try {
            $payload = json_decode($this->encrypter->decrypt($token), true);
        } catch (DecryptException $e) {
            throw new ResponseException('Token de enlace inválido.', 'sso_handshake_invalid', 401);
        }

        if (!is_array($payload) || !isset($payload['issued_at'])) {
            throw new ResponseException('Token de enlace inválido.', 'sso_handshake_invalid', 401);
        }

        $ttl = $ttlSeconds ?? (int) config('laravel-auth.sso.handshake_ttl', 60);

        if (now()->timestamp - (int) $payload['issued_at'] > $ttl) {
            throw new ResponseException('El enlace de sesión expiró, intentá de nuevo.', 'sso_handshake_expired', 401);
        }

        return $payload;
    }
}
