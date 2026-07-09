<?php

namespace DantePiazza\LaravelAuth\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('refresh_token')) {
            return;
        }

        $type = $this->route('type');
        
        $cookieName = config("laravel-auth.account_types.{$type}.name", 'refresh_token') . '_refresh_token';

        $this->merge([
            'refresh_token' => $this->cookie($cookieName),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }
}
