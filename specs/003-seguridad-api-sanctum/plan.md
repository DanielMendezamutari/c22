# Implementation Plan: Seguridad de la API — Autenticación Obligatoria, Eliminación de Puertas Traseras y Autorización por Rol

**Branch**: `003-seguridad-api-sanctum` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-seguridad-api-sanctum/spec.md`

## Summary

Cerrar la API `/api/v1` (113 de 118 rutas hoy sin autenticación) usando el Sanctum ya instalado: reagrupar `routes/api.php` en públicas, de máquina, híbrida y autenticadas por grupo de rol; render JSON uniforme de 401/403; eliminar la puerta trasera de contraseñas fijas, la clave maestra POS y los secretos por defecto (pasando de `env()` a `config()` y fallando cerrado); sacar las credenciales del agente POS del repo; revocar tokens al desactivar/cambiar credenciales; y cubrir todo con una suite de pruebas de matriz de acceso. Sin migraciones ni cambios en el formato de login.

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 11); Node 20 (bot Baileys); Python 3 / PowerShell (agente POS)
**Primary Dependencies**: `laravel/sanctum` ^4.0 (ya instalado). Sin dependencias nuevas.
**Storage**: MySQL (prod) / SQLite (local, tests `:memory:`). Tablas existentes `usuarios`, `personal_access_tokens`, `sucursales`.
**Testing**: PHPUnit 10 vía `php artisan test`; `Laravel\Sanctum\Sanctum::actingAs()` para tests autenticados.
**Target Platform**: cPanel compartido (Apache + PHP 8.2), con `config:cache` en cada deploy.
**Project Type**: Web service API + clientes (SPA Vue, app Flutter, bot Node, agente Windows).
**Performance Goals**: Overhead de autenticación imperceptible (1 consulta indexada por token por petición).
**Constraints**: No romper panel web ni bot en producción (modo "Solo WhatsApp"); despliegue por `git pull`; el bot y Laravel deben compartir el mismo secreto en el mismo despliegue.
**Scale/Scope**: 118 rutas, 8 roles, 4 clientes, 4 sucursales.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio / Regla | Estado | Evidencia |
| :--- | :---: | :--- |
| **I. Rendimiento empírico** | **PASS (neutral)** | No altera cálculos de transformación. |
| **II. Justicia en comisiones** | **PASS (refuerza)** | Hoy cualquiera puede registrar rellenos anónimos y generar comisiones; tras la feature solo barra autenticada. |
| **III. Responsabilidad por turno** | **PASS (refuerza)** | Cada corte/relleno queda atado a un usuario autenticado; restricción por sucursal de la sesión. |
| **IV. Independencia del POS** | **PASS** | El agente POS sigue con su token de sucursal; se elimina la clave maestra que permitía suplantar a Casa22. |
| **V. Trazabilidad multi-sucursal** | **PASS (refuerza)** | "Actor" de cada movimiento deja de poder ser anónimo (SC-006). |
| **Inmutabilidad de registros** | **PASS (refuerza)** | Reconteo (`autorizar-reconteo`) y `forzar-resincronizacion` pasan a solo `ADMIN`. |
| **Governance §3 (tests)** | **PASS** | Suite `tests/Feature/Seguridad/` + adaptación de tests existentes; suite completa en verde. |

Re-check post-diseño: **PASS**. Sin violaciones; Complexity Tracking vacío.

## Project Structure

### Documentation (this feature)

```text
specs/003-seguridad-api-sanctum/
├── plan.md              # Este archivo
├── research.md          # Decisiones técnicas (Phase 0)
├── data-model.md        # Entidades y configuración usadas (Phase 1)
├── quickstart.md        # Validación + checklist de despliegue (Phase 1)
├── contracts/
│   └── matriz-acceso.md # Ruta → grupo de acceso → respuesta (Phase 1)
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
backend/
├── bootstrap/app.php                                  # aliases rol, sucursal.sesion, bot.o.sesion; render JSON 401/403
├── config/services.php                                # + whatsapp.webhook_secret, whatsapp.bot_http_port
├── .env.example                                       # + WHATSAPP_WEBHOOK_SECRET=
├── routes/api.php                                     # reagrupado según contracts/matriz-acceso.md
├── app/Domain/Enums/GrupoRol.php                      # NUEVO: constantes ADMIN, BARRA, LECTURA_AUDITORIA, CONTABLE
├── app/Infrastructure/Http/Middleware/
│   ├── EnsureUserHasRole.php                          # NUEVO (alias rol): rol + usuario activo
│   ├── EnsureSucursalDeSesion.php                     # NUEVO (alias sucursal.sesion)
│   ├── VerifyWebhookSecretOrSanctum.php               # NUEVO (alias bot.o.sesion)
│   ├── VerifyWebhookSecret.php                        # config() + fallo cerrado 503
│   └── VerifyBranchToken.php                          # sin clave maestra
├── app/Application/UseCases/Auth/LoginWebUseCase.php  # sin contraseñas fijas
├── app/Infrastructure/Http/Controllers/Api/UserController.php  # revocar tokens
├── app/Services/WhatsAppOutboundDispatcherService.php # config() + fallo cerrado
├── app/Console/Commands/
│   ├── AuditarCredencialesCommand.php                 # NUEVO seguridad:auditar-credenciales
│   └── AsignarPasswordCommand.php                     # NUEVO usuarios:asignar-password
└── tests/Feature/Seguridad/                           # NUEVO
    ├── AccesoAnonimoTest.php
    ├── RutasProtegidasTest.php                        # guarda de FR-003: ninguna ruta api/* sin protección
    ├── MatrizAccesoTest.php
    ├── PuertasTraserasTest.php
    ├── ClientesMaquinaTest.php
    └── RevocacionTokensTest.php

