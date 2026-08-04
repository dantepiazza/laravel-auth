# Roadmap: Evoluci�n SSO & Cookie Wildcard

> **Estado (2026-08-04): implementado y desplegable.** Las 4 fases completas, suite completa en verde (61/61 — 56 de la implementación original + 5 agregados desde, incluyendo los hallazgos preexistentes no relacionados a SSO que se resolvieron de paso — ver `REPORTS.md`). Cobertura de tests en `tests/SsoHandshakeTest.php`, `tests/SsoRbacTest.php`, `tests/SsoSessionsTest.php`. Documentación de uso en el `README.md` del paquete, sección "SSO (optional, opt-in)". El único pendiente de Fase 4 (default roto de `provider_login_route`) quedó resuelto — ver Backlog.

## Objetivo

Agregar al paquete Composer `dantepiazza/laravel-auth` (ya existente, con `AuthService` y `PersonalRefreshToken` implementados) una **nueva caracter�stica opcional** de Single Sign-On mediante tokens Sanctum y cookies de refresco compartidas en dominios wildcard (`.dominio.com`).

**Restricci�n no negociable:** esto es una feature nueva, no un refactor. El paquete es consumido por m�ltiples proyectos Laravel v�a Composer, por lo que:

- No se debe modificar el comportamiento por defecto existente. Todo lo nuevo debe ser **opt-in** (desactivado a menos que el `.env` del proyecto consumidor lo habilite expl�citamente).
- No se deben cambiar firmas de m�todos p�blicos, contratos de interfaces, ni el shape de las respuestas actuales de `current()`/`login()` salvo que sea de forma aditiva y retrocompatible (agregar campos opcionales, nunca quitar o renombrar los existentes).
- No se deben agregar dependencias nuevas obligatorias; si se necesita algo nuevo, debe ser opcional o ya estar disponible en un proyecto Laravel + Sanctum est�ndar.
- Cualquier migraci�n, config publicable o middleware nuevo debe convivir sin romper instalaciones existentes que actualicen la versi�n del paquete (backward compatible por defecto: sin tocar `.env`, el proyecto debe seguir funcionando exactamente igual que antes).
- Las variables de entorno nuevas deben tener defaults seguros que preserven el comportamiento actual (SSO desactivado) si no se configuran.

El paquete debe soportar dos modos configurables por entorno:

- **Modo Provider (Gateway):** autentica y emite tokens centralizados.
- **Modo Consumer (Microservicio/SPA):** redirige al login centralizado, valida/decodifica el token usando una firma/secret compartida (incluso para dominios cruzados o externos) y gestiona la cookie/refresco local.

El dominio base y las configuraciones de cookies/tokens deben ser din�micos y parametrizables v�a `.env`, sin hardcodear ning�n dominio espec�fico en el c�digo del paquete.

Se debe documentar el uso t�cnico del paquete tanto para el lado del Gateway como para el lado de los microservicios/SPA consumidores.

---

## Estado actual

- El paquete ya cuenta con arquitectura polim�rfica y soporte de tokens de acceso.
- `PersonalRefreshToken` ya existe y gestiona cookies HttpOnly.
- `AuthService` ya existe; esta es una **evoluci�n**, no una implementaci�n desde cero.

---

## Fase 1 � Configuraci�n de Gateway y Cookie Wildcard

**Objetivo:** habilitar que el Gateway emita cookies de refresco v�lidas en todo el dominio wildcard, parametrizadas por `.env`.

1. Revisar `AuthService` para garantizar que la emisi�n de la cookie `refresh_token` respete `session.domain` (ej. `.dominio.com`), `session.secure` y `session.same_site` definidas por la app host.
2. Variables de entorno a soportar (nombres de ejemplo, ajustar al est�ndar del proyecto):
   - `SESSION_DOMAIN=.dominio.com`
   - `SESSION_SECURE_COOKIE=true`
   - `SESSION_SAME_SITE=lax`
   - `SANCTUM_STATEFUL_DOMAINS=sub1.dominio.com,sub2.dominio.com,...`
