# Contrato: Matriz de Acceso de la API `/api/v1`

Fuente de verdad para `backend/routes/api.php` y para la prueba de matriz. Toda ruta nueva entra por defecto en un grupo autenticado (FR-003).

## Grupos de roles

| Grupo | Roles |
|---|---|
| `ADMIN` | `admin`, `super_admin` |
| `BARRA` | `barman`, `garzon`, `cajera` + `ADMIN` |
| `LECTURA_AUDITORIA` | `dueno`, `contadora`, `auxiliar_contable` + `ADMIN` |
| `CONTABLE` | `contadora` + `ADMIN` |

## Respuestas de rechazo

| Caso | Status | Cuerpo |
|---|---|---|
| Sin credencial / inválida / revocada | 401 | `{"success": false, "error": "No autenticado"}` |
| Usuario inactivo con token vigente | 401 | `{"success": false, "error": "No autenticado"}` |
| Rol sin permiso | 403 | `{"success": false, "error": "No tiene permiso para esta operación"}` |
| Sucursal distinta a la de la sesión (barman/cajera) | 403 | `{"success": false, "error": "Operación fuera de su sucursal"}` |
| Secreto de máquina incorrecto | 401 | (sin cambios respecto al actual) |
| Secreto de máquina no configurado en el servidor | 503 | `{"success": false, "error": "Configuración de seguridad incompleta"}` |

## Rutas por grupo

### Públicas (sin credencial)
`GET auth/sucursales` · `GET auth/sucursales-usuarios` · `POST auth/login-pin` · `POST auth/web/login`

### Máquina — `X-Webhook-Secret` (bot)
`POST webhook/whatsapp` · `GET whatsapp/grupos-auditables` · `POST whatsapp/bot-status` · `POST whatsapp/grupos-descubiertos`

### Máquina — `X-Branch-Token` (agente POS)
`POST sync/pos-transacciones`

### Híbrida — secreto de bot **o** sesión válida (cualquier rol)
`GET whatsapp/bot-status`

### Cualquier sesión válida (usuario activo, cualquier rol — middleware `rol` sin parámetros)
`GET auth/me`

### `BARRA` (+ restricción de sucursal para barman/cajera)
- Transformaciones: `POST transformaciones/relleno`, `POST transformaciones/baja`, `POST inventario/bajas`
- Turnos: `POST turnos/abrir`, `POST turnos/{id}/cerrar`, `GET turnos/activo`, `GET turnos/{id}/corte-inicial`, `GET turnos/historial-cortes`, `GET turnos/{id}/resumen-cajera`, `POST turnos/{id}/cobro-recibido`, `POST turnos/{id}/confirmar-pago-comision`, `POST turnos/{id}/liquidar-garzon`, `POST turnos/{id}/aplicar-reconteo`
- Inventario: `GET productos/corte`, `POST ingresos`, `POST inventario/ingreso`, `GET|POST inventario/compras`
- Traspasos: `POST traspasos/enviar`, `POST traspasos/{id}/recibir`, `GET traspasos/pendientes`, `GET traspasos/stock-disponible`
- Catálogos de lectura: `GET productos`, `GET motivos-baja`, `GET recetas/transformacion`, `GET recetas/combos`, `GET proveedores`, `GET sucursales`

### `LECTURA_AUDITORIA`
`GET dashboard/metricas-tiempo-real` · `GET auditoria/ratios` · `GET auditoria/turnos-pendientes` · `GET auditoria/sucursal/{sucursal_id}/informe-turno` · `GET auditoria/conciliacion-triangulada/{turno_id}` · `GET liquidaciones/semanal` · `GET recaudaciones/diarias` · `GET gastos-caja-chica` · `GET gastos-caja-chica/pendientes` · `GET taxis/reporte-diario` · `GET taxis/tarifas` · `GET pos/transacciones-en-vivo` · `GET pos/estado-casas` · `GET pos/mapeo-productos`

### `CONTABLE`
`POST auditoria/calcular` · `POST auditoria/subir-planilla-manual` · `POST liquidaciones/pagar` · `POST recaudaciones/procesar-planilla` · `POST recaudaciones/procesar-voucher` · `POST gastos-caja-chica` · `POST gastos-caja-chica/{id}/aprobar` · `POST gastos-caja-chica/cruzar-planilla` · `POST taxis/procesar-mensaje`

### `ADMIN`
- Usuarios: `GET|POST usuarios`, `PUT usuarios/{id}`, `PUT usuarios/{id}/pin`
- Catálogo: `POST productos`, `PUT productos/{id}`, `PATCH productos/{id}/toggle-activo`, `GET proveedores/{id}`, `POST proveedores`, `PUT|DELETE proveedores/{id}`, `PATCH proveedores/{id}/toggle-activo`
- Recetas: `POST recetas/transformacion`, `PUT|DELETE recetas/transformacion/{id}`, `POST recetas/combos`, `PUT|DELETE recetas/combos/{id}`
- Motivos de baja: `GET motivos-baja/admin`, `POST motivos-baja`, `PUT|DELETE motivos-baja/{id}`
- Sucursales: `POST sucursales`, `PUT sucursales/{id}`, `PATCH sucursales/{id}/toggle-activo`
- Alertas y dispositivos: `GET alertas/discrepancias`, `POST alertas/{id}/resolver|aprobar-producto|unificar-producto`, `POST dispositivos/registrar-maestro|desvincular`, `GET dispositivos/maestros`
- Turnos (control): `POST turnos/{id}/autorizar-reconteo`, `POST turnos/{id}/forzar-resincronizacion`
- Sistema: `GET sistema/logs-en-vivo`, `POST pos/mapeo-productos`
- WhatsApp: `POST whatsapp/grupos|confirmar-sucursal|asignar-sucursal-lote|solicitar-sync-grupos|desconectar|resolver-discrepancia-cruce|enviar-mensaje|despachar-informe-cierre|auditar-hoy|cargar-zip|reconectar`, `GET whatsapp/grupos-disponibles|mensajes-recientes|auditorias-cruce|config-control-recaudo|bot-logs`, `GET|POST sucursal-whatsapp-grupos`, `PUT|DELETE sucursal-whatsapp-grupos/{id}`

Total: 4 públicas + 5 máquina + 1 híbrida + 1 sesión + resto por rol = 118 rutas.
