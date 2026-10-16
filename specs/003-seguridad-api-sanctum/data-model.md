# Data Model — 003 Seguridad de la API

**Sin migraciones nuevas.** La feature reutiliza tablas existentes.

## Entidades usadas

### `usuarios` (modelo `Usuario`, existente)
| Campo | Uso en esta feature |
|---|---|
| `rol` | Determina el grupo de acceso (ver `contracts/matriz-acceso.md`). Valores: `barman`, `garzon`, `cajera`, `admin` (app); `super_admin`, `dueno`, `contadora`, `auxiliar_contable` (web). Desconocido → mínimo privilegio (403 en todo salvo `auth/me`). |
| `activo` | `false` → 401 en cualquier ruta protegida aunque el token exista; al desactivar se revocan los tokens. |
| `sucursal_actual_id` | Sucursal de la sesión para barman/cajera (lo fija `LoginPinUseCase`); base de la restricción por sucursal. |
| `password_hash`, `pin_hash` | Únicas credenciales válidas para el login web (se elimina el respaldo de contraseñas fijas). Cambiar cualquiera revoca los tokens. |

### `personal_access_tokens` (Sanctum, existente)
- Una fila por sesión emitida (`pin-auth-token` o `web-token`).
- Revocación = borrar las filas del usuario (`$usuario->tokens()->delete()`).
- `expires_at` permanece `null` (sin vencimiento automático, decisión de spec).

### `sucursales.token_acceso` (existente)
- Único secreto válido para `X-Branch-Token`. Ninguna petición puede modificarlo (se elimina la reescritura por clave maestra).

## Configuración (no persistida en BD)

| Clave `config()` | Variable de entorno | Consumidores | Si falta |
|---|---|---|---|
| `services.whatsapp.webhook_secret` | `WHATSAPP_WEBHOOK_SECRET` | `VerifyWebhookSecret`, middleware híbrido de `bot-status`, `WhatsAppOutboundDispatcherService` | 503 + `Log::critical` (Laravel); el bot no arranca |
| `services.whatsapp.bot_http_port` | `BOT_HTTP_PORT` | `WhatsAppOutboundDispatcherService` | default `3001` (no es secreto) |

## Agente POS (`scripts/sync_agent/config.json`, fuera de git)
Campos: `api_url`, `branch_token`, `sql_server`, `database`, `db_user`, `db_password`, `poll_interval_seconds`, `batch_size`. Plantilla versionada: `config.example.json` con valores ficticios.