3. Definir (o confirmar) el endpoint unificado de autenticaci�n en el Gateway, ej. `POST /v1/auth/login` y `POST /v1/auth/refresh`.
4. **CORS & Credentials:** documentar las cabeceras HTTP necesarias para que la cookie HttpOnly viaje entre subdominios (`withCredentials: true` del lado cliente, `Access-Control-Allow-Credentials` del lado servidor).

## Fase 2 � RBAC e Integraci�n en Consumidores (microservicios / SPA)

**Objetivo:** propagar roles/permisos y estandarizar c�mo los consumidores validan sesi�n.

1. Extender el payload de `current()` y `login()` para incluir opcionalmente los roles/permisos del modelo autenticado, solo si el modelo implementa la interfaz/trait correspondiente. Si el modelo consumidor no la implementa, el payload debe quedar exactamente igual que hoy (sin campos vac�os ni nulls agregados de m�s).
2. Agregar middlewares **nuevos** (no modificar los existentes) para filtrar rutas por permiso expl�cito (ej. `can:manage-infrastructure`), pensados para que el proyecto consumidor los registre si los necesita, devolviendo `403 Forbidden` cuando corresponda.
3. **Renovaci�n transparente de token (consumidor tipo Blade/backend):** si no hay `access_token` activo en memoria/sesi�n, el consumidor invoca `POST {gateway}/v1/auth/refresh`; la cookie HttpOnly enviada autom�ticamente permite al Gateway devolver un nuevo token sin pedir credenciales.
4. **Consumidor tipo SPA:**
   - Configurar el cliente HTTP con `withCredentials: true`.
   - En la inicializaci�n de la app: `POST {gateway}/v1/auth/refresh` ? obtener `access_token` ? `GET {gateway}/v1/auth/current` para validar permisos antes de renderizar.
   - Las siguientes peticiones a APIs adjuntan `Authorization: Bearer <access_token>`.

## Fase 3 � Revocaci�n Centralizada de Sesiones

**Objetivo:** que el logout sea efectivo en todo el dominio wildcard.

1. Garantizar que `logout` invalide el registro correspondiente en `PersonalRefreshToken` y expire la cookie en todo el dominio wildcard configurado.
2. Implementar m�todos para listar y revocar tokens de refresco activos por usuario (gesti�n de dispositivos), pensado para uso desde paneles de administraci�n.

---

## Fase 4 � SSO entre dominios ra�z distintos (cross-domain, no solo subdominios)

**Objetivo:** permitir que un dominio ra�z distinto al del Gateway (ej. `otrodominio.com` autentic�ndose contra `gateway.com`) pueda loguearse sin depender de cookie wildcard, ya que esta �ltima solo funciona dentro de un mismo dominio ra�z.

**Mecanismo (handshake con token cifrado, no cookie):**

1. `otrodominio.com` redirige al usuario a la pantalla de login de `gateway.com` adjuntando un token cifrado (query string) generado con el **secret compartido**. La URL de destino no debe quedar hardcodeada: debe resolverse v�a la convenci�n de rutas nombradas de Laravel (ej. `route('login')`), tanto del lado de quien redirige (que arma la URL del Gateway a partir de su config, no de un string fijo) como del lado del Gateway (que expone su login bajo la ruta nombrada `login`, que es la que Laravel usa por defecto para redirects de `Auth::routes()` / middleware `auth`). As�, si un proyecto cambia el path de su pantalla de login (`/login`, `/acceso`, `/auth/login`, etc.), el paquete lo sigue resolviendo bien sin tocar c�digo.
   El cifrado del token es sim�trico (no solo firma/HMAC) � sin el secret el contenido no es legible, aunque el token viaje expuesto en la URL.
2. `gateway.com` descifra el token con el mismo secret. Si puede descifrarlo, el secret es v�lido.
3. `gateway.com` valida que `otrodominio.com` est� en la allowlist de dominios permitidos definida en su `.env`.
4. Si es v�lido, `gateway.com` autentica al usuario (login normal) y genera un **token de handshake de corta duraci�n** (ej. 30-60s, de un solo prop�sito), lo cifra con el mismo secret y redirige de vuelta a `otrodominio.com` con ese token en query string.
5. `otrodominio.com` descifra el token con el secret compartido, obtiene el token real de sesi�n, y lo setea como su propia cookie local.
6. A partir de ah�, `otrodominio.com` refresca siempre contra `gateway.com` v�a `POST /v1/auth/refresh` (mismo mecanismo ya existente para consumidores), y ese refresh ya emite tokens con el TTL est�ndar del Gateway (no el TTL corto del handshake).

