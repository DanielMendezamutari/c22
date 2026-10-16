# C22 — Grupo Punto Frío

Plataforma de auditoría de inventario, rellenos, comisiones y recaudación (vía WhatsApp + IA) para las 4 casas de Grupo Punto Frío, independiente del POS.

## Stack y decisiones vigentes
- Backend: Laravel 11 / PHP 8.2 + Sanctum, API `/api/v1`. Hexagonal: `Domain` (PHP puro) → `Application/UseCases` → `Infrastructure`. Controladores delgados que delegan en un UseCase.
- Datos: MySQL en prod (cPanel), SQLite en local y `:memory:` en tests. Multi-sucursal lógico por `sucursal_id`.
- Inventario: fracciones `DECIMAL(8,2)` en múltiplos de 0.25 (`FraccionLicor`); toda mutación de stock en `DB::transaction()`; traspasos descuentan en origen y acreditan solo al confirmar recepción.
- Web: Vue 3 + Vuetify (plantilla Materialize) en `frontend_web/`; el build sale a `backend/public/` y se commitea (así se despliega).
- Móvil: Flutter + Riverpod + SQLite offline-first en `frontend/puntofrio_app/` (hoy en espera).
- IA: Gemini Vision (`GEMINI_MODEL`, fallback `gemini-3.5-flash-lite`) para planillas, vouchers y tickets POS.
- WhatsApp: bot Node + Baileys silencioso con whitelist de grupos (`scripts/whatsapp_bot/`) → webhook `X-Webhook-Secret`; salida por HTTP local :3001.
- POS: agente Windows `scripts/sync_agent/` empuja RestoTech con `X-Branch-Token`. Modo actual: "Solo WhatsApp".

## Arrancar y probar en local
```bash
cd backend && php artisan migrate && php artisan serve                        # :8000
cd backend && php artisan queue:work                                          # cola `database`: sin worker los jobs de IA quedan pendientes
cd frontend_web && VITE_API_BASE_URL=http://localhost:8000/api/v1 pnpm dev   # no hay proxy en Vite
cd scripts/whatsapp_bot && npm start   # ⚠ su .env apunta a PRODUCCIÓN; en local usar LARAVEL_API_URL=http://localhost:8000/api/v1
cd backend && php artisan test         # si tocas transformación, cortes o comisiones: AGREGAR tests nuevos, no solo correrlos
```

## Convenciones
- Español en dominio, specs y commits; conventional commits `tipo(scope): descripción`.
- SDD: /speckit-specify → plan → tasks → implement. Feature nueva = spec nuevo (`specs/003-…`); marcar tareas `[X]`.
- Código nuevo en su capa hexagonal; no extender `app/Models`, `app/Services`, `app/Jobs` ni los controladores gigantes. Ojo: los `Domain/Ports` hoy devuelven modelos Eloquent (hexagonal parcial); no copiar ese patrón.
- Migraciones: nombres de índice/FK ≤ 64 caracteres (MySQL de cPanel).
- Sin nombres de casas quemados: se leen de `sucursales`.
- UI web: buscar el componente en `template_reference/admin-full-version` antes de crear uno.
- Nunca commitear `.env`, `auth_info_baileys/`, `scripts/sync_agent/config.json`.

Las reglas de producto viven en .specify/memory/constitution.md y el estado del producto en specs/README.md
