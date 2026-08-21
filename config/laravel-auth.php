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
    |
    | web_guard: (opcional) override del guard de sesión que usan las
    |   pantallas Blade (ver "web" más abajo) para este account_type
    |   puntual. Si no se define, se usa web.default_guard.
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
    | default_type: account_type usado para resolver la URL de login cuando el
    |   paquete necesita construirla sin depender de un dominio hardcodeado.
    |
    | provider_login_route: nombre de ruta HTML (GET, con formulario) que el
    |   Provider expone para el login humano — la que ve el usuario en el
    |   navegador durante el handshake cross-domain (Fase 4). Por defecto
    |   resuelve 'login', la convención estándar de Laravel (la misma que usa
    |   Auth::routes()/Breeze/Fortify y a la que redirige el middleware 'auth'
    |   cuando no hay sesión) — así funciona out-of-the-box en cualquier app
    |   Laravel típica sin configurar nada. Si el host nombra su ruta de login
    |   distinto (ej. un grupo con prefijo de nombre, como 'auth.login.form'),
    |   hay que setear AUTH_SSO_PROVIDER_LOGIN_ROUTE con el nombre real —
    |   nunca asumir 'login' a ciegas sin confirmar que esa ruta existe.
    |
    | secret: secret dedicado para el handshake cross-domain (Fase 4). No debe
    |   reutilizar APP_KEY: permite rotarlo de forma independiente y evita
    |   depender de la app key de cada consumidor.
    |
    | allowed_consumers: lista de dominios raíz permitidos para el handshake,
    |   solo se usa del lado "provider".
    */
    /*
    |--------------------------------------------------------------------------
    | Web (Blade) auth screens
    |--------------------------------------------------------------------------
    | Opt-in. Desactivado por defecto: si "enabled" es false no se registra
    | ninguna ruta web nueva y el comportamiento del paquete es idéntico al
    | de antes de esta feature.
    |
    | Cuando se activa, se registran rutas GET/POST bajo {type}/auth/... que
    | renderizan las vistas Blade del paquete (publicables vía
    | `vendor:publish --tag=laravel-auth-web-views`) y autentican con un
    | guard de sesión estándar de Laravel (no Sanctum).
    |
    | default_guard: guard de sesión (config/auth.php) usado para login()/
    |   logout() de las vistas Blade. Debe ser un guard con soporte de
    |   sesión (ej. el "web" por defecto de Laravel) — el guard "sanctum"
    |   de account_types (stateless) no sirve para esto. Se puede
    |   sobreescribir por account_type con la clave "web_guard".
    |
    | redirect_after_login / redirect_after_logout: nombre de ruta o URL a
    |   la que se redirige tras esas acciones. Si es null, redirige a '/'.
    */
    'web' => [
        'enabled' => env('AUTH_WEB_ENABLED', false),

        'default_guard' => env('AUTH_WEB_DEFAULT_GUARD', 'web'),

        'redirect_after_login'  => env('AUTH_WEB_REDIRECT_AFTER_LOGIN'),
        'redirect_after_logout' => env('AUTH_WEB_REDIRECT_AFTER_LOGOUT'),
    ],

    'sso' => [
        'mode' => env('AUTH_SSO_MODE'),

        'default_type' => env('AUTH_SSO_DEFAULT_TYPE', 'users'),

        'provider_login_route' => env('AUTH_SSO_PROVIDER_LOGIN_ROUTE', 'login'),

        'secret' => env('AUTH_SSO_SECRET'),

        'handshake_ttl' => env('AUTH_SSO_HANDSHAKE_TTL', 60),

        'provider_url' => env('AUTH_SSO_PROVIDER_URL'),

        'allowed_consumers' => array_filter(explode(',', env('AUTH_SSO_ALLOWED_CONSUMERS', ''))),

        'rbac' => [
            'enabled' => env('AUTH_SSO_RBAC_ENABLED', false),
        ],
    ],
];
