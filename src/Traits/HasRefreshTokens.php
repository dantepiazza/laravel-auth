<?php

namespace DantePiazza\LaravelAuth\Traits;

use DantePiazza\LaravelAuth\Models\PersonalRefreshToken;
use Illuminate\Support\Str;

trait HasRefreshTokens
{
    /**
     * Relación polimórfica con los refresh tokens
     */
    public function refreshTokens()
    {
        return $this->morphMany(PersonalRefreshToken::class, 'tokenable');
    }

    /**
     * Método para generar un refresh token vinculado a un access token
     */
    public function createRefreshToken(int $accessTokenId): string
    {
        $refreshToken = Str::random(64);

        $this->refreshTokens()->create([
            'token'           => hash('sha256', $refreshToken),
            'access_token_id' => $accessTokenId,
            'expires_at'      => now()->addMinutes((int) config('laravel-auth.refresh_token_expiration', 43200)),
        ]);

        return $refreshToken;
    }

    /**
     * Bug real encontrado 2026-09-13: esto usaba hasOne(PersonalRefreshToken::class,
     * 'access_token_id', 'id'), que arma la relación constreñida por
     * `access_token_id = $this->id` — pero $this es el USUARIO (UUID), no el
     * access token. Esa comparación (string UUID contra la columna entera
     * access_token_id) nunca puede matchear nada, así que el ->where(...)
     * adicional era irrelevante: la query de base ya devolvía cero filas
     * siempre. removeCurrentSession() (usado por logout) nunca borraba
     * realmente el refresh token — solo el access token, en silencio, sin
     * que ningún test lo notara (el test de logout solo verifica que
     * tokens()->count() sea 0, nunca chequeó personal_refresh_tokens). No es
     * una relación real de Eloquent (no hay FK entre User y
     * PersonalRefreshToken vía access_token_id) — es una búsqueda simple.
     */
    public function currentRefreshToken(): ?PersonalRefreshToken
    {
        $token = $this->currentAccessToken();

        // Sesión de cookie/stateful (guard 'web'): Sanctum devuelve un
        // TransientToken sin id ni fila en BD, no hay refresh token que buscar.
        if (!$token instanceof \Laravel\Sanctum\PersonalAccessToken) return null;

        return PersonalRefreshToken::where('access_token_id', $token->id)->first();
    }

    public function removeCurrentSession()
    {
        $this->currentRefreshToken()?->delete();

        $token = $this->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }
    }

    public function rotateAccessToken()
    {
        $newToken = $this->createToken(
            'access_token',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 15))
        );

        return $newToken;
    }

    /**
     * Refresh tokens activos (no expirados) del usuario, para paneles de administración.
     */
    public function listActiveRefreshTokens()
    {
        return $this->refreshTokens()
            ->where('expires_at', '>', now())
            ->get(['id', 'access_token_id', 'expires_at', 'created_at']);
    }

    /**
     * Revoca una sesión (refresh token) puntual, siempre que pertenezca a este modelo.
     */
    public function revokeRefreshToken(int $refreshTokenId): bool
    {
        $refreshToken = $this->refreshTokens()->whereKey($refreshTokenId)->first();

        if (!$refreshToken) {
            return false;
        }

        $this->tokens()->where('id', $refreshToken->access_token_id)->delete();
        $refreshToken->delete();

        return true;
    }

    /**
     * Revoca todos los refresh tokens (y sus access tokens asociados) del usuario.
     */
    public function revokeAllRefreshTokens(): int
    {
        $count = $this->refreshTokens()->count();

        $this->tokens()->delete();
        $this->refreshTokens()->delete();

        return $count;
    }
}