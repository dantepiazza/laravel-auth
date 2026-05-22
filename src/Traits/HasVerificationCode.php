<?php

namespace DantePiazza\LaravelAuth\Traits;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use DantePiazza\LaravelAuth\Mails\VerificationCodeEmail;
use DantePiazza\LaravelAuth\Models\PersonalVerificationCode;
use DantePiazza\LaravelApiResponse\Exceptions\ResponseException;

trait HasVerificationCode
{
    public static function getByIdentity(string $identity): ?static
    {
        if (empty($identity)) return null;

        $routeType = request()->route('type');
        $identityField = config("laravel-auth.account_types.{$routeType}.identity", 'email');

        return static::where($identityField, $identity)->first();
    }
    
    public function verificationCodes()
    {
        return $this->morphMany(PersonalVerificationCode::class, 'verifiable');
    }
    
    public function generateVerificationCode(string $type, int $minutes = 15): int
    {
        $code = random_int(100000, 999999);
        
        $this->verificationCodes()->updateOrCreate(
            ['type' => $type],
            [
                'code'       => Hash::make($code),
                'expires_at' => now()->addMinutes($minutes),
                'attempts'   => 0,
            ]
        );

        return $code;
    }

    public function sendVerificationCode(): void
    {
        $code        = $this->generateVerificationCode('password.recover');
        $frontendUrl = request()->input('frontend_url');
        $base        = $frontendUrl ?? config('app.url');
        $url         = rtrim($base, '/') . '?code=' . $code . '&identity=' . urlencode($this->email);

        Mail::to($this->email)->send(new VerificationCodeEmail($code, 'password.recover', $url));
    }
    public function sendEmailVerificationCode(): void
    {
        $code        = $this->generateVerificationCode('email.verify');
        $frontendUrl = request()->input('frontend_url');
        $base        = $frontendUrl ?? config('app.url');
        $url         = rtrim($base, '/') . '?code=' . $code . '&identity=' . urlencode($this->email);

        Mail::to($this->email)->send(new VerificationCodeEmail($code, 'email.verify', $url));
    }

    public function verifyEmail(string $code): void
    {
        if (! PersonalVerificationCode::consume($this, 'email.verify', $code)) {
            throw new ResponseException('Código inválido o expirado.', 'auth_invalid_code', 422);
        }

        $this->email_verified_at = now();
        $this->save();
    }

    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function resetPassword(string $newPassword, string $code): void
    {
        if (! PersonalVerificationCode::consume($this, 'password.recover', $code)) {
            throw new ResponseException('Código inválido o expirado.', 'auth_invalid_code', 422);
        }

        $this->password = Hash::make($newPassword);
        $this->save();
    }
}