<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    | The segment appended after v1/{type}/. Defaults to "auth".
    */
    'prefix' => 'auth',

    /*
    |--------------------------------------------------------------------------
    | Refresh token expiration
    |--------------------------------------------------------------------------
    | Time in minutes before a refresh token expires. Defaults to 30 days.
    */
    'refresh_token_expiration' => 43200,

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    | login_after_register: if true, returns tokens after register (same as login).
    |                        if false, returns only the created model.
    */
    'register' => [
        'login_after_register' => true,
    ],

    'register_fields' => [
        'frontend_url' => 'nullable|url',
    ],

    /*
    |--------------------------------------------------------------------------
    | Email verification
    |--------------------------------------------------------------------------
    | enabled:  whether to send a verification code after registration.
    | blocking: if true, authenticated routes require a verified email.
    |           if false, verification is optional / background.
    */
    'email_verification' => [
        'enabled'  => false,
        'blocking' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Account types
    |--------------------------------------------------------------------------
    | One entry per authenticatable model. The array key becomes the {type}
    | segment in the route: POST v1/users/auth/login, POST v1/admins/auth/login
    |
    | register_fields: extra validation rules merged into the RegisterRequest.
    |   Fields that match the model's $fillable are assigned to the model.
    |   Fields that don't match are passed to afterRegister($extraFields) and
    |   the UserRegistered event as extra data.
    */
    'account_types' => [
        /*'users' => [
            'name'     => 'user', 
            'guard'    => 'sanctum',
            'identity' => 'email', 
            'class'    => App\\Models\\User:class,
            'resource' => App\\Resources\\UserResource:class,
        ]*/
    ],
];
