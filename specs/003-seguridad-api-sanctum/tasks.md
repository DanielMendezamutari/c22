# Tasks: Seguridad de la API — Autenticación Obligatoria, Eliminación de Puertas Traseras y Autorización por Rol

**Input**: Design documents from `specs/003-seguridad-api-sanctum/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/matriz-acceso.md, quickstart.md

**Tests**: SÍ — pedidos en FR-019/FR-020 y obligatorios por Constitución (Governance §3).

**Organization**: por historia de usuario; cada fase es desplegable y verificable por separado.

## Format: `[ID] [P?] [Story] Description`
- **[P]**: paralelizable (archivos distintos, sin dependencias pendientes) → candidata a delegar a Codex en otro worktree.
- Rutas relativas a la raíz del repo.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configuración centralizada de secretos.

- [ ] T001 Agregar bloque `'whatsapp' => ['webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'), 'bot_http_port' => (int) env('BOT_HTTP_PORT', 3001)]` en `backend/config/services.php` (sin valor por defecto para el secreto)
- [ ] T002 [P] Agregar `WHATSAPP_WEBHOOK_SECRET=` y `BOT_HTTP_PORT=3001` en `backend/.env.example`, y `WHATSAPP_WEBHOOK_SECRET=` en `backend/phpunit.xml` (`<env name="WHATSAPP_WEBHOOK_SECRET" value="test-secret"/>`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Piezas que usan todas las historias. ⚠️ Ninguna historia empieza antes de terminar esta fase.

- [ ] T003 Crear `backend/app/Domain/Enums/GrupoRol.php` (PHP puro) con constantes `ADMIN = ['admin','super_admin']`, `BARRA = ['barman','garzon','cajera','admin','super_admin']`, `LECTURA_AUDITORIA = ['dueno','contadora','auxiliar_contable','admin','super_admin']`, `CONTABLE = ['contadora','admin','super_admin']` y un helper `GrupoRol::middleware(array $roles): string` que devuelva `'rol:'.implode(',', $roles)` (ver `contracts/matriz-acceso.md`)
- [ ] T004 [P] Crear `backend/app/Infrastructure/Http/Middleware/EnsureUserHasRole.php` con firma `handle(Request $request, Closure $next, string ...$roles)`: obtiene `$request->user()`; si es null o `activo == false` → lanzar `AuthenticationException`; **si `$roles` está vacío, solo exige usuario activo** (uso: `->middleware('rol')` para "cualquier sesión válida"); si no está vacío y `rol` no está en `$roles` → lanzar `AccessDeniedHttpException('No tiene permiso para esta operación')`
- [ ] T005 [P] Crear `backend/app/Infrastructure/Http/Middleware/EnsureSucursalDeSesion.php`: solo para roles `barman` y `cajera` con `sucursal_actual_id` no nulo; si `$request->input('sucursal_id')` o `$request->route('sucursal_id')` existe y difiere → `AccessDeniedHttpException('Operación fuera de su sucursal')`
- [ ] T006 Registrar aliases `rol` → `EnsureUserHasRole` y `sucursal.sesion` → `EnsureSucursalDeSesion` en `backend/bootstrap/app.php`; en `withExceptions`: `shouldRenderJsonWhen(fn ($r) => $r->is('api/*') || $r->expectsJson())`, render de `AuthenticationException` → 401 `{"success":false,"error":"No autenticado"}` y de `AccessDeniedHttpException`/`AuthorizationException` → 403 `{"success":false,"error":<mensaje>}` para `api/*`

**Checkpoint**: `php artisan test` sigue en verde (aún no hay rutas protegidas).

---

## Phase 3: User Story 1 - Bloqueo de Acceso Anónimo (Priority: P1) 🎯 MVP

**Goal**: Ninguna ruta, salvo públicas y de máquina, responde sin credencial válida.
**Independent Test**: Escenario 1 de `quickstart.md` (`GET /usuarios` sin token → 401 JSON; `GET /auth/sucursales` → 200).

### Tests for User Story 1

- [ ] T007 [P] [US1] Crear `backend/tests/Feature/Seguridad/AccesoAnonimoTest.php`: data provider con una ruta de muestra por grupo (BARRA, LECTURA_AUDITORIA, CONTABLE, ADMIN y `auth/me`) → 401 con cuerpo `{"success":false,"error":"No autenticado"}`, también **sin** header `Accept`; las 4 públicas no devuelven 401; token inválido → 401
- [ ] T007a [P] [US1] Crear `backend/tests/Feature/Seguridad/RutasProtegidasTest.php` (H2, hace cumplir FR-003/SC-001): recorrer `Route::getRoutes()` filtrando URIs `api/*` y exigir que cada ruta tenga al menos uno de los middlewares `auth:sanctum`, `webhook.secret`, `branch.token` o `bot.o.sesion`, salvo una lista blanca explícita con las 4 rutas públicas (`api/v1/auth/sucursales`, `api/v1/auth/sucursales-usuarios`, `api/v1/auth/login-pin`, `api/v1/auth/web/login`). El mensaje de fallo debe listar las rutas sin protección. Además verificar que el total de rutas `api/*` sea 118 (si cambia, el test obliga a clasificar la ruta nueva en `contracts/matriz-acceso.md`)

### Implementation for User Story 1

- [ ] T008 [US1] Reestructurar `backend/routes/api.php` dentro de `prefix('v1')`: (a) grupo público con las 4 rutas de `auth/*`; (b) rutas de máquina tal como están (`webhook.secret`, `branch.token`); (c) `Route::middleware('auth:sanctum')->group(...)` con **todas** las demás rutas, ordenadas en subgrupos comentados según `contracts/matriz-acceso.md` (BARRA, LECTURA_AUDITORIA, CONTABLE, ADMIN) pero **todavía sin** middleware `rol` (se agrega en US4). Dejar `GET whatsapp/bot-status` fuera del grupo por ahora (se resuelve en US3). Verificar con `php artisan route:list --path=api` que el total sigue siendo 118
- [ ] T009 [P] [US1] Adaptar tests existentes que llaman rutas protegidas sin token usando `Sanctum::actingAs($usuarioAdmin)` (crear el usuario `admin` en `setUp` si no existe) en `backend/tests/Feature/AuditoriaTest.php`, `ConciliacionTrianguladaResilienteTest.php`, `LiquidacionJornalGarzonTest.php`, `WhatsAppWebGestionTest.php`, `WhatsAppAuditoriaCruceYDespachoRecaudoTest.php` y `WhatsAppWebhookTest.php` (solo las llamadas a rutas de persona). Además, en `WhatsAppWebhookTest.php` y `WhatsAppWebGestionTest.php` reemplazar `putenv("WHATSAPP_WEBHOOK_SECRET=...")` y `$_ENV[...]` del `setUp` por `config(['services.whatsapp.webhook_secret' => $this->secret])`: tras T014 el middleware lee `config()`, y `putenv` posterior al arranque de la app no la modifica (H1)
- [ ] T010 [US1] Ejecutar `cd backend && php artisan test` → suite completa en verde (T007 + existentes)

**Checkpoint**: MVP desplegable — acceso anónimo cerrado; panel web, app y bot siguen funcionando.

---

## Phase 4: User Story 2 - Eliminación de Puertas Traseras y Secretos (Priority: P1)

**Goal**: Cero contraseñas fijas, clave maestra o secretos reales en código y repo; fallo cerrado si falta un secreto.
**Independent Test**: Escenario 2 de `quickstart.md`.

### Tests for User Story 2

- [ ] T011 [P] [US2] Crear `backend/tests/Feature/Seguridad/PuertasTraserasTest.php`: (1) usuario `super_admin` con `password_hash` de otra contraseña → `POST auth/web/login` con `123456`, `1234` y `9999` rechazado; con su contraseña real → token; (2) `POST sync/pos-transacciones` con la antigua clave maestra → 401 y `token_acceso` de la sucursal sin cambios; (3) con `config(['services.whatsapp.webhook_secret' => null])` → `POST webhook/whatsapp` con cualquier secreto → 503

### Implementation for User Story 2

- [ ] T012 [P] [US2] Eliminar el respaldo de contraseñas fijas (`elseif ($password === '123456' || ...)`) en `backend/app/Application/UseCases/Auth/LoginWebUseCase.php`, conservando `password_hash` y `pin_hash`
- [ ] T013 [P] [US2] Eliminar el bloque "Fallback maestro" (clave fija + reescritura de `token_acceso`) en `backend/app/Infrastructure/Http/Middleware/VerifyBranchToken.php`
- [ ] T014 [P] [US2] En `backend/app/Infrastructure/Http/Middleware/VerifyWebhookSecret.php` leer `config('services.whatsapp.webhook_secret')`; si está vacío → `Log::critical(...)` y 503 `{"success":false,"error":"Configuración de seguridad incompleta"}`; mantener `hash_equals`
- [ ] T015 [P] [US2] En `backend/app/Services/WhatsAppOutboundDispatcherService.php` leer secreto y puerto desde `config('services.whatsapp.*')`; si el secreto está vacío, no enviar y devolver error con `Log::critical`
- [ ] T016 [P] [US2] Crear `backend/app/Console/Commands/AuditarCredencialesCommand.php` (`seguridad:auditar-credenciales`): tabla de usuarios activos con rol web (`super_admin`, `admin`, `dueno`, `contadora`, `auxiliar_contable`) sin `password_hash` ni `pin_hash`; código de salida 1 si hay alguno
- [ ] T017 [P] [US2] Crear `backend/app/Console/Commands/AsignarPasswordCommand.php` (`usuarios:asignar-password {usuario_id}`): pide contraseña con `secret()` dos veces, mínimo 8 caracteres, guarda `Hash::make` en `password_hash` y revoca los tokens del usuario
- [ ] T018 [P] [US2] En `scripts/whatsapp_bot/bot.js` quitar el valor por defecto de `WEBHOOK_SECRET`; si falta, `console.error('[BOT] WHATSAPP_WEBHOOK_SECRET no configurado')` y `process.exit(1)` antes de conectar
- [ ] T019 [P] [US2] En `scripts/sync_agent/agent.py` quitar los valores por defecto de `db_user`, `db_password` y `branch_token` (config por defecto y `.get(...)`); si faltan, terminar con mensaje claro
- [X] T020 [US2] Crear `scripts/sync_agent/config.example.json` con los 8 campos y valores ficticios (`"db_password": "CAMBIAR"`, `"branch_token": "TOKEN_DE_LA_SUCURSAL"`), ejecutar `git rm --cached scripts/sync_agent/config.json` (el archivo local se conserva) y agregar `scripts/sync_agent/config.json` y los archivos de estado del agente a `.gitignore` (NO estaban ignorados)
- [X] T020a [US2] (C1, urgente) Retirar `backend/public/sync_agent.zip` del repositorio (`git rm --cached`) y de la carpeta pública: contenía `config.json` con credenciales reales y era descargable sin login en producción. Copia local movida a `scripts/sync_agent/sync_agent.zip` (ignorada por `*.zip`). Regenerar el instalador solo con `config.example.json`, nunca dentro de `backend/public/`
- [ ] T021 [US2] Ejecutar `cd backend && php artisan test` → verde

**Checkpoint**: US1 + US2 cierran el acceso externo; requiere el checklist de despliegue (pasos 1–3) antes de producción.

---

## Phase 5: User Story 3 - Clientes de Máquina Operativos (Priority: P1)

**Goal**: Bot y POS siguen funcionando con su secreto; `bot-status` acepta secreto o sesión; un secreto no abre rutas de persona.
**Independent Test**: Escenario 3 de `quickstart.md`.

### Tests for User Story 3

- [ ] T022 [P] [US3] Crear `backend/tests/Feature/Seguridad/ClientesMaquinaTest.php`: con `X-Webhook-Secret` correcto, las 4 rutas del bot no dan 401; `GET whatsapp/bot-status` → 200 con secreto, 200 con `Sanctum::actingAs(admin)`, 401 sin nada; `POST usuarios` con secreto y sin Bearer → 401; `POST sync/pos-transacciones` con `token_acceso` válido → no 401

### Implementation for User Story 3

- [ ] T023 [US3] Crear `backend/app/Infrastructure/Http/Middleware/VerifyWebhookSecretOrSanctum.php` (alias `bot.o.sesion` en `backend/bootstrap/app.php`): pasa si `X-Webhook-Secret` coincide con `config('services.whatsapp.webhook_secret')` (con `hash_equals`, fallo cerrado si no está configurado) o si `auth('sanctum')->user()` es un usuario activo; si no, `AuthenticationException`
- [ ] T024 [US3] Aplicar `->middleware('bot.o.sesion')` a `GET whatsapp/bot-status` en `backend/routes/api.php`
- [ ] T025 [US3] Ejecutar `cd backend && php artisan test` → verde

**Checkpoint**: Las 118 rutas tienen protección; P1 completo.

---

## Phase 6: User Story 4 - Autorización por Rol (Priority: P2)

**Goal**: Cada rol accede solo a su grupo; barman/cajera limitados a su sucursal; credenciales revocadas al desactivar o cambiar PIN.
**Independent Test**: Escenario 4 de `quickstart.md` y matriz completa.

### Tests for User Story 4

- [ ] T026 [P] [US4] Crear `backend/tests/Feature/Seguridad/MatrizAccesoTest.php`: data provider (rol × ruta de muestra por grupo) con `Sanctum::actingAs` y aserción 403 vs no-403 según `contracts/matriz-acceso.md`; incluye rol desconocido (`'otro'`) → 403 salvo `auth/me`; barman con `sucursal_id` ajeno en `POST turnos/abrir` → 403 "Operación fuera de su sucursal"
- [ ] T027 [P] [US4] Crear `backend/tests/Feature/Seguridad/RevocacionTokensTest.php`: tras `PUT usuarios/{id}/pin` y tras `PUT usuarios/{id}` con `activo=false`, el token previo del usuario recibe 401; usuario inactivo con token vigente → 401 en una ruta de BARRA **y en `GET auth/me`**

### Implementation for User Story 4

- [ ] T028 [US4] En `backend/routes/api.php` aplicar `GrupoRol::middleware(GrupoRol::BARRA)` + `sucursal.sesion`, `GrupoRol::middleware(GrupoRol::LECTURA_AUDITORIA)`, `GrupoRol::middleware(GrupoRol::CONTABLE)` y `GrupoRol::middleware(GrupoRol::ADMIN)` a los subgrupos creados en T008, y `->middleware('rol')` (sin parámetros = cualquier usuario activo, ver T004) a `GET auth/me`, de modo que un usuario inactivo con token vigente reciba 401 en **todas** las rutas protegidas (M1)
- [ ] T029 [US4] En `backend/app/Infrastructure/Http/Controllers/Api/UserController.php`: `$usuario->tokens()->delete()` en `cambiarPin` y en `update` cuando cambie `activo` a `false` o cambie la contraseña
- [ ] T030 [US4] Ejecutar `cd backend && php artisan test` → verde; si un test existente falla por rol, corregir el rol del usuario del test (no la matriz) salvo que revele una pantalla real mal clasificada → en ese caso actualizar `contracts/matriz-acceso.md` primero

**Checkpoint**: Las 4 historias funcionan de forma independiente.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T031 [P] Revisión cruzada: `codex review` sobre el diff de la rama y resolver hallazgos confirmados
- [ ] T032 [P] Actualizar `CLAUDE.md` (Convenciones: "Rutas nuevas de `/api/v1` van dentro de un grupo autenticado con `GrupoRol`; públicas solo con justificación") y `specs/README.md` (003 → implementado; tachar deuda #1 y #2)
- [ ] T033 [P] Corregir el comentario "Bearer JWT Token" → "Bearer token (Sanctum)" en `frontend_web/src/plugins/axios.js`
- [ ] T034 Validar manualmente los escenarios 1–4 de `specs/003-seguridad-api-sanctum/quickstart.md` contra el backend local con el bot apuntando a local
- [ ] T035 Entregar al usuario el checklist de despliegue de `quickstart.md` (pasos 1–6) antes de `deploy.sh`; NO desplegar sin su confirmación

---

## Dependencies & Execution Order

### Phase Dependencies
- **Setup (F1)** → **Foundational (F2)** → historias.
- **US1 (F3)**: depende de F2. Es el MVP y se puede desplegar sola (tras checklist pasos 1–2).
- **US2 (F4)**: depende de F1 (config). Independiente de US1 salvo `routes/api.php` (no lo toca).
- **US3 (F5)**: depende de US1 (T008 dejó `bot-status` pendiente) y de T001.
- **US4 (F6)**: depende de US1 (subgrupos de T008) y de F2 (middlewares).
- **Polish (F7)**: al final.

### Within Each User Story
- Escribir el test primero y verlo fallar; luego implementar; cerrar con la suite completa.
- `routes/api.php` lo tocan T008 → T024 → T028 en ese orden (nunca en paralelo).

---

## Parallel Opportunities

```text
# F2 (tras T003):
T004 EnsureUserHasRole.php        | T005 EnsureSucursalDeSesion.php

# F3:
T007 AccesoAnonimoTest.php        | T009 adaptar tests existentes

# F4 (todas en archivos distintos) — buen bloque para delegar a Codex:
T012 LoginWebUseCase  | T013 VerifyBranchToken | T014 VerifyWebhookSecret | T015 Dispatcher
T016 / T017 comandos  | T018 bot.js            | T019 agent.py

# F6:
T026 MatrizAccesoTest.php         | T027 RevocacionTokensTest.php
```

**Orquestación sugerida**: Claude implementa F2 → F3 → F5 → F6 (rutas y middlewares, secuenciales). Codex, en worktree `../c22-codex` rama `003-seguridad-puertas-traseras`, implementa F4 (T011–T020) en paralelo tras T001. Merge antes de T025. Revisión cruzada en T031.

---

## Implementation Strategy

### MVP First
1. F1 + F2 → F3 (US1). **Parar y validar** (Escenario 1). Desplegable.
2. F4 (US2) — cerrar puertas traseras; aplicar checklist de despliegue.
3. F5 (US3) — `bot-status` híbrido.
4. F6 (US4) — roles.
5. F7 — revisión cruzada, documentación, despliegue con confirmación del usuario.

---

## Notes
- 37 tareas (35 + T007a, T020a agregadas por `/speckit-analyze` del 2026-10-16: C1, H1, H2, M1). T020 y T020a completadas como medida urgente. Formato `- [ ] [TaskID] [P?] [Story?] Descripción con ruta de archivo`.
- Marcar `[X]` al completar cada tarea.
- Rotar credenciales expuestas y purgar historial de git: operativo del usuario (quickstart, paso 6).
