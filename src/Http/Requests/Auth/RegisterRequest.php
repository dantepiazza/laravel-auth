<?php

namespace DantePiazza\LaravelAuth\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->route('type');

        $extraRules = config("laravel-auth.account_types.{$type}.register_fields", []);

        $baseRules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        return array_merge($baseRules, $extraRules);
    }
}
