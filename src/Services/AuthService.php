<?php

namespace DantePiazza\LaravelAuth\Services;

use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;
use DantePiazza\LaravelAuth\Models\PersonalRefreshToken;
use DantePiazza\LaravelApiResponse\Exceptions\ResponseException;
use DantePiazza\LaravelAuth\Events\UserRegistered;
use DantePiazza\LaravelAuth\Support\SsoHandshakeEncryptor;

class AuthService
{
    protected $modelClass;
    protected array $config;
    protected $resolvedInstance = null;

    public function modelSet($type) {
        $types = config('laravel-auth.account_types');
    
        if (!$type || !isset($types[$type])) {
            abort(404, "Tipo de cuenta no soportado");
        }

        $this->config = $types[$type];
        $this->modelClass = $this->config['class'];
        return $this;
    }

    protected function resolve(?string $identity = null)
    {
        if ($identity) {
            $this->resolvedInstance = $this->modelClass::where($this->config['identity'], $identity)->firstOrFail();
        }
        
        if ($this->resolvedInstance) {
            return $this->resolvedInstance;
        }

        $model = auth($this->config['guard'])->user();

        if (!$model) {
            throw new ResponseException('auth_unauthorized', 'No hay una sesión activa.', 401);
        }

        return $this->resolvedInstance = $model;
    }

