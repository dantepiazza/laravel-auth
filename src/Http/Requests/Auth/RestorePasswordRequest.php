<?php

namespace DantePiazza\LaravelAuth\Http\Requests\Auth;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

use DantePiazza\LaravelAuth\Rules\ValidVerificationCode;

class RestorePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        
        return [
            'identity' => 'required',
            'password' => 'required|min:8|confirmed',
            'code'  => ['required', 'string', new ValidVerificationCode('password.recover')],
        ];
    }
}