scripts/whatsapp_bot/bot.js                            # sin secreto por defecto; exit(1) si falta
backend/public/sync_agent.zip                          # RETIRADO (contenía credenciales, era público)
scripts/sync_agent/
├── config.json                                        # fuera de git (git rm --cached + .gitignore)
├── config.example.json                                # NUEVO plantilla ficticia
└── agent.py                                           # sin credenciales por defecto
```

**Structure Decision**: Se respeta la hexagonal del backend: middlewares en `Infrastructure/Http/Middleware`, constantes de rol en `Domain/Enums`, comandos en `app/Console/Commands` (convención de Laravel 11, no `routes/console.php`). No se refactorizan controladores gigantes (fuera de alcance).

## Fases de Implementación

### FASE 1 — Fundaciones (bloqueante)
- `GrupoRol`, los 3 middlewares nuevos, aliases y render JSON 401/403 en `bootstrap/app.php`.
- `config/services.php` + `.env.example`.

### FASE 2 — US1 Autenticación obligatoria (MVP)
- Reagrupar `routes/api.php`: públicas fuera; todo lo demás bajo `auth:sanctum`. En esta fase todos los grupos autenticados usan solo `auth:sanctum` (sin `rol`) para poder desplegar rápido si urge.
- Adaptar tests existentes con `Sanctum::actingAs()`.

### FASE 3 — US2 Puertas traseras y secretos
- `LoginWebUseCase` sin contraseñas fijas; `VerifyBranchToken` sin clave maestra; `VerifyWebhookSecret` y dispatcher con `config()` y fallo cerrado; comandos de credenciales.
- `bot.js` sin default; `agent.py` sin defaults; `config.json` fuera de git + `config.example.json`.

### FASE 4 — US3 Clientes de máquina
- `bot.o.sesion` en `GET whatsapp/bot-status`; pruebas de bot/POS y de que el secreto no abre rutas de persona.

### FASE 5 — US4 Autorización por rol
- Aplicar `rol:` por grupo y `sucursal.sesion` en `BARRA`; revocación de tokens en `UserController`; prueba de matriz completa.

### FASE 6 — Verificación y despliegue
- Suite completa en verde; revisión cruzada (`codex review`); actualizar `CLAUDE.md` (sección convenciones: "rutas nuevas protegidas por defecto") y `specs/README.md`; checklist de despliegue de `quickstart.md`.

## Riesgos

| Riesgo | Mitigación |
|---|---|
| El bot queda rechazado tras el deploy porque producción hoy usa el secreto quemado (efecto `config:cache`) | Paso 2 del checklist: fijar el mismo secreto en ambos `.env` antes de `deploy.sh` y reiniciar el bot |
| Usuarios web que hoy entran solo con `123456` quedan fuera | `seguridad:auditar-credenciales` antes del deploy + `usuarios:asignar-password` |
| Una pantalla web/app usa una ruta que la matriz asignó a un rol más alto | Matriz derivada de los endpoints reales que llama cada cliente; smoke test del checklist; FASE 2 desplegable sin roles |
| Agente POS de Casa22 usa la clave maestra | Paso 3 del checklist |

## Complexity Tracking

Sin violaciones constitucionales que justificar.
