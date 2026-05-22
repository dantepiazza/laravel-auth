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
}