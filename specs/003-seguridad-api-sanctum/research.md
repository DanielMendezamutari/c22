# Phase 0: Research & Technical Decisions — 003 Seguridad de la API

Hallazgos verificados en el código el 2026-10-16. Cada decisión cita la evidencia.

## 1. Mecanismo de autenticación: Sanctum existente (sin cambios de formato)

- **Decision**: Proteger las rutas con el guard `auth:sanctum`. No se cambia el login ni el formato del token.
- **Rationale**: `LoginPinUseCase` y `LoginWebUseCase` ya emiten tokens personales de Sanctum (`createToken()->plainTextToken`); la tabla `personal_access_tokens` existe (`2026_09_05_095634`); el modelo `Usuario` usa `HasApiTokens`; web (`frontend_web/src/plugins/axios.js:37`) y Flutter (`api_client.dart:27`) ya envían `Authorization: Bearer`. El test `CicloCompletoTurnoTest` ya autentica con Bearer y pasa.
- **Alternatives considered**: JWT (`firebase/php-jwt` / `tymon/jwt-auth`) — rechazado por el usuario el 2026-10-16: requiere librería nueva, cambiar ambos logins y re-login de todos los dispositivos, sin beneficio (un solo backend valida los tokens).
- **Nota**: `config/auth.php` apunta el provider a `App\Models\User`, pero el guard de Sanctum resuelve el `tokenable` polimórfico del token (provider `null`), por eso funciona con `Usuario`. No se toca.

## 2. Rechazo 401/403 siempre en JSON

- **Decision**: En `bootstrap/app.php`: `shouldRenderJsonWhen` para `api/*`, y render propio de `AuthenticationException` (401) y `AuthorizationException`/`AccessDeniedHttpException` (403) con el formato que ya usa la API: `{"success": false, "error": "<mensaje>"}`.
- **Rationale**: Sin esto, Laravel intenta redirigir a `route('login')`, que no existe en la API → 500 en peticiones sin `Accept: application/json` (p. ej. el bot o curl). El interceptor de axios (`axios.js:51`) solo mira el status 401, así que el cuerpo puede seguir la convención `success/error` que lee Flutter.
- **Alternatives considered**: `redirectGuestsTo(null)` solo — no cubre 403 ni unifica el formato.

## 3. Autorización por rol, no por abilities del token

- **Decision**: Middleware propio `EnsureUserHasRole` (alias `rol`) que recibe roles permitidos (`rol:admin,super_admin`). Además verifica que el usuario siga `activo` (FR-005). Los grupos de roles se definen como constantes en una sola clase (`App\Domain\Enums\GrupoRol` o similar) para no repetir listas en las rutas.
- **Rationale**: Los tokens de PIN se emiten **sin abilities** → Sanctum les da `['*']` y `tokenCan()` devolvería `true` para todo. Las abilities web derivan 1:1 del rol (`LoginWebUseCase::obtenerHabilidadesPorRol`), así que verificar el rol equivale a verificar las abilities y funciona igual para ambos logins (FR-017).
- **Alternatives considered**: middleware `abilities`/`ability` de Sanctum — rechazado por el `['*']` de los tokens PIN; Policies/Gates por modelo — más granular pero desproporcionado para 118 rutas en esta feature.

## 4. Restricción por sucursal (FR-016)

- **Decision**: Middleware `sucursal.sesion` en el grupo de barra: para `barman` y `cajera`, si la petición trae `sucursal_id` (input o parámetro de ruta) distinto de `usuario.sucursal_actual_id`, responde 403. `admin`, `super_admin` y roles sin sucursal asignada quedan exentos.
- **Rationale**: `LoginPinUseCase:102-104` ya fija `sucursal_actual_id` al entrar a barman/cajera. Un chequeo genérico evita tocar cada controlador.
- **Alternatives considered**: forzar `sucursal_id` desde la sesión en cada controlador — más correcto a largo plazo, pero toca ~15 controladores; queda como deuda.

## 5. Revocación de credenciales (FR-005)

- **Decision**: Al cambiar PIN/contraseña o al poner `activo = false` en `UserController`, ejecutar `$usuario->tokens()->delete()`. El middleware `rol` rechaza usuarios inactivos aunque el token exista.
- **Rationale**: Sin vencimiento automático (decisión de spec), la revocación explícita es la única forma de cortar acceso.

## 6. Secretos: `config()` en vez de `env()` y fallo cerrado

