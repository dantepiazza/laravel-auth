# dantepiazza/laravel-auth

A plug-and-play Sanctum-based authentication system for Laravel 10+ APIs. Drop the traits onto your model, publish the config, and get a full auth flow — login, registration, refresh tokens, password recovery, email verification, and device tracking — without writing a single controller.

---

## Installation

```bash
composer require dantepiazza/laravel-auth
```

The service provider is auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=laravel-auth-config
```

Migrations run automatically — no need to publish them unless you want to customize the schema:

```bash
php artisan migrate
```

---

## Setup

### 1. Configure account types

In `config/laravel-auth.php`, define one entry per authenticatable model:

```php
return [
    'prefix' => 'auth',
    'refresh_token_expiration' => 43200,

    'register' => [
        'login_after_register' => true,
    ],

    'email_verification' => [
        'enabled'  => false,
        'blocking' => false,
    ],

    'account_types' => [
        'users' => [
            'name'             => 'user',
            'guard'            => 'sanctum',
            'class'            => \App\Models\User::class,
            'identity'         => 'email',
            'resource'         => \App\Http\Resources\UserResource::class,
            'register_fields'  => [
                'firstname' => 'required|string|max:100',
                'lastname'  => 'nullable|string|max:100',
            ],
        ],
    ],
];
```

| Key | Description |
|-----|-------------|
| `name` | Used as cookie prefix for the refresh token |
| `guard` | Sanctum guard to use for authentication |
| `class` | The Eloquent model class |
| `identity` | The field used as username (e.g. `email`, `username`) |
| `resource` | API Resource class to wrap the model in responses |
| `register_fields` | Extra validation rules merged into the registration request |

> **Register validation:** only `password` and whatever you list in `register_fields` gets validated on `POST .../register` — any other field sent by the client is silently dropped before reaching the model. If your model has other `NOT NULL` columns (e.g. `name`), declare them in `register_fields` or the insert will fail. The `identity` column (e.g. `email`) gets a `required` + `unique` rule automatically unless you override it yourself in `register_fields`.

### 2. Add the traits to your model

```php
use Laravel\Sanctum\HasApiTokens;
use DantePiazza\LaravelAuth\Traits\HasRefreshTokens;
use DantePiazza\LaravelAuth\Traits\HasVerificationCode;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasRefreshTokens;
    use HasVerificationCode;
}
```

That's it. Routes are registered automatically under `v1/{type}/auth`.

---

## Routes

All routes are registered automatically. The `{type}` segment maps to a key in `account_types`.

| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| `POST` | `v1/{type}/auth/login` | — | Login and get tokens |
| `POST` | `v1/{type}/auth/refresh` | — | Rotate the access token |
| `POST` | `v1/{type}/auth/register` | — | Register a new account |
| `POST` | `v1/{type}/auth/register/check-identity` | — | Check if identity is available |
| `POST` | `v1/{type}/auth/email/verify` | — | Verify email with code |
| `POST` | `v1/{type}/auth/email/resend` | — | Resend verification code |
| `POST` | `v1/{type}/auth/password/recover` | — | Send recovery code via email |
| `POST` | `v1/{type}/auth/password/restore` | — | Reset password with code |
| `GET`  | `v1/{type}/auth/current` | Sanctum | Get the authenticated model |
| `POST` | `v1/{type}/auth/logout` | Sanctum | Invalidate session |
| `POST` | `v1/{type}/auth/password/change` | Sanctum | Change password |

Example for a `users` type:
```
POST v1/users/auth/login
POST v1/users/auth/register
GET  v1/users/auth/current
```

### Customizing the prefix

```php
'prefix' => 'session', // → v1/{type}/session/login
```

---

## Endpoints

### Login

```http
POST v1/{type}/auth/login
Content-Type: application/json

{
    "identity": "user@example.com",
    "password": "secret"
}
```

```json
{
    "status": "success",
    "code": 200,
    "message": "Sesión iniciada correctamente.",
    "data": {
        "access_token": "1|abc123...",
        "refresh_token": "def456...",
        "model": { ... }
    }
}
```

The refresh token is also set as an `HttpOnly` cookie automatically.

---

### Register

```http
POST v1/{type}/auth/register
Content-Type: application/json

{
    "firstname": "Jane",
    "lastname": "Doe",
    "email": "jane@example.com",
    "password": "secret1234",
    "password_confirmation": "secret1234"
}
```

If `login_after_register` is `true` (default), returns the same structure as login. Otherwise returns only the created model.

---

### Check identity availability

```http
POST v1/{type}/auth/register/check-identity

{ "identity": "jane@example.com" }
```

```json
{
    "data": { "available": false }
}
```

---

### Refresh token

```http
POST v1/{type}/auth/refresh

{ "refresh_token": "def456..." }
```

Send either the body field or let the cookie be sent automatically.

---

### Password recovery

```http
POST v1/{type}/auth/password/recover
{ "identity": "user@example.com" }
```

Sends a 6-digit code to the model's email. Codes expire in 15 minutes and are invalidated after 3 failed attempts.

---

### Password reset

```http
POST v1/{type}/auth/password/restore

