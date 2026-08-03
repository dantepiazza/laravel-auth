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

    public function currentRefreshToken()
    {
        $token = $this->currentAccessToken();
        
        if (!$token) return null;

        return $this->hasOne(PersonalRefreshToken::class, 'access_token_id', 'id')
                    ->where('access_token_id', $token->id);
    }

    public function removeCurrentSession()
    {
        $refreshToken = $this->currentRefreshToken();
        
        if ($refreshToken) {
            $refreshToken->delete();
        }

        $this->currentAccessToken()?->delete();
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