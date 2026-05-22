<?php

namespace DantePiazza\LaravelAuth\Services;

use Illuminate\Support\Facades\Hash;
use DantePiazza\LaravelAuth\Models\PersonalRefreshToken;
use DantePiazza\LaravelApiResponse\Exceptions\ResponseException;
use DantePiazza\LaravelAuth\Events\UserRegistered;

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

        $cookie = cookie(
            $this->config['name'].'_refresh_token', 
            $refreshToken, 
            43200, 
            '/', 
            config('session.domain'),
            config('session.secure'),
            true,
            false, 
            config('session.same_site', 'none')
        );

        return [
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken,
            'model'         => $model,
            'resource'      => new $this -> config['resource']($model),
            'cookie'        => $cookie
        ];
    }

    public function logout(): array
    {
        $this->resolve()->removeCurrentSession(); 

        return [
            'cookie' => $this->config['name'].'_refresh_token', 
        ];
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
        ];
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

        $cookie = cookie(
            $this->config['name'] . '_refresh_token',
            $refreshToken,
            config('laravel-auth.refresh_token_expiration', 43200),
            '/',
            config('session.domain'),
            config('session.secure'),
            true,
            false,
            config('session.same_site', 'none')
        );

        return [
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken,
            'model'         => $model,
            'resource'      => new $this->config['resource']($model),
            'cookie'        => $cookie,
        ];
    }
}