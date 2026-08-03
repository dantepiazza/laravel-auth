# Reports — hallazgos pendientes de revisión humana

_Sin pendientes abiertos al 2026-08-02. Ver historial de git para el detalle de lo resuelto._

## Resuelto (2026-08-02)

Al cerrar la feature de SSO se corrió `vendor/bin/pest` por primera vez y aparecieron 7 fallos preexistentes (no relacionados a SSO, confirmados con `git stash` sobre el código original). Se investigaron y arreglaron:

1. **Registro fallaba con `NOT NULL constraint` / no detectaba duplicados limpiamente** — `RegisterRequest` solo validaba `password`; cualquier otro campo no declarado en `register_fields` (per `account_type`) se descartaba silenciosamente antes de llegar al modelo, y no había regla de unicidad para el campo `identity`. Fix: `RegisterRequest` ahora agrega automáticamente `required` + `unique` para la columna `identity` configurada (a menos que el proyecto ya declare su propia regla en `register_fields`, que sigue teniendo prioridad). Se documentó en el README que cualquier otra columna `NOT NULL` del modelo (ej. `name`) debe declararse explícitamente en `register_fields` — es una limitación de diseño esperada de un paquete polimórfico que no puede inferir el schema del modelo consumidor.
2. **Test de cookie con `BadMethodCallException`** — el método de aserción se renombró en la versión de Laravel/Testbench instalada (`assertCookieIsNotExpired` → `assertCookieNotExpired`). Solo era un test desactualizado, no un bug del paquete.
3. **`auth.verify-email` no bloqueaba aunque `blocking=true`** — el array de middleware de las rutas protegidas se armaba una sola vez al boot de la app leyendo `config('laravel-auth.email_verification.blocking')`; cambiar ese config después (en runtime, o en un test) no tenía efecto porque las rutas ya estaban registradas. Es la misma clase de bug que encontramos con las rutas de SSO (`sso.mode` también se lee al boot). Fix: el chequeo de `blocking` se movió adentro del propio middleware `VerifyEmail` (retorna `$next($request)` de inmediato si está desactivado), y el middleware ahora se registra siempre en el grupo protegido — el toggle funciona en runtime sin reiniciar la app.
4. **`resendVerificationCode()` no fallaba si el email ya estaba verificado** — faltaba la validación de negocio. Fix: se agregó un chequeo de `hasVerifiedEmail()`/`email_verified_at` que lanza `ResponseException` (422) si ya está verificado.

Ver también el `README.md` — sección "SSO (optional, opt-in)" para la feature que motivó esta revisión, y la nota sobre `register_fields` cerca de la config de `account_types`.
