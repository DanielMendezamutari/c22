# Research — 005 Conciliación de Cierres con IA

Análisis manual de un cierre real para definir qué debe extraer la IA y qué debe verificar el sistema.
Caso dorado: **Casa 22 VIP, turno Día, miércoles 14-10-2026, encargada Fanny Morón** (imágenes en `fixtures/`, fuera de git).

## 1. Documentos que forman un cierre

| # | Documento | Datos clave (caso real) |
|---|---|---|
| A | **Planilla "VENTAS"** (formulario impreso, a mano) | Cajera: Fanny Morón · 14-10-26 · Día · Miércoles. Garzones: Cuevas vendió 300, pagado 127 (firma "QR"). Personal: Fanny Lim 20, Fanny Caja 169 = 189. **Ingresos**: Caja chica 1000, Ventas bebidas 500, Shampagne 1000, Comisión limpieza 20 → **Total 1520**. **Egresos**: Chicas 840, Garzones 127, Personal 189, Almuerzos 90, Taxis chicas 17+20+17=54, Otros 17 (almuerzo) → **Total 1317**. Total 203 · Linkser — · **QR 113** · **Efectivo 90** · Sobre 90 |
| B | **Hoja "PAGO DE CHICAS"** | Fiorella SM: manillas 200 + shampagne 300, limpieza 10, total 500 · Yubinka: shampagne 300, limpieza 10, total 300 · Fanny: manillas 40, total 40 → **Total 840** |
| C | **Reporte de Ventas POS** (ticket térmico) | Servidor "VIP ALEMANA CASA22 - V.491" · Turno 1 - FANNY · 09:15–20:48 · Efectivo 1000 · Otras cuentas (QR) 500 · **Total cobros 1500** · Ítems: agua chica 80, alikal 20, 2 champ 500, coca chica 80, 3 corona chica 80, red bull chica 80 · Categorías: bebida chicas 480, cajera 1000, varios 20 |
| D | **Ticket POS "VENTAS" meseros/chicas** | Debe: Cuevas 0, Fanny 0 · Chica desde móvil: Fanny 1 red bull chica 80 · Fiorella: agua 80, coca 80, 3 corona 80 (5 bebidas) |
| E | **Sobre** | "Fanny T/D efectivo 90 · 14-10-26" |
| F | **Comprobante QR Banco FIE** | PAGO QR **EMITIDO** desde cta 400XXXXX870 a VALVERDE FERNANDEZ RODRIGO ALEJANDRO (1311250961) · **Bs 113** · 14/10/2026 21:10:13 |
| G | **Texto de WhatsApp** | "Casa 22vip · Venta total 1520 · Qr 113 · Link 0 · Efectivo 90 · Fanny 169" |

## 2. Conciliación del caso real (hecha a mano, todo cuadra)

| Regla | Cálculo | Resultado |
|---|---|---|
| R1 Ingresos planilla | 500 + 1000 + 20 = 1520 (la "caja chica 1000" NO suma: es el efectivo cobrado según POS, informativo) | ✅ 1520 |
| R2 Egresos planilla | 840 + 127 + 189 + 90 + 54 + 17 = 1317 | ✅ 1317 |
| R3 Saldo | 1520 − 1317 = 203 = QR 113 + Linkser 0 + Efectivo 90 | ✅ 203 |
| R4 Sobre | Efectivo declarado 90 = sobre 90 | ✅ |
| R5 Hoja de chicas | 500 + 300 + 40 = 840 = egreso "pago de chicas" | ✅ |
| R6 Planilla vs POS | Bebidas 500 + shampagne 1000 = POS 1500; caja chica 1000 = POS efectivo 1000; bebidas chicas 480 + varios 20 = 500 | ✅ |
| R7 Comisión bebidas chica (50%) | Fiorella 5 × 80 = 400 → 200 manillas · Fanny 1 × 80 → 40 | ✅ |
| R8 Comisión shampagne (60%) | 2 botellas × 500; 300 a Fiorella + 300 a Yubinka = 60% de cada una | ✅ |
| R9 Limpieza (10 Bs si la chica genera ≥ 100) | Fiorella ✓ 10, Yubinka ✓ 10, Fanny (40) no → ingreso limpieza 20 | ✅ |
| R10 Flujo QR | QR recibido POS 500 − egresos pagados por QR 387 = **113** = QR declarado. Única combinación que da 387: garzón 127 + Fanny caja 169 + Fanny limpieza 20 + taxis 54 + almuerzo 17 | ✅ (inferido) |
| R11 Flujo efectivo | Efectivo POS 1000 + limpieza 20 − (chicas 840 + almuerzos 90) = **90** = sobre | ✅ (inferido) |
| R12 Comprobante QR | Transferencia 113 = QR declarado; hora 21:10 > cierre POS 20:48 | ✅ monto · ❓ ¿beneficiario autorizado? |
| R13 Texto WhatsApp | 1520 / 113 / 0 / 90 / Fanny 169 = planilla | ✅ |
| R14 Casa | POS "VIP ALEMANA CASA22" = texto "Casa 22vip" | ✅ Casa 22 VIP |

