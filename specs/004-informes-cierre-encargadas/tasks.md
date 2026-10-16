# Tasks: Informes de Cierre Basados en las Encargadas y Entrega Confiable

**Input**: `specs/004-informes-cierre-encargadas/` (spec.md, plan.md)
**Tests**: SÍ (FR-014).

## Phase 1: Setup

- [X] T001 Agregar `'gemini' => ['api_key' => env('GEMINI_API_KEY'), 'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite')]` en `backend/config/services.php` y `GEMINI_API_KEY=`/`GEMINI_MODEL=` en `backend/.env.example`
- [X] T002 Crear migración `backend/database/migrations/2026_10_16_000001_ampliar_clasificacion_inbound_whatsapp.php`: `clasificacion_ia` y `estado` de `whatsapp_mensajes_inbound` a `string(30)` con sus defaults (`desconocido`, `pendiente_proceso`)

## Phase 2: Foundational

- [X] T003 [P] Usar `config('services.gemini.*')` en `backend/app/Services/GeminiVisionAuditorService.php`, `backend/app/Infrastructure/AI/GeminiVisionService.php` y `backend/app/Infrastructure/AI/TaxiParserService.php`
- [X] T004 [P] Quitar el PIN del `Log::info` en `backend/app/Application/UseCases/Auth/LoginPinUseCase.php`

## Phase 3: US1 - Informe solo desde el cierre de la encargada (P1) 🎯 MVP

- [X] T005 [P] [US1] Test `backend/tests/Feature/InformeCierreEncargadaTest.php`: cerrar turno vía `CerrarTurnoUseCase` no llama al dispatcher; planilla de encargada despacha con su nombre; segunda planilla no sobrescribe ni despacha; sin planilla el use case lanza `DomainException`
- [X] T006 [US1] Eliminar el despacho automático en `backend/app/Application/UseCases/Turnos/CerrarTurnoUseCase.php`
- [X] T007 [US1] En `backend/app/Application/UseCases/Auditoria/GenerarYDespacharInformeAuditoriaUseCase.php`: `DomainException` si no hay planilla; "Encargada" desde `cajero_nombre` o remitente del inbound de la planilla, nunca `barman`
- [X] T008 [US1] En `backend/app/Jobs/ProcesarMensajeWhatsAppJob.php`: no sobrescribir planilla existente de otro mensaje (→ `requiere_revision`); despachar solo si el turno no tiene informe enviado; `ticket_pos` solo enriquece; turno autónomo con usuario de la misma sucursal

## Phase 4: US2 - Nunca inventar cifras (P1)

- [X] T009 [P] [US2] Ampliar `InformeCierreEncargadaTest.php`: con la IA no disponible, la imagen queda `requiere_revision`, sin planilla, sin turno y sin despacho
- [X] T010 [US2] `simularClasificacion` → `otro`, confianza 0, `ia_disponible = false` en `backend/app/Services/GeminiVisionAuditorService.php`; en el job, cortar sin efectos cuando `ia_disponible === false`

## Phase 5: US3 - Informes legibles en el grupo (P1)

- [X] T011 [US3] Actualizar `scripts/whatsapp_bot/package.json` a `@whiskeysockets/baileys` `7.0.0-rc14`, `npm install` local y verificar que existan los exports usados
- [X] T012 [US3] En `scripts/whatsapp_bot/bot.js`: `reiniciarBot()` con generación de socket y un único temporizador; ignorar `close` de sockets viejos
- [X] T013 [US3] En `scripts/whatsapp_bot/bot.js`: `outbound_store.json` persistente con `BufferJSON`; `getMessage` desde el store o `undefined`; ignorar como entrada los IDs propios enviados
- [X] T014 [US3] En `scripts/whatsapp_bot/bot.js`: `normalizeMessageContent()` para mensajes efímeros/view-once; remitente con `participantAlt` si existe
- [X] T015 [US3] Agregar `outbound_store.json` a `.gitignore`; `node --check scripts/whatsapp_bot/bot.js`

## Phase 6: US4 - Encargadas desde datos (P2)

- [X] T016 [P] [US4] Test en `InformeCierreEncargadaTest.php`: el prompt incluye la cajera registrada de la casa y no contiene nombres fijos
- [X] T017 [US4] En `backend/app/Services/GeminiVisionAuditorService.php`: categoría `cierre_barra` y bloque de personal construido desde `usuarios` activos por sucursal (cajera = encargada; barman/garzón = no encargado); quitar nombres fijos
- [X] T018 [US4] En `ProcesarMensajeWhatsAppJob::inferirSucursalId`: coincidencia contra nombres/códigos de `sucursales` primero; quitar nombres de personas

## Phase 7: Polish

- [X] T019 `cd backend && php artisan test` en verde
- [X] T020 [P] Revisión cruzada `codex review` del diff
- [X] T021 [P] Actualizar `specs/README.md`, `CLAUDE.md` (app de barra activa) y quickstart de despliegue en `specs/004-informes-cierre-encargadas/quickstart.md`
- [ ] T022 Entregar checklist de despliegue al usuario (no desplegar sin su confirmación)

## Dependencies
- T001–T002 → resto. US1 y US2 tocan el mismo job (T008 → T010 secuencial). Bot (T011–T015) independiente del backend → delegable a Codex en paralelo.

## Notes
- Revisión cruzada (T020, Codex): 4 hallazgos confirmados y corregidos — candado por sucursal en cierres concurrentes; carga web sin IA y sin montos manuales → 422; foto tardía del mismo cierre (>2 h) reutiliza el turno; escritura síncrona del `outbound_store`.
- Bugs previos encontrados en el camino: enum de `clasificacion_ia` sin `ticket_pos`; `tipo_turno` "día" (con tilde) rompía el enum `dia/noche`; eco del informe propio por `process.nextTick` de Baileys; PIN en texto plano en el log; fallback de `GeminiVisionService` con montos inventados.
