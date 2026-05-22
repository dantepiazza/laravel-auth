<?php

namespace DantePiazza\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PersonalRefreshToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'tokenable_id',
        'tokenable_type',
        'token',
        'access_token_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function rotate(): string
    {
        $owner = $this->tokenable;

        $newAccessToken = $owner->createToken(
            'access_token',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 15))
        );

        $this->update([
            'access_token_id' => $newAccessToken->accessToken->id,
            'expires_at'      => now()->addMinutes((int) config('laravel-auth.refresh_token_expiration', 43200)),
        ]);

        return $newAccessToken->plainTextToken;
    }
}