**Conclusión del caso**: cuadra al centavo. Con estos 7 documentos un cierre se puede verificar por completo.

## 3. Puntos que un auditor marcaría (no son acusaciones; requieren regla o aprobación)

1. **La encargada se paga a sí misma por varias vías**: Fanny caja 169 + Fanny limpieza 20 + Fanny como chica 40 = **229 Bs a la misma persona que rinde**. Puede ser correcto, pero debe existir una regla (pago fijo de cajera, ¿puede también figurar como chica?).
2. **Garzón**: Cuevas "total vendido 300" no aparece en el POS (meseros "Debe 0") y se le pagó 127 (42,3%). No hay regla configurada para validarlo.
3. **Dos almuerzos**: 90 en "Pago de almuerzos" + 17 en "Otros: pago almuerzo".
4. **Taxis sin comprobante**: 54 Bs (17 + 20 + 17) con nombres anotados a mano.
5. **Cuenta de origen del QR** (400XXXXX870): hay que saber si es de la casa o personal de la encargada; las ventas por QR (500) entran ahí.

## 4. Por qué falla hoy y cómo llegar a "confiable"

- Hoy la IA clasifica **una foto a la vez** y extrae unos pocos totales. No ve el cierre completo, así que no puede cruzar documentos.
- La IA no debe "decidir" si cuadra: **la IA solo lee; el código verifica**. Las 14 reglas son aritmética determinista.
- **100% no se puede garantizar en la lectura de manuscritos**, pero sí se puede garantizar que **ningún cierre se informe como "conforme" si una sola regla no cuadra o falta un documento**. Un error de lectura se convierte en "revisar", nunca en un informe falso. Con la redundancia de este formulario (los totales aparecen 2–3 veces en documentos distintos), un error de OCR rompe al menos una igualdad y queda detectado.

## 5. Decisiones

- **D1 Lote de cierre**: agrupar fotos y texto por remitente + casa + ventana de tiempo (las encargadas mandan 4–7 mensajes seguidos, a veces en grupos distintos). El lote se concilia cuando llega la planilla y tras 10 min sin mensajes nuevos.
- **D2 Extracción por tipo de documento** con esquema JSON estricto (`responseSchema` de Gemini) y los nombres de campo exactos del formulario impreso.
- **D3 Texto de WhatsApp** con parser determinista (sin IA): `Venta total`, `Qr`, `Link`, `Efectivo`, `<nombre>: monto`.
- **D4 Motor de conciliación** en `Domain` (PHP puro, sin IA), con tolerancia 0 y reglas de comisión configurables por casa.
- **D5 "Entrenamiento"**: Gemini no se re-entrena; se mejora con (a) esquemas precisos, (b) ejemplos reales verificados como referencia en el prompt, (c) un banco de cierres reales con resultado esperado para **medir la precisión** antes de cada cambio, (d) las correcciones del administrador se guardan y alimentan los ejemplos.
- **D6 Informe para administración**: estado del cierre (CONFORME / REVISAR / FALTANTE), cuadro de ingresos, egresos y flujo QR/efectivo, comisiones verificadas contra el POS, documentos faltantes y puntos de auditoría.

---

## 6. Caso dorado 2 — Casa22 (Belén), turno Noche, martes 13-10-2026, encargada Sisiela Barboza

Documentos: planilla VENTAS, PAGO DE CHICAS, CONTROL DE SHAMPAGNE, Reporte de Ventas POS ("BELEN CASA22 - V.491", Turno 2 - SISIELA, 13/10 21:05 → 14/10 09:00), ticket "Chica desde Móvil", sobre "T/N 13-10-26 Efectivo 2837".

