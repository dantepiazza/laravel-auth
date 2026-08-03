<?php

namespace DantePiazza\LaravelAuth\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type       = $this->route('type');
        $typeConfig = config("laravel-auth.account_types.{$type}", []);

        $extraRules = $typeConfig['register_fields'] ?? [];

        $baseRules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        // Valida por defecto que el campo identidad (ej. "email") sea único,
        // salvo que el proyecto ya haya declarado su propia regla en
        // register_fields — así se evita un 500 de constraint de base de
        // datos cuando el identity ya existe, sin pisar reglas custom.
        $identityColumn = $typeConfig['identity'] ?? null;

        if ($identityColumn && !isset($extraRules[$identityColumn])) {
            $baseRules[$identityColumn] = [
                'required',
                Rule::unique($typeConfig['class'], $identityColumn),
            ];
        }

        return array_merge($baseRules, $extraRules);
    }
}
