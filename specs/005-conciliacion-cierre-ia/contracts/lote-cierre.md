# Contrato: lote de cierre (spec 005, fase 3)

Un **lote** es el cierre de UNA casa en UN turno: todos los documentos que lo prueban, vengan del grupo que vengan.
La fase 3 corre en **modo sombra** (`CONCILIACION_V2=sombra`): arma y concilia lotes, guarda el resultado y
**no envía nada ni toca planillas/turnos del flujo actual**. Solo con `CONCILIACION_V2=activo` (fase 4) reemplaza al flujo viejo.

## Datos

**`documentos_cierre`**: `id`, `inbound_id` (FK whatsapp_mensajes_inbound), `lote_id` (nullable FK), `tipo`
(los 11 del contrato de extracción), `datos` (json: el objeto del tipo), `confianza`, `sucursal_id` (nullable),
`fecha_operativa` (date, nullable), `tipo_turno` (`dia`|`noche`|null), `evidencia_casa` (`encabezado`|`grupo`|`encargada`|`remitente`|`texto`|null),
timestamps. Índices: (`lote_id`), (`sucursal_id`, `fecha_operativa`, `tipo_turno`).

**`lotes_cierre`**: `id`, `sucursal_id`, `fecha_operativa`, `tipo_turno`, `encargada` (de la planilla), `remitentes` (json: teléfonos),
`estado` (`abierto` → `listo` → `conciliado`), `ultimo_documento_at`, `resultado` (json de `ResultadoConciliacion::toArray()`),
`conciliado_at`, timestamps. **Único** (`sucursal_id`, `fecha_operativa`, `tipo_turno`).

## Reglas de asignación (en este orden; la primera que aplica gana)

**Casa del documento**
1. `reporte_pos.servidor` con alias de casa (`sucursales.alias_documentos`) → `encabezado`. Gana siempre, aunque contradiga al grupo.
2. Casa del grupo (`sucursal_whatsapp_grupos.sucursal_id`) → `grupo`.
3. Encargada de la planilla registrada como cajera de una sola casa → `encargada`.
4. **Remitente**: si el mismo `sender_phone` envió en las últimas 16 h un documento con casa por `encabezado` o `grupo`, esa casa → `remitente` (las encargadas mandan todo lo de su cierre, también a grupos generales).
5. Alias en el texto adjunto → `texto`.
6. Ninguna → sin casa (queda pendiente; se reintenta cada vez que entra otro documento del mismo remitente).

**Fecha operativa y turno del documento**
1. `planilla_ventas.fecha/turno`, validada con `ProcesarMensajeWhatsAppJob::fechaOperativa()` (±2 días del envío).
2. `reporte_pos.desde`: fecha de inicio; turno `noche` si la hora de inicio es ≥ 17:00 o < 05:00, si no `dia`.
3. `control_shampagne.fecha/turno`, `sobre.fecha`.
4. Si no hay: hora de envío (noche si se envió entre 17:00 y 13:59 del día siguiente → noche anterior; si no, día).

**Lote**: (casa, fecha, turno). Un documento con casa pero sin turno propio toma el del lote más reciente del mismo remitente en 16 h.
`comprobante_transferencia` y `recibo_gasto` también se asignan (son evidencia de entrega y de egresos).

**Listo para conciliar**: tiene `planilla_ventas` **y** pasaron 10 minutos desde `ultimo_documento_at`.
Un documento que llega a un lote ya `conciliado` lo devuelve a `listo` (se re-concilia).

## Armado del lote para `ConciliadorCierre` (`App\Domain\Cierre\ArmadorLote`, PHP puro)

| Documento | → clave del lote |
|---|---|
| `planilla_ventas` | `planilla` (mismos nombres: encargada, fecha, turno, ingresos, otros_ingresos, total_ingresos, egresos, total_egresos, total, linkser, qr, efectivo, efectivo_en_sobre, garzones, personal) |
| `pago_chicas` | `pago_chicas` = filas (nombre, manillas, bot, shampagne, limpieza, total) |
| `control_shampagne` | `control_shampagne` = filas (nombre, total); `pagado` NO se infiere |
| `reporte_pos` | `pos.total_cobros`, `pos.efectivo` (=ventas_efectivo), `pos.qr`, `pos.bebida_chicas` (categoría que contenga "BEBIDA CHICA"), `pos.shampagne` (categoría "CAJERA" exacta; si no hay, null), `pos.limpieza` (cantidad del ítem que contenga "COMISION LIMPIEZA" × `ReglasPago::limpiezaPorChica`; si no hay ítem, 0) |
| `ticket_chicas` | `pos.chicas` = {NOMBRE: {precio: cantidad}} sumando bebidas por precio |
| `sobre` | `sobre.monto` (si hay varios, el del documento más reciente) |
| `comprobante_transferencia` con `destino_cuenta` oficial | `comprobantes_qr[]` = {monto, cuenta_destino, titular} |
| `comprobante_transferencia` a otra cuenta, `recibo_gasto` | `evidencia_egresos[]` (fase US6; el conciliador aún no la usa) |
| texto del mensaje con "Venta total"/"Qr"… | `texto` = `ParserTextoCierre::parsear()` |

Varios documentos del mismo tipo (la misma planilla en dos fotos): se usa el de **mayor confianza**; si dos planillas
distintas (totales diferentes) caen en el mismo lote → el lote queda `conciliado` con alerta "dos planillas distintas" y estado REVISAR.