- **Decision**: Crear `config/services.php → whatsapp.webhook_secret` y `whatsapp.bot_http_port`; los middlewares y `WhatsAppOutboundDispatcherService` leen `config()`. Si el secreto está vacío: `Log::critical` y respuesta 503 "configuración incompleta" (nunca aceptar). Agregar `WHATSAPP_WEBHOOK_SECRET=` a `backend/.env.example`.
- **Rationale (hallazgo crítico)**: `deploy.sh` ejecuta `config:cache`. Con config cacheada, Laravel **no carga `.env`** y `env()` fuera de `config/` devuelve el valor por defecto. Por lo tanto en producción `VerifyWebhookSecret.php:14` y `WhatsAppOutboundDispatcherService.php:18` probablemente usan hoy el secreto quemado, sin importar el `.env`. Al pasar a `config()`, producción empezará a usar el valor real del `.env`: **el secreto del bot y el de Laravel deben coincidir antes de desplegar** (ver quickstart, paso de despliegue).
- **Fuera de alcance, pero detectado**: `GEMINI_API_KEY`, `GEMINI_MODEL`, `WHATSAPP_BOT_WEBHOOK_URL` y `WHATSAPP_GRUPO_RECAUDACIONES` también se leen con `env()` en `app/` (3 servicios de IA y `WhatsAppNotificationService`). Mismo riesgo con `config:cache`. Recomendado como feature aparte (004).
- **Alternatives considered**: mantener `env()` sin default — sigue roto con `config:cache`.

## 7. Clave maestra POS

- **Decision**: Eliminar el bloque de `VerifyBranchToken.php:24-31` (clave fija + reescritura de `token_acceso`). Solo vale el token de la sucursal en BD.
- **Rationale**: Hoy cualquiera con la clave fija se autentica como Casa22 y además sobrescribe su token real, dejando fuera al agente legítimo.
- **Impacto operativo**: Si el agente de Casa22 usa hoy la clave fija, debe configurarse con el `token_acceso` real de la sucursal (visible en `GET /pos/estado-casas`) antes de desplegar.

## 8. Puerta trasera del login web

- **Decision**: Eliminar `LoginWebUseCase.php:55-57` (contraseñas `123456`/`1234`/`9999`). Se mantiene el respaldo legítimo `pin_hash`.
- **Mitigación de bloqueo**: comando `seguridad:auditar-credenciales` (lista usuarios web activos sin `password_hash` ni `pin_hash`) y `usuarios:asignar-password {id}` (pide la contraseña oculta y guarda hash). Se ubican en `app/Console/Commands/` (auto-descubiertos en Laravel 11), no en `routes/console.php` (convención de CLAUDE.md).

## 9. Credenciales del agente POS en el repositorio

- **Decision**: `git rm --cached scripts/sync_agent/config.json` (versionado en 9247c8d y, corrección del 2026-10-16, **nunca estuvo en `.gitignore`**: se agrega); crear `config.example.json` con valores ficticios; quitar de `agent.py` los valores por defecto de `db_user`/`db_password`/`branch_token` (fallar con mensaje claro si faltan).
- **Rationale**: `agent.py:120-122` trae la contraseña real del SQL Server como default.
- **Hallazgo crítico (`/speckit-analyze`, C1)**: `backend/public/sync_agent.zip` estaba versionado y se servía en `https://c22.ribersoft.com/sync_agent.zip` (verificado HTTP 200) con un `config.json` con todos los campos reales (token de sucursal, usuario y contraseña SQL Server). Se retira del repo y de `public/` (T020a). El instalador nunca debe volver a `backend/public/`.
- **Fuera de alcance del código**: purgar el historial de git y rotar la contraseña `sa` de la caja → checklist operativo.

## 10. Bot sin secreto por defecto

- **Decision**: `bot.js:25` sin fallback; si `WHATSAPP_WEBHOOK_SECRET` falta, `console.error` + `process.exit(1)`. El watchdog `verificar_y_levantar.sh` lo reintentará y el log mostrará la causa.

## 11. Pruebas

- **Decision**: Nueva suite `tests/Feature/Seguridad/` con: matriz de acceso data-driven (una muestra por grupo de rutas × rol → 401/403/2xx), puertas traseras, clientes de máquina, revocación. Los tests existentes que llaman rutas protegidas sin token se adaptan con `Sanctum::actingAs()`.
- **Rationale**: Constitución, Governance §3; FR-019/FR-020.
