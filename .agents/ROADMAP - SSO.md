# 🗺️ Roadmap: Integración de SSO en la Red de Microservicios

Este documento establece el plan de integración para conectar el Gateway central (api.clousis.net) con los microservicios de infraestructura (payments, hostbi, accounting, etc.) utilizando la autenticación unificada de dantepiazza/laravel-auth.

---

## 1. Arquitectura de Autenticación

Los administradores del sistema no utilizan un modelo separado; son usuarios estándar (tipo user) que cuentan con permisos elevados sobre la infraestructura.

El flujo de autenticación centralizada funciona de la siguiente manera:
1. Las peticiones de autenticación viajan desde las SPAs o vistas Blade hacia el Gateway Central (api.clousis.net/v1/user).
2. El AuthService en el Gateway emite el Access Token vía Sanctum y rota la cookie HttpOnly del Refresh Token con alcance wildcard en el dominio (.clousis.net/dev/local).
3. Cualquier subdominio interno valida el estado de la sesión usando dicha cookie compartida.

---

## 2. Fases de Integración

### Fase 1 — Configuración del Gateway Central (api.clousis.net)
1. Configuración de Dominio Global en .env:
   - SESSION_DOMAIN=.clousis.net
   - SESSION_SECURE_COOKIE=true
   - SESSION_SAME_SITE=lax
   - SANCTUM_STATEFUL_DOMAINS=panel.clousis.net,payments.clousis.net,hostbi.clousis.net,panel.clousis.com
2. Endpoint Unificado:
   Toda autenticación se canaliza por POST /v1/user/auth/login y POST /v1/user/auth/refresh. La autorización a rutas internas se resuelve por roles/permisos.

### Fase 2 — Integración en Microservicios Blade (/{ms}/internal)
1. Renovación Transparente de Token:
   Al ingresar a {ms}.clousis.net/internal, si no existe un access_token activo en memoria o sesión, el frontend/Blade invoca POST api.clousis.net/v1/user/auth/refresh. La cookie HttpOnly enviada automáticamente permite al Gateway devolver un nuevo token sin solicitar credenciales.
2. Protección de Rutas Internas (RBAC):
   - Middleware requerido: ['auth:sanctum', 'can:manage-infrastructure']
   - Prefijo de rutas: /v1/internal
3. Control de Accesos No Autorizados:
   Usuarios autenticados sin permisos de infraestructura reciben una respuesta 403 Forbidden.

### Fase 3 — Integración en SPA Centralizada (panel.clousis.net)
1. Configuración del Cliente HTTP:
   Configurar el interceptor HTTP en Angular con withCredentials: true.
2. Flujo de Inicialización (App Init):
   - La SPA ejecuta POST api.clousis.net/v1/user/auth/refresh al cargar.
   - Obtiene el access_token y consulta GET api.clousis.net/v1/user/auth/current para validar permisos de superadministrador antes de renderizar la interfaz.
3. Consumo de APIs:
   La SPA envía las peticiones adjuntando la cabecera Authorization: Bearer <access_token> a cada microservicio.