{
    "identity": "user@example.com",
    "code": "123456",
    "password": "newpassword",
    "password_confirmation": "newpassword"
}
```

---

### Change password (authenticated)

```http
POST v1/{type}/auth/password/change
Authorization: Bearer {access_token}

{
    "current_password": "oldpassword",
    "password": "newpassword",
    "password_confirmation": "newpassword"
}
```

---

### Email verification

```http
POST v1/{type}/auth/email/verify
{ "identity": "user@example.com", "code": "123456" }

POST v1/{type}/auth/email/resend
{ "identity": "user@example.com" }
```

Enable in config:

```php
'email_verification' => [
    'enabled'  => true,   // send code after registration
    'blocking' => true,   // block protected routes until verified
],
```

When `blocking` is `true`, the `auth.verify-email` middleware is applied automatically to protected routes. You can also apply it manually on your own routes:

```php
Route::middleware(['auth:sanctum', 'auth.verify-email'])->group(...)
```

---

## Model hooks

The package checks for optional methods on your model, letting you extend behavior without overriding anything.

### `authFilters(): bool`

Called before password verification. Return `false` to block login.

```php
public function authFilters(): bool
{
    return $this->is_active && $this->email_verified_at !== null;
}
```

### `checkPassword(string $password): bool`

Override the default `Hash::check()`.

```php
public function checkPassword(string $password): bool
{
    return MyLegacyHasher::check($password, $this->password);
}
```

### `loadAuthRelations(): void`

Called after login and on `current`. Use it to eager-load relations.

```php
public function loadAuthRelations(): void
{
    $this->load('roles', 'workspace');
}
```

### `afterRegister(array $extraFields): void`

Called after the model is created. Receives fields that didn't match `$fillable`.

```php
public function afterRegister(array $extraFields): void
{
    Workspace::create(['name' => $extraFields['workspace_name']])
        ->users()
        ->attach($this->id);
}
```

---

## Registration — extra fields

Fields defined in `register_fields` that match the model's `$fillable` are assigned directly to the model. Fields that don't match are passed to `afterRegister()` and the `UserRegistered` event:

```php
// config
'register_fields' => [
    'firstname'      => 'required|string',
    'workspace_name' => 'required|string', // not in $fillable
],

// → firstname goes to the model
// → workspace_name goes to afterRegister($extraFields)
```

---

## Events

| Event | Payload | When |
|-------|---------|------|
| `UserRegistered` | `$model`, `$extraFields` | After successful registration |

```php
// In EventServiceProvider
use DantePiazza\LaravelAuth\Events\UserRegistered;

