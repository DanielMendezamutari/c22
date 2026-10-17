# Contrato: lote de cierre (spec 005, fase 3)

Un **lote** es el cierre de UNA casa en UN turno: todos los documentos que lo prueban, vengan del grupo que vengan.
La fase 3 corre en **modo sombra** (`CONCILIACION_V2=sombra`): arma y concilia lotes, guarda el resultado y
**no envía nada ni toca planillas/turnos del flujo actual**. Solo con `CONCILIACION_V2=activo` (fase 4) reemplaza al flujo viejo.

## Datos

**`documentos_cierre`**: `id`, `inbound_id` (FK whatsapp_mensajes_inbound), `lote_id` (nullable FK), `tipo`
(los 11 del contrato de extracción), `datos` (json: el objeto del tipo), `confianza`, `sucursal_id` (nullable),
`fecha_operativa` (date, nullable), `tipo_turno` (`dia`|`noche`|null), `evidencia_casa` (`encabezado`|`encabezado_remitente`|`grupo`|`encargada`|`remitente`|`texto`|null),
timestamps. Índices: (`lote_id`), (`sucursal_id`, `fecha_operativa`, `tipo_turno`).

**`lotes_cierre`**: `id`, `sucursal_id`, `fecha_operativa`, `tipo_turno`, `encargada` (de la planilla), `remitentes` (json: teléfonos),
`estado` (`abierto` → `listo` → `conciliado`), `ultimo_documento_at`, `resultado` (json de `ResultadoConciliacion::toArray()`),
`conciliado_at`, timestamps. **Único** (`sucursal_id`, `fecha_operativa`, `tipo_turno`).

## Deduplicación en sombra

Antes de extraer documentos, si `hash_media` es null se calcula SHA-256 del archivo en `public_path(media_path)`
o, como alternativa, en `storage/app/public/` quitando el prefijo `storage/`.
Si existe un inbound anterior (ID menor), creado dentro de las 48 horas anteriores al envío, con el mismo hash
**y documentos ya registrados**, no se crean documentos para el reenvío y se registra un warning con ambos IDs.
Se completan también las huellas nulas de los inbounds anteriores con documentos para detectar imágenes históricas.
La comparación es independiente del grupo y del remitente. Sin archivo ni hash no se presume duplicación.
Dentro de un mismo inbound se conserva un solo documento por tipo y datos idénticos, independientemente del orden
de las claves JSON o de la confianza; se conserva la lectura de mayor confianza. Las listas mantienen su orden.
Documentos con datos o tipos distintos se conservan.
No se cambia el estado ni los efectos contables del flujo viejo.

## Reglas de asignación (en este orden; la primera que aplica gana)

**Casa del documento**
1. `reporte_pos.servidor` con alias de casa (`sucursales.alias_documentos`) → `encabezado`. Gana siempre, aunque contradiga al grupo.
2. Casa del grupo (`sucursal_whatsapp_grupos.sucursal_id`) → `grupo`.
3. Encargada de la planilla registrada como cajera de una sola casa → `encargada`.
4. **Remitente**: si el mismo `sender_phone` envió en las últimas 16 h un documento con casa por `grupo`, esa casa → `remitente`. Los encabezados se propagan con la regla del cierre del remitente descrita abajo, que prevalece sobre esta asignación inicial.
5. Alias en el texto adjunto → `texto`.
6. Ninguna → sin casa (queda pendiente; se reintenta cada vez que entra otro documento del mismo remitente).

**Fecha operativa y turno del documento**
1. `planilla_ventas.fecha/turno`, validada con `ProcesarMensajeWhatsAppJob::fechaOperativa()` (±2 días del envío).
2. `reporte_pos.desde`: fecha de inicio; turno `noche` si la hora de inicio es ≥ 17:00 o < 05:00, si no `dia`.
3. `control_shampagne.fecha/turno`, `sobre.fecha`.
4. Si no hay: hora de envío (noche si se envió entre 17:00 y 13:59 del día siguiente → noche anterior; si no, día).

**Lote**: (casa, fecha, turno). Los documentos sin turno propio heredan **fecha y turno** de los documentos con
turno propio de la misma casa y `sender_phone` no vacío, cuyos inbounds estén dentro de **±8 h**, extremos incluidos.
Tienen turno propio `planilla_ventas`, `reporte_pos` con `desde` válido (incluida la validación de ±2 días), y
`control_shampagne` con turno reconocido. La prioridad es planilla > POS > control; a igual prioridad se elige el
más cercano en tiempo (y el menor ID en caso de empate). Si hay documentos propios de día y noche, primero se
elige el turno del documento propio más cercano; en empate se usa la prioridad anterior. Luego se aplica la
prioridad dentro de ese turno. La fecha de un sobre también se sustituye al heredar el cierre.
Se evalúa en ambos órdenes de llegada y al reprocesar documentos ya asignados. Al llegar una referencia se
corrigen los documentos sin turno propio de su ventana, usando para cada uno su propia ventana de ±8 h.
Los movimientos son transaccionales: se eliminan lotes vacíos y se reabren origen y destino, reconstruyendo
remitentes y encargada, borrando resultados anteriores y conservando alertas de asignación, igual que en la
regla de encabezado del remitente.
`comprobante_transferencia` y `recibo_gasto` también se asignan (son evidencia de entrega y de egresos).

**Encabezado del cierre del remitente (prevalece sobre el grupo)**

El cierre del remitente reúne documentos del mismo `sender_phone` no vacío cuyos inbounds se crearon dentro de
**±8 horas del inbound evaluado** (extremos incluidos), con **igual `fecha_operativa` y `tipo_turno`**.
Se evalúa al asignar cada documento y al reprocesar uno existente, tanto si llega primero la planilla como si llega primero el POS.

- Si los documentos con evidencia `encabezado` identifican **una sola casa**, todos los de ese cierre con evidencia
  `grupo`, `remitente`, `texto` o sin casa se asignan a esa casa con evidencia `encabezado_remitente` y al lote
  correspondiente. Los ya vinculados por `encabezado_remitente` mantienen esa vinculación. Un comprobante de
  un grupo global sin casa también hereda este encabezado. La evidencia `encargada` y los propios encabezados se conservan.
- Si existen encabezados de **dos casas distintas**, no se reasigna ningún documento. Se guarda la alerta exacta
  **"El remitente envió documentos de dos casas distintas"** en `resultado.alertas_asignacion` y `resultado.alertas`
  de los lotes involucrados, sin duplicarla. La conciliación posterior conserva esta alerta.
- Los movimientos son transaccionales. Los lotes vacíos se eliminan. Los que ganan o pierden documentos quedan
  `abierto`, con `conciliado_at` null y sin resultado anterior de conciliación (se preservan las alertas de asignación).
  Se reconstruyen sus remitentes y encargada desde los documentos restantes y se reinicia la espera de conciliación.

**Listo para conciliar**: tiene `planilla_ventas` **y** pasaron 10 minutos desde `ultimo_documento_at`.
Un documento que llega a un lote lo devuelve a `abierto` y reinicia la espera (se re-concilia).

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
