# Estado del Producto — C22 / Grupo Punto Frío

Índice vivo de features (Spec Kit). Actualizar al cerrar cada spec o al detectar deriva. Última revisión: 2026-10-16.

## Features

| Spec | Tema | Historias | Estado |
|---|---|---|---|
| [001-control-inventario-transformaciones](001-control-inventario-transformaciones/spec.md) | Plataforma completa: inventario, turnos, comisiones, auditoría, web, IA, POS, WhatsApp | US1–US38 | ✅ Implementado (289/289 tareas). Cerrado: no agregar historias |
| [002-test-suite-e2e](002-test-suite-e2e/spec.md) | Suite E2E backend + Flutter + runner | 3 | ✅ Implementado (26 tests backend en verde) |
| [003-seguridad-api-sanctum](003-seguridad-api-sanctum/spec.md) | Autenticación obligatoria, eliminación de puertas traseras y secretos, autorización por rol | 4 | 📋 Plan y tareas listos (35) |
| 004 | _Próxima_: documentar trabajo post-US38 (ver deriva) | — | ⏳ Pendiente |

## Módulos de 001 por área

| Área | Historias | Canal operativo hoy |
|---|---|---|
| Barra: rellenos, cortes, bajas, recepción, traspasos | US1–4, 10, 12–18, 21–25 | App Flutter (en espera) |
| Auditoría Ticket Z, ratios, liquidaciones, reconteo | US5, 6, 15, 20, 26, 35 | App Flutter / web |
| Administración: catálogo, usuarios, sucursales, proveedores | US7–9, 11, 19 | App Flutter |
| Web super admin en tiempo real + deploy cPanel | US27, 31 | Web ✅ activo |
| IA: recaudaciones, caja chica, taxis (Gemini Vision) | US28–30, 34 | Web + WhatsApp ✅ activo |
| POS RestoTech + conciliación triangulada | US32 | Agente Windows (lectura por foto mientras tanto) |
| Bot WhatsApp: grupos, QR, cruce inter-canales, despacho a Control de Recaudo | US33, 36–38 | ✅ activo 24/7 |

Modo operativo actual: **"Solo WhatsApp"**.

## Deriva conocida (código sin spec)
Implementado después de US38 (commits desde 2026-10-12) sin historia ni tareas:
- Cierre de turno detectado por IA y despacho automático sin RestoTech.
- Importación de ZIP de WhatsApp (web, API y `artisan whatsapp:importar-zip`); `artisan whatsapp:auditar-hoy`.
- Anti-spam de 5 min por turno en despachos; reenvío desde admin; asignación de casa por lote.
- Heartbeat del bot, endpoint `bot-logs`, purga de sender keys, fixes de descifrado Baileys.

Acción: `/speckit-converge` o spec 003. Constitución pendiente de enmienda MINOR (3 → 4 casas).

## Deuda técnica abierta (auditoría Claude + Codex, 2026-10-16)

| # | Severidad | Problema | Evidencia |
|---|---|---|---|
| 1 | 🔴 Crítica | 113 de 118 rutas `/api/v1` sin `auth:sanctum` ni control de rol (crear usuarios, cambiar PIN, enviar WhatsApp, leer logs) | `backend/routes/api.php`; verificado: `GET /usuarios` sin token → 200 |
| 2 | 🔴 Crítica | Secretos en código y en git: clave maestra POS que además reescribe el token de sucursal; secreto webhook por defecto; `scripts/sync_agent/config.json` (credenciales SQL Server) versionado desde 9247c8d; **`backend/public/sync_agent.zip` con esas credenciales era descargable sin login** (retirado del repo el 2026-10-16; falta borrarlo del servidor y rotar) | `VerifyBranchToken.php:25`, `VerifyWebhookSecret.php:14`, `bot.js:25` |
| 3 | 🟠 Alta | Recepción de traspasos sin bloqueo (`lockForUpdate`): doble recepción concurrente duplica stock; sin turno destino usa `turno_id = 1` | `EloquentTraspasoRepository.php`, `RecibirTraspasoUseCase.php:108,121` |
| 4 | 🟠 Alta | Producción depende de `queue:work` (cola `database`); no hay worker documentado en cPanel | `.env.example:38`, `WhatsAppWebhookController.php:210` |
| 5 | 🟡 Media | Hexagonal parcial: Ports devuelven Eloquent; controladores gigantes escriben modelos directo; servicios Gemini duplicados | `Domain/Ports/TurnoRepositoryPort.php:5`, `UserController.php:42` |
| 6 | 🟡 Media | Sucursales quemadas (3 casas) en el filtro de recaudaciones | `frontend_web/src/pages/auditoria/recaudaciones.vue:16` |
| 7 | 🟡 Media | 787 assets de builds viejos acumulados en git (14 MB) | `backend/public/assets/` |
| 8 | ⚪ Baja | Specs con rutas Windows antiguas; constitución dice 3 casas | `specs/*/plan.md`, constitución v1.0.0 |
