# Quickstart / Validación — 003 Seguridad de la API

## Prerrequisitos (local)
- `backend/.env` con `WHATSAPP_WEBHOOK_SECRET=<valor-local>`.
- `cd backend && php artisan migrate && php artisan serve` (:8000).
- Un usuario `admin` y uno `barman` con PIN (seeders existentes).

## Escenario 1 — Acceso anónimo bloqueado (US1)
```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/v1/usuarios          # 401
curl -s http://localhost:8000/api/v1/usuarios                                          # {"success":false,"error":"No autenticado"}
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/v1/auth/sucursales   # 200 (pública)
```
Sin `Accept: application/json` también debe dar 401 JSON (no 500 ni redirect).

## Escenario 2 — Puertas traseras cerradas (US2)
- `POST /auth/web/login` con un usuario web real y `password=123456` (si esa no es su contraseña) → error "Contraseña incorrecta".
- `POST /sync/pos-transacciones` con la antigua clave maestra → 401; `token_acceso` de la sucursal sin cambios.
- Quitar `WHATSAPP_WEBHOOK_SECRET` del `.env`, `php artisan config:clear`, llamar `POST /webhook/whatsapp` con cualquier secreto → 503.
- `php artisan seguridad:auditar-credenciales` lista usuarios web sin contraseña ni PIN.

## Escenario 3 — Bot y POS operativos (US3)
- Bot local con `LARAVEL_API_URL=http://localhost:8000/api/v1` y el mismo secreto: arranca, reporta estado y descarga grupos.
- `GET /whatsapp/bot-status` con `X-Webhook-Secret` → 200; con Bearer de admin → 200; sin nada → 401.
- `POST /usuarios` con `X-Webhook-Secret` y sin Bearer → 401.

## Escenario 4 — Autorización por rol (US4)
Con token de barman: `POST /usuarios` → 403; `POST /turnos/abrir` (su sucursal) → 2xx; con `sucursal_id` de otra casa → 403.
Con token de admin: `POST /usuarios` → 2xx. Matriz completa: `contracts/matriz-acceso.md`.

## Suite automatizada
```bash
cd backend && php artisan test                          # suite completa en verde (FR-020)
cd backend && php artisan test tests/Feature/Seguridad  # matriz, puertas traseras, máquina, revocación
```

## Checklist de despliegue en producción (orden obligatorio)
0. **URGENTE, no esperar a la feature**: borrar `~/c22.ribersoft.com/backend/public/sync_agent.zip` del servidor (SSH o Administrador de archivos de cPanel) y comprobar que `https://c22.ribersoft.com/sync_agent.zip` responde 404. Rotar la contraseña SQL Server de la caja y el `token_acceso` de la sucursal que contenía.
1. **Antes**: en el servidor, `php artisan seguridad:auditar-credenciales`; asignar contraseña a quien aparezca (`php artisan usuarios:asignar-password {id}`).
2. **Rotar** `WHATSAPP_WEBHOOK_SECRET`: generar valor nuevo y ponerlo **igual** en `backend/.env` y `scripts/whatsapp_bot/.env` (hoy producción probablemente usa el valor quemado por `config:cache`; ver research §6).
3. Confirmar que el agente POS de cada casa usa el `token_acceso` real de su sucursal, no la clave maestra.
4. `./deploy.sh` y reiniciar el bot (`./levantar_bot.sh`).
5. Verificar: login web, tablero, recaudaciones, gestión WhatsApp; el bot en estado "conectado"; un mensaje de prueba llega al webhook.
6. **Rotar credenciales expuestas** (operativo, fuera del código): contraseña `sa` del SQL Server de la caja, clave maestra POS, secreto anterior del webhook. Evaluar purga del historial de git (`git filter-repo`) y forzar re-clonado.
