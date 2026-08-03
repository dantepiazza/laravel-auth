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

    /*
    |--------------------------------------------------------------------------
    | SSO (opcional)
    |--------------------------------------------------------------------------
    | Funcionalidad de Single Sign-On entre microservicios/SPAs. Desactivada
    | por defecto: si "mode" queda en null no se registra ninguna ruta ni
    | middleware nuevo, y el payload de login()/current() no cambia.
    |
    | mode: null | 'provider' | 'consumer'
    |   - provider: emite tokens de forma centralizada.
    |   - consumer: redirige/valida contra el provider.
    |
    | default_type: account_type usado para resolver route('laravel-auth.login',
    |   ['type' => ...]) cuando el paquete necesita construir una URL de login
    |   sin depender de un dominio o path hardcodeado.
    |
    | secret: secret dedicado para el handshake cross-domain (Fase 4). No debe
    |   reutilizar APP_KEY: permite rotarlo de forma independiente y evita
    |   depender de la app key de cada consumidor.
    |
    | allowed_consumers: lista de dominios raíz permitidos para el handshake,
    |   solo se usa del lado "provider".
    */
    'sso' => [
        'mode' => env('AUTH_SSO_MODE'),

        'default_type' => env('AUTH_SSO_DEFAULT_TYPE', 'users'),

        'secret' => env('AUTH_SSO_SECRET'),

        'handshake_ttl' => env('AUTH_SSO_HANDSHAKE_TTL', 60),

        'provider_url' => env('AUTH_SSO_PROVIDER_URL'),

        'allowed_consumers' => array_filter(explode(',', env('AUTH_SSO_ALLOWED_CONSUMERS', ''))),

        'rbac' => [
            'enabled' => env('AUTH_SSO_RBAC_ENABLED', false),
        ],
    ],
];
