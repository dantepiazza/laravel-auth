# CLAUDE.md

## Instrucciones de origen

- **Idioma del código**: todo lo que se escribe como código va en **inglés**, salvo que se pida explícitamente lo contrario. La conversación con el usuario, comentarios y todo los textos que son visibles por un usuario son en español.
  > Nota de estado: hoy varios mensajes de `ResponseException`/`ApiResponse` en el código quedaron en español (se escribieron antes de que esta regla quedara explícita). Revisar y traducir progresivamente — ver `REPORT.md`.
- **`.agents`**: Carpeta dedicada para la IA a modo repo y docs de trabajo
- **`.agents/REPORTS.md`**: Listado de hallazgos que necesitan revisión o respuesta humana (bugs, inconsistencias, decisiones de diseño pendientes). Mantenerlo limpio — sacar de ahí lo que ya se resolvió.
- **`.agents/ROADMAP.md`**: Acciones concretas pendientes antes de producción o bien para nuevas etapas. Mantenerlo como lista corta y accionable, no como changelog.
- **`.agents/CONTEXT.md`**: Contexto general para agentes de IA, la inetncion es que al leer este docuemnto cualquier agente entienda lo qu ese trabajo y los detalles importantes  atener en cuenta.
- **tests**: No ejecutar tests entre fases de desarrollo, dejarlos para lo ultimo del proyecto

## Proyecto
Salvo que el proyecto ya tenga definida alguna convencion diferente entonces:
- **Respuestas de la API**: Siempre se arman siempre con el paquete `dantepiazza/laravel-api-response` (`$this->api->success()/created()/records()/...`). No inventar formatos de respuesta a mano.
- **Manejo de errores en Services**: usar `DantePiazza\LaravelApiResponse\Exceptions\ResponseException` dentro de los Services (no en los Controllers) para casos de error de negocio. `ResponseException` tiene su propio `render()` y se convierte solo en la respuesta JSON correcta — así los Controllers quedan sin `if` de manejo de error, solo orquestando Request → Service → Resource.
- **Emparejamiento de base**: Cuando cambie la version del paquete `dantepiazza/laravel-base` (si es que se usa) y comparar sus stubs (`bootstrap/app.php`, `config/*`, `routes/api.php`, `app/Http/Controllers/Controller.php`) contra los archivos reales de este proyecto, para detectar drift — fixes de seguridad u otros cambios que se hayan hecho de un lado y no del otro.

## Entorno
- **composer**: En esta pc composer tiene un problema de ssl porque el antiviru frena esto, cuanod sea enceario avisamre y desbloque el antivirus

## Paquetes comunes

Mis proyecto laravel llevan estos paquetes por lo general
- **`dantepiazza/laravel-base`**: contiene los stubs alineados para que todos los proyectos mantengan la misma estructura (incluyendo scaffolding base, config de Scribe, assets de Docker).
- **`dantepiazza/laravel-api-response`**: define la convención estándar para exponer respuestas de API en todos los MS.
- **`dantepiazza/laravel-auth`**: Es un paquete de autenticacion polimorfico que utiliza sanctum. ya trae funciones como, login, register, password_reset, y todo el flujo de autenticacion listo.
- **`dantepiazza/laravel-chatwoot`**: Es una base para integrar bots con Chatwoot
- **`dantepiazza/geo-location-data`**: Un repositorio que ofrese una DB json cacheada de localidades, provicnias y paises con ID unicos.
- **`dantepiazza/laravel-importer`**: En desarrollo. Es un importador masivo polimorfico similar a los importadores de CRM solo que la parte backend
- **`dantepiazza/laravel-kyc`**: Es un paquete polimorfico para verificacion KYC multi drive auqnue de momento solo se integra con Didit. Maneja "dispotivios" permitidos.
- **`clousis/microservices`**: Es un pauqte privado unicamente para utilizar en apps y microservicios de Clousis para comunicarse entre si, ademas de palnetar una base comun para todos, depende de `dantepiazza/laravel-base` y `dantepiazza/laravel-api-response`.
- **`clousis/wordpress-agent`**: Es un paquete de monitorizacion para wordpress, basicamente es un plugin que espone API que informan sobre estado del proyecto y ademas realizar acciones de mantenimiento.
- **`clousis/laravel-agent`**: Es un paquete de monitorizacion para conectarlo a un proyecto laravel exponienod API que informan sobre estado del proyecto.