    public function login(string $identity, string $password): array
    {
        $model = $this->resolve($identity);

        if(empty($model->password)){
            throw new ResponseException('Es necesario restablecer la contraseña.', 'auth_empty_password', 401);
        }

        if ($model && method_exists($model, 'authFilters') && !$model->authFilters()) {
            throw new ResponseException('', 'not_found', 401);
        }
        
        $auth = method_exists($model, 'checkPassword')
            ? $model->checkPassword($password)
            : Hash::check($password, $model->password);
        
        if (!$model || !$auth) {
            throw new ResponseException('Las credenciales son incorrectas', 'auth_invalid_credential', 401);
        }

        if ($model && method_exists($model, 'loadAuthRelations')) {
            $model->loadAuthRelations();
        }
    
        $accessToken = $model->createToken(
            'access_token',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 15))
        );

        $refreshToken = $model->createRefreshToken($accessToken->accessToken->id);

        return [
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken,
            'model'         => $model,
            'resource'      => new $this -> config['resource']($model),
            'cookie'        => $this->makeRefreshCookie($refreshToken),
            ...$this->withRbacPayload($model),
        ];
    }

    public function logout(): array
    {
        $this->resolve()->removeCurrentSession();

        return [
            'cookie' => $this->makeRefreshCookie('', -1),
        ];
    }

    /**
     * Crea la cookie del refresh token, respetando session.domain/secure/same_site
     * para que funcione tanto en instalaciones normales como con cookie wildcard (SSO).
     *
     * Modo SSO activo: el valor de la cookie se cifra con AUTH_SSO_SECRET (no
     * APP_KEY) usando SsoHandshakeEncryptor, y la cookie se marca `raw` para
     * que Laravel NO le aplique además su propio cifrado de EncryptCookies
     * (atado a APP_KEY). Es necesario porque un microservicio consumer tiene
     * una APP_KEY distinta a la del Provider y jamás podría desencriptar una
     * cookie cifrada con la APP_KEY del Provider — pero sí puede desencriptar
     * con el mismo AUTH_SSO_SECRET compartido (ver EnsureSsoSession de
     * clousis/microservices, o el consumer que corresponda).
     *
     * Modo SSO desactivado (default): comportamiento idéntico al de antes de
     * esta feature — cookie de valor plano, cifrada por el EncryptCookies
     * estándar de la app (APP_KEY). Cero cambio de comportamiento.
     */
    public function makeRefreshCookie(string $refreshToken, ?int $minutes = null): Cookie
    {
        $ssoMode = config('laravel-auth.sso.mode');
        $value   = ($ssoMode && $refreshToken !== '')
            ? (new SsoHandshakeEncryptor())->encrypt(['refresh_token' => $refreshToken])
            : $refreshToken;

        return cookie(
            $this->config['name'].'_refresh_token',
            $value,
            $minutes ?? config('laravel-auth.refresh_token_expiration', 43200),
            '/',
            config('session.domain'),
            config('session.secure'),
            true,
            (bool) $ssoMode,
            config('session.same_site', 'none')
        );
    }

    /**
     * Extiende de forma aditiva el payload con roles/permisos, solo si el
     * modo SSO RBAC está habilitado y el modelo implementa las convenciones.
     * Si no aplica, no agrega ninguna clave (evita nulls/vacíos de más).
     */
    protected function withRbacPayload($model): array
    {
        if (!config('laravel-auth.sso.rbac.enabled') || !$model) {
            return [];
        }

        $rbac = [];

        if (method_exists($model, 'getSsoRoles')) {
            $rbac['roles'] = $model->getSsoRoles();
        }

        if (method_exists($model, 'getSsoPermissions')) {
            $rbac['permissions'] = $model->getSsoPermissions();
        }

        return $rbac ? ['rbac' => $rbac] : [];
    }

    public function refresh(string $token): string
    {
        if (!$token) {
            throw new ResponseException('No se proporcionó un token de refresco.', 'auth_refresh_empty', 401);
        }

        $refreshToken = PersonalRefreshToken::where('token', hash('sha256', $token))->first();

        if (!$refreshToken || $refreshToken->isExpired()) {
            throw new ResponseException('Refresh token inválido o expirado', 'auth_refresh_invalid', 401);
        }

        return $refreshToken->rotate();
    }

    public function current()
    {
        $model = $this->resolve();

        if ($model && method_exists($model, 'loadAuthRelations')) {
            $model->loadAuthRelations();
        }

        return [
            'model'    => $model,
            'resource' => new $this -> config['resource']($model),
            ...$this->withRbacPayload($model),
        ];
    }

    /**
     * Lista los refresh tokens (sesiones) activos del usuario autenticado.
     */
    public function listSessions()
    {
        return $this->resolve()->listActiveRefreshTokens();
    }

    /**
     * Revoca una sesión (refresh token) puntual del usuario autenticado.
     */
    public function revokeSession(int $refreshTokenId): void
    {
        if (!$this->resolve()->revokeRefreshToken($refreshTokenId)) {
            throw new ResponseException('auth_session_not_found', 'La sesión indicada no existe.', 404);
        }
    }

    /**
     * Revoca todas las sesiones (refresh tokens) del usuario autenticado.
     */
    public function revokeAllSessions(): void
    {
        $this->resolve()->revokeAllRefreshTokens();
    }

	public function sendRecoverCode(string $identity)
	{
        $model = $this->resolve($identity);

        if(empty($model->email)){
            throw new ResponseException('El modelo no tiene un correo asociado.', 'auth_without_email', 422);
        }
        
        $model->sendVerificationCode();
    }

	public function restorePassword(string $identity, string $password, string $code)
	{
        $this->resolve($identity)->resetPassword($password, $code);
	}

    public function changePassword(string $currentPassword, string $newPassword): void
    {
        $model = $this->resolve();

        $valid = method_exists($model, 'checkPassword')
            ? $model->checkPassword($currentPassword)
            : Hash::check($currentPassword, $model->password);

        if (! $valid) {
            throw new ResponseException('La contraseña actual es incorrecta.', 'auth_invalid_current_password', 422);
        }

        $model->password = Hash::make($newPassword);
        $model->save();
    }

    public function checkIdentity(string $identity): bool
    {
        return ! $this->modelClass::where($this->config['identity'], $identity)->exists();
    }

    public function verifyEmail(string $identity, string $code): void
    {
        $model = $this->modelClass::where($this->config['identity'], $identity)->firstOrFail();

        $model->verifyEmail($code);
    }

    public function resendVerificationCode(string $identity): void
    {
        $model = $this->modelClass::where($this->config['identity'], $identity)->firstOrFail();

        if (method_exists($model, 'hasVerifiedEmail') ? $model->hasVerifiedEmail() : !empty($model->email_verified_at)) {
            throw new ResponseException('auth_email_already_verified', 'El email ya se encuentra verificado.', 422);
        }

        if (method_exists($model, 'sendEmailVerificationCode')) {
            $model->sendEmailVerificationCode();
        }
    }

    public function register(array $validated): array
    {
        $model = new $this->modelClass;

        // Separar campos que pertenecen al modelo de los extra
        $fillable    = $model->getFillable();
        $modelFields = [];
        $extraFields = [];

        foreach ($validated as $key => $value) {
            if ($key === 'password') continue; // se maneja aparte

            if (in_array($key, $fillable)) {
                $modelFields[$key] = $value;
            } else {
                $extraFields[$key] = $value;
            }
        }

        $modelFields['password'] = Hash::make($validated['password']);

        $model->fill($modelFields)->save();

        // Hook en el modelo (si existe)
        if (method_exists($model, 'afterRegister')) {
            $model->afterRegister($extraFields);
        }

        // Evento para listeners externos
        event(new UserRegistered($model, $extraFields));

        // Verificación de email si está habilitada
        $verificationConfig = config('laravel-auth.email_verification', []);

        if (! empty($verificationConfig['enabled']) && method_exists($model, 'sendEmailVerificationCode')) {
            $model->sendEmailVerificationCode();
        }

        // Si debe hacer login, devuelve tokens
        if (config('laravel-auth.register.login_after_register', true)) {
            return $this->loginAfterRegister($model);
        }

        return [
            'model'    => $model,
            'resource' => new $this->config['resource']($model),
        ];
    }

    protected function loginAfterRegister(mixed $model): array
    {
        if (method_exists($model, 'loadAuthRelations')) {
            $model->loadAuthRelations();
        }

        $accessToken = $model->createToken(
            'access_token',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 15))
        );

        $refreshToken = $model->createRefreshToken($accessToken->accessToken->id);

        return [
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken,
            'model'         => $model,
            'resource'      => new $this->config['resource']($model),
            'cookie'        => $this->makeRefreshCookie($refreshToken),
            ...$this->withRbacPayload($model),
        ];
    }
}