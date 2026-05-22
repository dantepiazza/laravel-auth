<?php

namespace DantePiazza\LaravelAuth\Rules;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

use DantePiazza\LaravelAuth\Models\PersonalVerificationCode;

class ValidVerificationCode implements ValidationRule, DataAwareRule
{
    protected array $data = [];
    protected string $verificationType; // Ej: 'reset_password', 'login'

    public function __construct(string $verificationType)
    {
        // El type de la tabla PersonalVerificationCode
        $this->verificationType = $verificationType;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 1. Obtener {type} de la ruta (affiliates, users, etc.)
        $routeType = request()->route('type');
        $types = config('laravel-auth.account_types');

        if (!$routeType || !isset($types[$routeType])) {
            abort(404, "Tipo de cuenta no soportado");
        }

        // 2. Obtener la clase del modelo
        $modelClass = $types[$routeType]['class'];

        // 3. Buscar el sujeto usando el método estático del Trait
        // Usamos :: porque $modelClass es un string con el namespace
        $subject = $modelClass::getByIdentity($this->data['identity'] ?? '');

        if (!$subject) {
            $fail('No se pudo determinar la identidad del usuario.');
            return;
        }

        // 4. Buscar el registro de verificación
        $verification = PersonalVerificationCode::where('verifiable_id', $subject->id)
            ->where('verifiable_type', $modelClass)
            ->where('type', $this->verificationType)
            ->first();

        // 5. Validaciones de existencia y estado
        if (!$verification) {
            $fail('El código no fue solicitado o es inexistente.');
            return;
        }

        if ($verification->isExpired()) {
            $verification->delete();
            $fail('El código ha expirado.');
            return;
        }

        if ($verification->tooManyAttempts()) {
            $verification->delete();
            $fail('Demasiados intentos fallidos. Solicitá uno nuevo.');
            return;
        }

        if (!$verification->isValid($value)) {
            $verification->increment('attempts');
            $fail('El código de verificación es incorrecto.');
            return;
        }
    }
}