**Decisiones de dise�o conscientes (documentar en el paquete, no dejarlas impl�citas):**

- **Secret �nico compartido entre todos los consumidores habilitados**, en vez de un secret por dominio o tabla de secrets. Es una decisi�n deliberada para mantener la implementaci�n simple, pensada para el caso de uso de "mismo equipo gestionando varios proyectos/dominios". Si el n�mero de consumidores crece o pasan a ser terceros no confiables, escalar a secret por consumidor (v�a tabla) queda como �tem de backlog, no como parte de esta fase.
- El token de handshake viaja en query string (GET), no en el body. Es aceptable porque va cifrado (in�til sin el secret) y de corta duraci�n (mitiga replay). Como mejora de bajo costo a backlog: configurar el Gateway para no loguear el query string completo en sus logs de acceso.
- No se implementa un registro de tokens de handshake "ya usados" (no hay componente stateful extra para eso). El TTL corto es el �nico mitigante de replay para esta fase; si en el futuro se requiere garant�a de un solo uso, pasa a backlog.

## Notas de implementaci�n

- Ning�n dominio, endpoint o nombre de proyecto espec�fico debe quedar hardcodeado en el paquete: todo v�a `.env` o config publicable.
- El modo SSO (Provider/Consumer) debe estar **desactivado por defecto**. Un proyecto que ya usa el paquete hoy y actualiza de versi�n no debe notar ning�n cambio de comportamiento hasta que configure expl�citamente las nuevas variables.
- Toda esta funcionalidad se implementa como c�digo adicional (nuevos m�todos, nuevos middlewares, nueva config), no como modificaci�n de los flujos actuales de auth ya existentes en el paquete.
- Los administradores del sistema son usuarios est�ndar con permisos elevados (no un modelo separado).
- Al documentar, separar claramente la gu�a "lado Gateway" de la gu�a "lado Consumidor", ya que la configuraci�n e interceptores difieren, y aclarar en el README que es una feature opt-in.

## Backlog futuro (fuera de alcance de esta feature)

- [ ] Pasar de secret �nico compartido a secret por dominio consumidor (tabla en vez de valor fijo en `.env`), si el n�mero de consumidores crece o incluye terceros no confiables.
- [ ] Registro de tokens de handshake consumidos (single-use real v�a storage), si el TTL corto deja de ser mitigante suficiente.
- [ ] Configurar el Gateway para no loguear el query string completo en los logs de acceso HTTP.

- [x] **Resuelto (2026-08-04):** `provider_login_route` ahora resuelve `'login'` por defecto (antes caía a `'laravel-auth.login'`, la ruta API del propio paquete — un redirect de navegador ahí daba 405, el bug real detrás de "Handshake cross-domain roto"). Había dos defaults distintos para lo mismo (uno en `config/laravel-auth.php`, otro hardcodeado como segundo argumento de `config()` dentro de `SsoHandshakeService::buildProviderRedirectUrl()`) — alineados ambos a `'login'`. Cada host sigue pudiendo overridear con `AUTH_SSO_PROVIDER_LOGIN_ROUTE` si su ruta de login no se llama `login` (ver README, sección "Cross-domain handshake"). Tests: `tests/SsoHandshakeTest.php` cubre el default nuevo explícitamente; `tests/SsoTestCase.php` registra una ruta `login` de prueba para poder resolverlo. Suite completa: 61/61 en verde.

## Pendiente de definir antes de ejecutar

- [ ] Confirmar nombres reales de env vars si ya existe una convenci�n en el paquete.
- [ ] Confirmar nombre real de los endpoints de auth (`/v1/auth/*` es un ejemplo).
- [ ] Confirmar el permiso/gate exacto a usar en el middleware de infraestructura.