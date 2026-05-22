<?php

namespace DantePiazza\LaravelAuth\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CheckIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity' => ['required', 'string'],
        ];
    }
}
