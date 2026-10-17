# Contrato: extracción de documentos de cierre (spec 005, fase 2)

`ExtractorDocumentosCierre::extraer(string $rutaOBase64, string $mime, string $contexto = ''): array` devuelve:

```json
{
  "ia_disponible": true,
  "modelo": "gemini-…",
  "error": null,
  "documentos": [ { "tipo": "…", "confianza": 0.0-1.0, "<tipo>": { …campos del tipo… } } ]
}
```

- Una foto puede traer **varios documentos** (ej. POS + ticket de chicas + Linkser en la misma imagen): un elemento por documento.
- Si la IA falla o no responde JSON válido: `ia_disponible=false`, `documentos=[]`. **Nunca se inventan datos.**
- Números: siempre `number` en Bs (`"1.520"` → 1520, `"1.520,50"` → 1520.5). **Casilla vacía o con raya = 0. Ilegible = null.** (El conciliador trata null como "no se pudo leer" y no da CONFORME.)
- Fechas `YYYY-MM-DD`; turno `"dia"` | `"noche"` (la planilla dice "Día"/"Noche"; "Tarde" → "dia").
- Nombres tal como están escritos (sin corregir ortografía).

## Tipos

| tipo | Qué es | Pistas visuales |
|---|---|---|
| `planilla_ventas` | Formulario impreso "VENTAS" lleno a mano: CAJERA(O), FECHA, TURNO, garzones, personal, INGRESOS, EGRESOS, totales | Encabezado "VENTAS", "PAGOS DIARIOS DEL PERSONAL DE VENTAS (GARZONES)" |
| `pago_chicas` | Formulario "PAGO DE CHICAS": NOMBRE, MANILLAS, BOT., SHAMPAGNE, LIMPIEZA, TOTAL, FIRMA | Encabezado "PAGO DE CHICAS" |
| `control_shampagne` | "CONTROL DE SHAMPAGNE": NOMBRE, TIEMPO, H.ENT., H.SAL., TOTAL, EF O QR | Encabezado "CONTROL DE SHAMPAGNE" |
| `reporte_pos` | Ticket térmico "Reporte de Ventas" con SERVIDOR, Turno, Total Cobros, categorías | "Reporte de Ventas", "SERVIDOR", "Total Cobros" |
| `ticket_chicas` | Ticket térmico "VENTAS" con "Chica desde Movil/PC" y bebidas por chica | "Chica desde Movil" o "Chica desde PC" |
| `reporte_tarjeta` | Ticket Linkser (REPORTE / CIERRE, TOTAL TARJETA) | "LINKSER S.A" |
| `sobre` | Sobre de papel con nombre, fecha, monto, "efectivo" | Sobre blanco escrito a mano |
| `comprobante_transferencia` | Captura de app bancaria: transferencia/pago QR | "Transferencia exitosa", "Pago realizado", "PAGO QR" |
| `recibo_gasto` | Recibo/nota de compra (hielo, insumos, taxi) | — |
| `cierre_barra` | Conteo de inventario del barman (productos/botellas) | Listas de productos y cantidades, sin dinero |
| `otro` | Cualquier otra cosa (incluye hojas de otros negocios, ej. mesas de billar) | — |

## Campos por tipo (nombres exactos)

**planilla_ventas**: `encargada`, `fecha`, `turno`, `dia_semana`,
`garzones` [{`nombre`, `total_vendido`, `pagado`, `firma`}],
`personal` [{`nombre`, `cargo`, `pagado`, `firma`}],
`ingresos` {`caja_chica`, `ventas_bebidas`, `covers`, `shampagne`, `chupete`, `comision_taxis`, `propinas`, `vasos_rotos`, `comision_limpieza`, `privados`, `chuflay`, `aumento_botellas`},
`otros_ingresos` [{`concepto`, `monto`}], `total_ingresos`,
`egresos` {`pago_chicas`, `pago_garzones`, `pago_personal`, `comision_taxis`, `volanteo`, `hielo`, `basura`, `almuerzos`, `cenas`, `taxis_chicas`, `taxis_pt_frio`, `propinas`, `otros`},
`notas_egresos` [{`concepto`, `texto`}] (anotaciones al margen, ej. "20 taxi linkser"),
`total_egresos`, `total`, `linkser`, `qr`, `efectivo`, `faltante`, `sobrante`, `efectivo_en_sobre`.
Firma: `"qr"` | `"efectivo"` | `"firma"` | null.

**pago_chicas**: `filas` [{`nombre`, `notacion` (ej. "12M", "3H 4M"), `manillas`, `bot`, `shampagne`, `limpieza`, `total`, `firma`}], `total`.

**control_shampagne**: `fecha`, `turno`, `filas` [{`nombre`, `total`, `medio` ("qr"|"efectivo"|"tarjeta"|null)}], `total`.

**reporte_pos**: `servidor` (línea SERVIDOR completa, ej. "CORONA CASA22 - V.491"), `turno_texto` (ej. "Turno 2 - NANCY CHURA"), `desde`, `hasta` (fecha y hora), `ventas_efectivo`, `ventas_tarjeta`, `ventas_otras_cuentas`, `total_cobros`, `qr`, `categorias` [{`nombre`, `monto`}], `items` [{`cantidad`, `producto`}], `cortesias` (cantidad), `borrados` [{`cantidad`, `producto`}], `obs_turno` (texto).

**ticket_chicas**: `fecha_hora`, `meseros` [string], `chicas` [{`nombre`, `bebidas` [{`cantidad`, `producto`, `precio`}]}].

**reporte_tarjeta**: `total_tarjeta`, `cantidad`, `fecha_hora`.

**sobre**: `nombre`, `fecha`, `monto`, `texto`.

**comprobante_transferencia**: `monto`, `fecha_hora`, `origen_titular`, `origen_cuenta`, `destino_titular`, `destino_cuenta`, `motivo`, `banco`, `nro_operacion`.

**recibo_gasto**: `concepto`, `monto`, `fecha`, `proveedor`.

**cierre_barra**: `responsable`, `fecha`. **otro**: `descripcion`.
