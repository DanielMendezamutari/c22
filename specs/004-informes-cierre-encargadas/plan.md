# Implementation Plan: Informes de Cierre Basados en las Encargadas y Entrega Confiable

**Branch**: `004-informes-cierre-encargadas` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

## Summary

Dos correcciones de producción. (1) Backend: el informe de recaudo deja de dispararse con el cierre del barman en la app y nace solo de la planilla de la encargada; se elimina la planilla simulada que inventaba cifras; la IA aprende el tipo `cierre_barra` y recibe el personal real por casa; la clave de Gemini se lee con `config()`. (2) Bot: actualización de Baileys 6.7.24 (*legacy*) a 7.0.0-rc14 con soporte LID, conexión única garantizada, memoria persistente de mensajes enviados para reenvíos y desenvoltura de mensajes efímeros/view-once.

## Technical Context

**Language/Version**: PHP 8.2 / Laravel 11; Node 20 (ESM) para el bot.
**Primary Dependencies**: `@whiskeysockets/baileys` 6.7.24 → **7.0.0-rc14** (dist-tag `latest`; 6.7.24 es `legacy`). Sin dependencias PHP nuevas.
**Storage**: MySQL (prod), SQLite (tests). Migración: `whatsapp_mensajes_inbound.clasificacion_ia` y `.estado` de `enum` a `string(30)` (el enum actual no admite `ticket_pos`: cada ticket POS terminaba en `estado = error` en MySQL estricto).
**Testing**: PHPUnit (`php artisan test`), mocks de `GeminiVisionAuditorService` y `WhatsAppOutboundDispatcherService`. Bot: `node --check` + carga de módulos; validación real en producción.
**Constraints**: Sin WhatsApp de pruebas → la actualización del bot se valida en producción con respaldo de `auth_info_baileys` y vuelta atrás.

## Constitution Check

| Principio | Estado | Evidencia |
|---|---|---|
| I. Rendimiento empírico | **PASS (corrige violación)** | Se elimina la planilla simulada con cifras inventadas (FR-006). |
| II. Comisiones | PASS (neutral) | No toca transformaciones. |
| III. Responsabilidad por turno | **PASS (refuerza)** | El informe identifica a la encargada real; el barman deja de figurar como responsable del recaudo. |
| IV. Independencia del POS | PASS | Tickets POS siguen como enriquecimiento opcional; ahora se pueden guardar. |
| V. Trazabilidad | **PASS (refuerza)** | Segunda planilla del turno no sobrescribe la primera; queda para revisión. |
| Governance §3 | PASS | `InformeCierreEncargadaTest` + suite completa. |

## Diseño

### Backend
1. `CerrarTurnoUseCase`: eliminar el despacho automático (FR-001).
2. `ProcesarMensajeWhatsAppJob`:
   - Si la IA no estuvo disponible (`ia_disponible = false`) → `estado = requiere_revision`, sin efectos (FR-006).
   - `cierre_barra` → se registra, sin cerrar turno ni despachar (FR-005).
   - `planilla_caja`: si el turno ya tiene planilla de otro mensaje → no sobrescribe, `requiere_revision`, sin despacho (FR-004). Despacha solo si el turno aún no tiene un informe enviado (sin `forzar`).
   - `ticket_pos`: enriquece la planilla; ya no re-despacha (evita informes duplicados).
   - Turno autónomo: `barman_id` deja de tomar "el primer usuario" de cualquier casa; usa un usuario de la sucursal y, si no hay, el usuario "Sistema" de esa sucursal.
   - `inferirSucursalId`: primero coincide `sucursal_sugerida` contra nombres/códigos de la BD; quita nombres de personas.
3. `GenerarYDespacharInformeAuditoriaUseCase`: sin planilla → `DomainException` (no se envía informe vacío) (FR-002); "Encargada" = `planilla.cajero_nombre` o remitente del mensaje de la planilla, nunca el barman (FR-003).
4. `GeminiVisionAuditorService`: `config('services.gemini.*')` (FR-007); prompt con categoría `cierre_barra` y personal activo por casa desde `usuarios` (FR-012); `simularClasificacion` → `otro`, confianza 0, `ia_disponible = false`. Igual para `GeminiVisionService` y `TaxiParserService` (solo `config()`).
5. `LoginPinUseCase`: quitar el PIN del log (FR-013).

### Bot (`scripts/whatsapp_bot/bot.js`)
1. Baileys 7.0.0-rc14 (FR-011): remitente con `key.participantAlt` (teléfono) cuando exista.
2. `reiniciarBot()` único: generación de socket; los eventos `close` de sockets viejos se ignoran; un solo temporizador de reinicio pendiente (FR-009).
3. `outbound_store.json`: últimos 500 mensajes enviados serializados con `BufferJSON`; `getMessage` lee de ahí y devuelve `undefined` si no está (FR-008).
4. Ignorar como entrada los IDs enviados por el propio bot (FR-010).
5. `normalizeMessageContent()` para desenvolver mensajes efímeros/view-once/documento con caption (las fotos de grupos con mensajes temporales llegaban como "texto" vacío).

## Riesgos

| Riesgo | Mitigación |
|---|---|
| Baileys 7 rc invalida la sesión y pide QR | Respaldo `auth_info_baileys` antes de actualizar; si pide QR se escanea desde la web; vuelta atrás: `npm i @whiskeysockets/baileys@6.7.24` + restaurar respaldo |
| Cambio de API en v7 rompe el bot | Verificar exports usados tras `npm install` local; `node --check` |
| Turnos sin planilla dejan de tener informe | Es el comportamiento correcto (FR-002); el admin puede reenviar manualmente cuando llegue la planilla |
