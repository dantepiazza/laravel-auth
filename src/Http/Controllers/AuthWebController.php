<?php

namespace DantePiazza\LaravelAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use DantePiazza\LaravelAuth\Services\AuthService;
use DantePiazza\LaravelApiResponse\Exceptions\ResponseException;
use DantePiazza\LaravelAuth\Http\Requests\Auth\LoginRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\RegisterRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\VerifyEmailRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\ChangePasswordRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\RecoverPasswordRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\RestorePasswordRequest;
use DantePiazza\LaravelAuth\Http\Requests\Auth\ResendVerificationCodeRequest;

/**
 * Contraparte Blade de AuthController. No usa Sanctum: autentica con un
 * guard de sesión estándar de Laravel (laravel-auth.web.default_guard,
 * "web" por defecto, o account_types.{type}.web_guard), así las pantallas
 * HTML funcionan con formularios + CSRF clásicos sin necesitar dominios
 * stateful. La lógica de negocio que no depende del mecanismo de sesión
 * (register, verify email, recover/restore password) se delega igual que
 * en la API a AuthService.
 */
class AuthWebController
{
    public function __construct(
        private AuthService $authService,
    ) {}

    private function typeConfig(Request $request): array
    {
        $type  = $request->route('type');
        $types = config('laravel-auth.account_types');

        if (! $type || ! isset($types[$type])) {
            abort(404, 'Tipo de cuenta no soportado');
        }

        return $types[$type];
    }

    /**
     * Guard de sesión para login()/logout() Blade. Distinto del guard de
     * account_types.{type}.guard (típicamente "sanctum", stateless, sin
     * soporte de sesión) — ver nota en config/laravel-auth.php.
     */
    private function sessionGuard(array $config): string
    {
        return $config['web_guard'] ?? config('laravel-auth.web.default_guard', 'web');
    }

    private function view(string $name, array $data = []): View
    {
        return view("laravel-auth::auth.{$name}", $data);
    }

    public function showLogin(Request $request): View
    {
        return $this->view('login', ['type' => $request->route('type')]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $config = $this->typeConfig($request);
        $guard  = $this->sessionGuard($config);
        $model  = $config['class']::where($config['identity'], $request->identity)->first();

        $checkedOut = $model
            && (! method_exists($model, 'authFilters') || $model->authFilters())
            && ! empty($model->password)
            && (method_exists($model, 'checkPassword')
                ? $model->checkPassword($request->password)
                : Hash::check($request->password, $model->password));

        if (! $checkedOut) {
            throw ValidationException::withMessages([
                'identity' => 'Las credenciales son incorrectas.',
            ]);
        }

        if (method_exists($model, 'loadAuthRelations')) {
            $model->loadAuthRelations();
        }

        $request->session()->regenerate();
        Auth::guard($guard)->login($model, $request->boolean('remember'));

        $redirect = config('laravel-auth.web.redirect_after_login');

        return $redirect ? redirect()->intended($redirect) : redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        $type   = $request->route('type');
        $config = config("laravel-auth.account_types.{$type}", []);

        Auth::guard($this->sessionGuard($config))->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirect = config('laravel-auth.web.redirect_after_logout');

        return redirect($redirect ?: route('laravel-auth.web.login.show', ['type' => $type]));
    }

    public function showRegister(Request $request): View
    {
        return $this->view('register', ['type' => $request->route('type')]);
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $type = $request->route('type');

        $this->authService->modelSet($type)->register($request->validated());

        return redirect()
            ->route('laravel-auth.web.login.show', ['type' => $type])
            ->with('status', 'Cuenta creada correctamente. Ya podés iniciar sesión.');
    }

    public function showVerifyEmail(Request $request): View
    {
        return $this->view('verify-email', [
            'type'     => $request->route('type'),
            'identity' => $request->query('identity'),
        ]);
    }

    public function verifyEmail(VerifyEmailRequest $request): RedirectResponse
    {
        $type = $request->route('type');

        try {
            $this->authService->modelSet($type)->verifyEmail(
                $request->validated('identity'),
                $request->validated('code'),
            );
        } catch (ResponseException $e) {
            return back()->withInput()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()
            ->route('laravel-auth.web.login.show', ['type' => $type])
            ->with('status', 'Email verificado correctamente.');
    }

    public function resendVerificationCode(ResendVerificationCodeRequest $request): RedirectResponse
    {
        $type = $request->route('type');

        $this->authService->modelSet($type)->resendVerificationCode($request->validated('identity'));

        return back()->with('status', 'Código de verificación reenviado.');
    }

    public function showRecoverPassword(Request $request): View
    {
        return $this->view('password.recover', ['type' => $request->route('type')]);
    }

    public function recoverPassword(RecoverPasswordRequest $request): RedirectResponse
    {
        $type = $request->route('type');

        $this->authService->modelSet($type)->sendRecoverCode($request->identity);

        return back()->with('status', 'Si el dato ingresado existe, enviamos un código de recuperación por email.');
    }

    public function showRestorePassword(Request $request): View
    {
        return $this->view('password.restore', [
            'type'     => $request->route('type'),
            'identity' => $request->query('identity'),
            'code'     => $request->query('code'),
        ]);
    }

    public function restorePassword(RestorePasswordRequest $request): RedirectResponse
    {
        $type = $request->route('type');

        try {
            $this->authService->modelSet($type)->restorePassword(
                $request->identity,
                $request->password,
                $request->code,
            );
        } catch (ResponseException $e) {
            return back()->withInput()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()
            ->route('laravel-auth.web.login.show', ['type' => $type])
            ->with('status', 'Contraseña actualizada correctamente. Ya podés iniciar sesión.');
    }

    public function changePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $type   = $request->route('type');
        $config = config("laravel-auth.account_types.{$type}", []);
        $model  = Auth::guard($this->sessionGuard($config))->user();

        if (! $model) {
            abort(401);
        }

        $valid = method_exists($model, 'checkPassword')
            ? $model->checkPassword($request->current_password)
            : Hash::check($request->current_password, $model->password);

        if (! $valid) {
            return back()->withErrors(['current_password' => 'La contraseña actual es incorrecta.']);
        }

        $model->password = Hash::make($request->password);
        $model->save();

        return back()->with('status', 'Contraseña actualizada correctamente.');
    }
}
