<?php

namespace DantePiazza\LaravelAuth\Models;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;

class PersonalVerificationCode extends Model
{
    protected $fillable = [
        'verifiable_id',
        'verifiable_type',
        'code',
        'type',
        'attempts',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(string $inputCode): bool
    {
        return Hash::check($inputCode, $this->code);
    }

    public function tooManyAttempts(): bool
    {
        return $this->attempts >= 3;
    }

    public static function consume($model, string $type, string $plainCode): bool
    {
        $record = $model->verificationCodes()
            ->where('type', $type)
            ->where('expires_at', '>', now())
            ->first();

            if (!$record || !Hash::check($plainCode, $record->code)) {
                if ($record) {
                    $record->increment('attempts');
                    if ($record->tooManyAttempts()) {
                        $record->delete();
                    }
                }
                return false;
            }

        return $record->delete();
    }
}