| Regla | Cálculo | Resultado |
|---|---|---|
| R1 Ingresos | bebidas 7280 + covers 120 (4×30) + shampagne 1300 + limpieza 60 + "1 papa sin comandar" 20 = 8780 (caja chica 1000 no suma) | ✅ 8780 |
| R2 Egresos | chicas 3680 + garzones 364 + personal 410 + comisión taxis 150 + papel 13 + taxis PT frío 60 = 4677 | ✅ |
| R3 Saldo | 8780 − 4677 = 4103 = QR 1266 + efectivo 2837 | ✅ |
| R4 Sobre | 2837 = sobre | ✅ |
| R6 Planilla vs POS | 7280 + 120 + 1300 + 60 = **8760 = POS total cobros** (limpieza entra al POS como "COMISIÓN LIMPIEZA") | ✅ |
| R7 Chicas vs POS | Ticket por chica: 55 bebidas de 80 + 14 de 100 = **5800 = categoría POS "bebida chicas"**; 50% = 2900 | ✅ |
| R8 Shampagne | Control: Ruby 400, Cloe 300, Luz 600 = 1300 = POS (champ 300+400+600); 60% = 240 + 180 + 360 = 780 | ✅ |
| R5 Pago chicas | 2900 + 780 = **3680** = egreso | ✅ (cada fila de la hoja coincide con el ticket por chica: "12M" = 12 bebidas de 80 → 480; "2H" = 2 Huari de 100 → 100) |
| R9 Limpieza | 6 chicas × 10 = 60 = ingreso | ✅ (ver punto 3) |
| G1 Garzones noche (5% de su venta) | Edwin 4260 → 213 · Cristhian 3020 → 151 · y 4260 + 3020 = **7280 = ventas bebidas** | ✅ |
| Texto | (no se envió texto en este caso) | — |

**Hallazgos para auditoría**
1. **"1 papa sin comandar 20"**: venta cobrada fuera del POS. La encargada la declaró (bien), pero es una fuga de control: el POS no la registró.
2. **Personal que cobra como "chica"**: Edwin (garzón, 40 Bs) y J. Pablo (seguridad, 50 Bs) figuran en la hoja de chicas porque el POS registró bebidas de chica a su nombre. Requiere regla.
3. **Limpieza**: Susana generó exactamente 100 y no pagó limpieza; Luz figura en dos filas (8M + 3M). ¿La regla es "≥ 100" o "> 100", y por chica o por fila?
4. **Encargada**: Sisiela se pagó 282 como cajera (Fanny 169 el día). Con la regla del 3% (ver §7) solo se puede verificar cuando cierran las 4 casas del turno.

## 7. Reglas de negocio confirmadas por el dueño (2026-10-16)

- **Cuenta oficial** para recibir el QR: la del dueño (VALVERDE FERNANDEZ RODRIGO ALEJANDRO, 1311250961). Un comprobante a otra cuenta es alerta.
- **Comisiones iguales en las 4 casas**: bebida de chica 50%, shampagne 60%, limpieza 10 Bs por chica (umbral por confirmar, §6.3).
- **Garzones/barmans de día**: 5% del total de ventas de bebidas del turno, **repartido entre todos los barmans de día** (pueden ser 1, 4 o más).
- **Garzones de noche**: 5% de **su propia** venta.
- **Encargadas**: 3% de la **suma de ventas de las 4 casas**, repartido entre las encargadas del turno, **más una base** (día ≈ 100 Bs, noche ≈ 50 Bs, por confirmar) y **bonos** por superar montos (montos por definir).

**Aplicado al caso 1 (Fanny, día)**: 5% de 500 = 25 Bs para repartir entre barmans de día, pero el garzón Cuevas cobró **127** sobre "300 vendidos" (el POS lo muestra con venta 0). **No cuadra con la regla → alerta**, salvo que exista otra regla para día.

## 8. Bugs encontrados en el código actual
- `inferirSucursalId` revisa "casa22" antes que "vip": el ticket "VIP ALEMANA CASA22" se asigna a Casa22 (Belén) en vez de Casa 22 VIP.
- Un cierre de noche llega a la mañana siguiente (POS 14/10 09:00, planilla 13-10 Noche): la fecha operativa debe ser la de **inicio** del turno, y la hora del mensaje no sirve para inferir día/noche.