protected $listen = [
    UserRegistered::class => [
        CreateWorkspaceListener::class,
        SendWelcomeEmailListener::class,
    ],
];
```

---

## Traits reference

### `HasRefreshTokens`

| Method | Description |
|--------|-------------|
| `refreshTokens()` | Polymorphic relation to `PersonalRefreshToken` |
| `createRefreshToken(int $accessTokenId)` | Creates and stores a hashed refresh token |
| `currentRefreshToken()` | Returns the refresh token linked to the current access token |
| `removeCurrentSession()` | Deletes the current access token and its refresh token |
| `rotateAccessToken()` | Issues a new access token |

### `HasVerificationCode`

| Method | Description |
|--------|-------------|
| `verificationCodes()` | Polymorphic relation to `PersonalVerificationCode` |
| `generateVerificationCode(string $type, int $minutes)` | Generates and stores a hashed 6-digit code |
| `sendVerificationCode()` | Sends password recovery code via email |
| `sendEmailVerificationCode()` | Sends email verification code |
| `verifyEmail(string $code)` | Validates code and sets `email_verified_at` |
| `hasVerifiedEmail()` | Returns whether the email has been verified |
| `resetPassword(string $newPassword, string $code)` | Validates code and updates password |

---

## SSO (optional, opt-in)

Single Sign-On across microservices/SPAs sharing the same root domain (via wildcard cookie) or across different root domains (via an encrypted handshake). **Disabled by default** — nothing changes for existing installs until `AUTH_SSO_MODE` is set.

```env
AUTH_SSO_MODE=              # provider | consumer | empty (off)
AUTH_SSO_DEFAULT_TYPE=users # account_types key used to resolve login/cookie routes
AUTH_SSO_SECRET=            # dedicated secret for the cross-domain handshake, NOT your APP_KEY
AUTH_SSO_HANDSHAKE_TTL=60   # seconds, handshake token lifetime
AUTH_SSO_PROVIDER_URL=      # consumer side: base URL of the Provider
AUTH_SSO_ALLOWED_CONSUMERS= # provider side: comma-separated root domains allowlist
AUTH_SSO_RBAC_ENABLED=false
```

### SSO Provider

The app that issues tokens centrally. To let the refresh-token cookie work across a wildcard domain, set Laravel/Sanctum's own config (not package config):

```env
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=panel.yourdomain.com,ms1.yourdomain.com
```

Make sure clients send `withCredentials: true` and the app returns `Access-Control-Allow-Credentials: true` so the HttpOnly cookie travels across subdomains.

### SSO Consumer

A microservice/SPA that trusts the Provider instead of managing its own credentials:

- **Blade/backend consumer**: if there's no active `access_token` in memory/session, call `POST {provider}/v1/{type}/auth/refresh` — the shared HttpOnly cookie lets the Provider return a new token without asking for credentials again.
- **SPA consumer**: configure the HTTP client with `withCredentials: true`; on app init, call `POST {provider}/v1/{type}/auth/refresh` then `GET {provider}/v1/{type}/auth/current` before rendering; attach `Authorization: Bearer <access_token>` to subsequent requests.

### RBAC (opt-in payload extension)

Set `AUTH_SSO_RBAC_ENABLED=true` and implement `getSsoRoles()`/`getSsoPermissions()` on your model to have `login()`/`current()`/`register()` include an additional `rbac` key in the response. If the model doesn't implement these methods, or RBAC is disabled, no extra key is added — existing consumers see no difference.

Use the opt-in `sso.permission` middleware to gate internal routes:

```php
Route::middleware(['auth:sanctum', 'sso.permission:manage-infrastructure'])->group(...);
```

### Session management (device list / revocation)

Available regardless of SSO mode, useful for admin panels:

| Route | Description |
|-------|-------------|
| `GET v1/{type}/auth/sessions` | Lists the authenticated user's active refresh tokens |
| `DELETE v1/{type}/auth/sessions/{id}` | Revokes one session |
| `DELETE v1/{type}/auth/sessions` | Revokes all sessions |

Logging out now clears the refresh-token cookie using the same `domain`/`path`/`secure`/`same_site` it was issued with, so it's actually removed by the browser even under a wildcard domain.

### Cross-domain handshake (root domain ≠ Provider's domain)

For a root domain that isn't a subdomain of the Provider (so the wildcard cookie can't reach it), the package supports a redirect-based handshake using an **encrypted token in the query string**, sealed with the dedicated `AUTH_SSO_SECRET` (via a private `Illuminate\Encryption\Encrypter` instance, independent from `APP_KEY`).

Flow: `GET v1/sso/redirect` (consumer) → redirect to the Provider's login page with an encrypted `sso_handshake` token → after normal login, `GET v1/sso/handshake` (provider, authenticated) validates the consumer against `AUTH_SSO_ALLOWED_CONSUMERS`, issues fresh tokens, and redirects back → the consumer's own `laravel-auth.sso.callback` route decrypts the response and sets its own local refresh-token cookie.

The Provider never assumes a path or prefix (e.g. `api/`) for the consumer's callback: the consumer resolves its own `route('laravel-auth.sso.callback', absolute: true)` and sends that full URL inside the encrypted payload. The Provider validates that the callback URL's host actually matches the allowlisted consumer domain before redirecting, so a tampered/foreign callback host is rejected.

These routes are only registered when `AUTH_SSO_MODE` is `provider` or `consumer`.

**Conscious design tradeoffs (see `.agents/ROADMAP.md` for the full rationale):**
- A single shared secret is used across all enabled consumers, not a per-domain secret — simpler, intended for one team managing several projects/domains.
- The handshake token travels in the query string (GET), mitigated by short TTL and encryption — no plaintext data is exposed without the secret.
- There's no "used handshake token" registry (no single-use guarantee beyond the short TTL).

**Backlog (out of scope for this feature):**
- [ ] Move from a single shared secret to a per-consumer secret (table-backed) if the number of consumers grows or includes untrusted third parties.
- [ ] Track consumed handshake tokens for real single-use guarantees.
- [ ] Configure the Provider to avoid logging the full query string on the handshake endpoints.

---

## Migrations

Three tables are created automatically:

| Table | Description |
|-------|-------------|
| `personal_refresh_tokens` | Hashed refresh tokens linked to Sanctum access tokens |
| `personal_verification_codes` | Hashed OTP codes for password recovery and email verification |
| `personal_trusted_devices` | Device fingerprints for trusted device tracking |

To publish and customize:

```bash
php artisan vendor:publish --tag=laravel-auth-migrations
```

---

## Testing

```bash
composer install
vendor/bin/pest
```

SSO-specific coverage lives in `tests/SsoHandshakeTest.php` (encryptor + cross-domain handshake service), `tests/SsoRbacTest.php` (opt-in `rbac` payload), and `tests/SsoSessionsTest.php` (session listing/revocation and the logout cookie domain fix). They run against a dedicated `SsoTestCase` that boots with `AUTH_SSO_MODE=consumer`, since the handshake routes are only registered at boot time when that config is set.

The full suite is green (56/56).

---

## Dependencies

- PHP `^8.3`
- Laravel `^10.0 | ^11.0 | ^12.0`
- `laravel/sanctum` `^4.0`
- `dantepiazza/laravel-api-response`

---

## License

MIT
