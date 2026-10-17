# Feature Specification: Conciliación Completa de Cierres de Encargadas con IA e Informe para Administración

**Feature Branch**: `005-conciliacion-cierre-ia`
**Created**: 2026-10-16
**Status**: Approved (clarificaciones del dueño 2026-10-16)
**Input**: "Cada encargada pone su nombre en cada planilla; de ahí debe salir la información. Las encargadas envían a diferentes grupos y mezclan todo (taxis, pagos de chicas…). Quiero entrenar a la IA para un reporte 99–100% confiable y saber si hay pérdidas o alguien está robando, con un informe para administración bien estructurado." Análisis de dos cierres reales en [research.md](research.md).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El cierre se audita como un lote completo (Priority: P1) 🎯 MVP

Como dueño, quiero que todas las fotos y textos de un cierre (planilla, hoja de chicas, control de shampagne, reporte POS, ticket de chicas, sobre, comprobante QR, texto) se junten aunque la encargada los mande en varios mensajes o a grupos distintos, y que el sistema verifique todas las cuentas entre ellos.

**Why this priority**: Una foto aislada no prueba nada; la redundancia entre documentos es lo que permite detectar errores y faltantes.

**Independent Test**: Cargar las fotos del caso dorado 1 (Fanny) y del caso 2 (Sisiela) → cada uno queda como un lote con su casa, fecha operativa y turno correctos, y el informe dice CONFORME con todas las verificaciones en verde.

**Acceptance Scenarios**:
1. **Given** una encargada envía 4–7 mensajes de su cierre, en uno o varios grupos, **When** llega la planilla y pasan 10 min sin mensajes nuevos de ella, **Then** se forma un lote con todos sus documentos y se concilia.
2. **Given** un cierre de noche enviado la mañana siguiente, **When** se arma el lote, **Then** la fecha operativa es la de la planilla (inicio del turno) y el turno es "noche".
3. **Given** un lote sin alguno de los documentos esperados, **When** se concilia, **Then** el informe lista qué documento falta y el estado no puede ser CONFORME.

---

### User Story 2 - Verificación determinista sin depender de la IA (Priority: P1)

Como auditor, quiero que la IA solo lea los documentos y que las cuentas las verifique el sistema con reglas fijas, para que un error de lectura nunca produzca un "conforme" falso.

**Independent Test**: Con los datos de los casos dorados, el motor da 100% de verificaciones correctas; alterando un solo número (p. ej. sobre 2837 → 2737) el estado pasa a FALTANTE con la diferencia exacta.

**Acceptance Scenarios**:
1. **Given** los datos leídos de un lote, **When** se concilia, **Then** se evalúan como mínimo: ingresos, egresos, saldo = QR + efectivo, sobre, hoja de chicas = egreso de chicas, planilla = POS, comisión de chicas (50% bebidas de chica, 60% shampagne) contra el POS, limpieza, flujo QR y comprobante.
2. **Given** cualquier verificación que no cuadra, **When** se genera el informe, **Then** el estado es REVISAR o FALTANTE, con la regla, el valor esperado, el declarado y la diferencia.
3. **Given** un dato que la IA no pudo leer, **When** se concilia, **Then** las reglas que lo usan quedan "sin verificar" y el estado no puede ser CONFORME.

---

### User Story 3 - Reglas de pago al personal (Priority: P1)

Como dueño, quiero que el sistema calcule lo que corresponde pagar a chicas, garzones y encargadas según las reglas de la empresa y lo compare con lo que se pagó.

**Independent Test**: Caso 2 → garzones de noche 213 + 151 = 5% de su venta ✅; caso 1 → garzón de día 127 vs 5% de 500 = 25 repartido → alerta.

**Acceptance Scenarios**:
1. **Given** un turno de noche, **When** se verifica el pago de garzones, **Then** cada uno debe recibir el 5% de su propia venta.
2. **Given** un turno de día, **When** se verifica, **Then** el 5% del total de ventas de bebidas se reparte entre los barmans/garzones de día del turno.
3. **Given** las 4 casas cerraron el mismo turno, **When** se verifica el pago de encargadas, **Then** cada una debe recibir su base (día/noche) + su parte del 3% de la suma de ventas de las 4 casas + bonos que correspondan; mientras no cierren las 4, queda "pendiente de verificar".
4. **Given** personal que no es chica (garzón, seguridad, encargada) cobra comisión de chica, **When** se concilia, **Then** se marca como punto de auditoría.

---

### User Story 4 - Informe para administración (Priority: P1)

Como dueño, quiero un informe claro por cierre en el grupo "Control de Recaudo" y un resumen diario de las 4 casas, que muestre si hay pérdidas y quién es responsable.

**Acceptance Scenarios**:
1. **Given** un lote conciliado, **When** se despacha, **Then** el informe incluye: casa, fecha, turno, encargada (de la planilla); estado (CONFORME / REVISAR / FALTANTE); ingresos vs POS; egresos por concepto con su verificación; entrega (QR + sobre); documentos recibidos/faltantes; puntos de auditoría.
2. **Given** las ventas fuera del POS ("sin comandar"), comprobantes a una cuenta distinta de la oficial o pagos sin regla, **When** se genera el informe, **Then** aparecen en "Puntos de auditoría".

---

### User Story 5 - Medir y mejorar la precisión de la IA (Priority: P2)

Como administrador, quiero un banco de cierres reales verificados para medir qué porcentaje de datos lee bien la IA antes de cada cambio, y que mis correcciones sirvan para mejorarla.

**Acceptance Scenarios**:
1. **Given** el banco de cierres dorados, **When** se ejecuta la evaluación, **Then** se reporta el % de campos leídos exactamente por tipo de documento y modelo.
2. **Given** un administrador corrige un dato leído, **When** guarda la corrección, **Then** queda registrada (valor IA vs valor correcto) y el cierre puede volverse caso dorado.

### Edge Cases
- La encargada manda el mismo documento a dos grupos → se deduplica (hash de imagen) y no se cuenta dos veces.
- Una foto contiene varios documentos (caso Sisiela: POS + hoja de chicas + shampagne + sobre en una imagen) → se extraen todos.
- Una chica aparece en dos filas (Luz 8M + 3M) → se suman por nombre antes de comparar con el POS.
- Cierre sin reporte POS → las reglas contra POS quedan "sin verificar".
- Montos con texto o tachones ("17 + 20 + 17 = 54") → se usa el total escrito y se verifica la suma.

## Requirements *(mandatory)*

### Functional Requirements
- **FR-001**: Agrupar en un lote los mensajes de una misma encargada y casa dentro de una ventana de tiempo, aunque vengan de grupos distintos; deduplicar imágenes idénticas.
- **FR-002**: Extraer cada tipo de documento con un esquema estructurado propio: planilla VENTAS (todas sus filas y campos), PAGO DE CHICAS (por fila), CONTROL DE SHAMPAGNE, Reporte de Ventas POS (totales, categorías, ítems, turno, servidor), ticket por chica/mesero, sobre, comprobante bancario, texto de WhatsApp.
- **FR-003**: Interpretar el texto de WhatsApp ("Venta total", "Qr", "Link", "Efectivo", "<nombre>: monto") sin IA.
- **FR-004**: Determinar casa, fecha operativa y turno priorizando planilla y POS (servidor, turno, rango horario) por sobre la hora del mensaje; "VIP" tiene prioridad sobre "CASA22".
- **FR-005**: Evaluar las reglas de conciliación de research.md §2 y §6 con tolerancia 0; cada regla devuelve cumple / no cumple / sin verificar con esperado, declarado y diferencia.
- **FR-006**: Reglas de comisión configurables y comunes a las 4 casas: bebida de chica 50%, shampagne 60%, limpieza 10 Bs por chica sobre un umbral configurable.
- **FR-007**: Pago de garzones: noche 5% de su venta; día 5% del total de bebidas repartido entre los barmans del turno.
- **FR-008**: Pago de encargadas: base por turno + parte del 3% de la suma de ventas de las 4 casas + bonos configurables; se verifica al cerrar las 4 casas.
- **FR-009**: Cuenta(s) oficial(es) de destino del QR configurables; comprobante a otra cuenta = alerta.
- **FR-010**: Estado CONFORME solo si todas las reglas cumplen y no faltan documentos obligatorios.
- **FR-011**: Informe por cierre y resumen diario de las 4 casas con la estructura de US4.
- **FR-012**: Banco de cierres dorados y comando de evaluación de precisión; registro de correcciones del administrador.
- **FR-013**: Sin nombres de casas ni personas en código o prompts (se leen de la BD); los datos personales de los casos dorados no se versionan.

### Key Entities
- **Lote de cierre**: casa, fecha operativa, turno, encargada, mensajes y documentos, estado.
- **Documento extraído**: tipo, datos estructurados, confianza, mensaje de origen.
- **Resultado de conciliación**: reglas evaluadas, estado, diferencias, puntos de auditoría.
- **Reglas de pago**: porcentajes, bases, bonos, umbral de limpieza, cuentas oficiales.
- **Corrección**: campo, valor IA, valor correcto, autor.

## Success Criteria *(mandatory)*
- **SC-001**: Los 2 casos dorados dan CONFORME con 100% de reglas verificadas.
- **SC-002**: Alterar cualquier monto de un caso dorado produce un estado distinto de CONFORME (0 falsos "conforme").
- **SC-003**: ≥ 95% de campos leídos correctamente en el banco dorado (se medirá; meta 99% con más casos).
- **SC-004**: El dueño entiende el estado de cada cierre en menos de 30 segundos de lectura del informe.

## Clarifications

### Session 2026-10-16
- Q: Base de encargadas y bonos → A: Provisional 100 Bs día / 50 Bs noche hasta que administración aclare; el informe muestra siempre una alerta de "base provisional". Bonos sin configurar → alerta.
- Q: Pago de garzones de día (Cuevas 127) → A: Se suman las ventas de bebidas de **todas las casas** del turno de día, se saca el 5% y se reparte entre **todos** los garzones/barmans de día (de todas las casas). Solo se verifica cuando cierran las 4.
- Q: Umbral de limpieza → A: Se cobra cuando la chica genera **más de** 100 Bs (una vez por chica).
- Q: Personal (garzón, seguridad, encargada) que cobra comisión de chica → A: Permitido, pero se marca como alerta.
- Q: Cuenta del QR → A: Cuenta del dueño (configurable; no se versiona).
- Q: ¿Qué casas son de Grupo Punto Frío? → A (2026-10-17): Casa22 (Belén), Corona, Casa 22 VIP, Cuarta Casa ("CIERRES DE INVENTARIO CASA NUEVA") y **Madan, que solo abre de noche de jueves a sábado**. Joker (billar), "Arqueo pocitos" y "Venta por QR licorería" son otros negocios: sus grupos quedan desactivados y la IA no debe asignarlos a ninguna casa.
  → Las reglas entre casas (garzones de día, encargadas) no esperan "4 casas" fijas: esperan las casas que operan ese turno y ese día de la semana (calendario configurable). Pendiente: confirmar qué casas abren de día.

## Assumptions
- El modelo de IA es Gemini (ya contratado); "entrenar" = esquemas + ejemplos verificados + evaluación + correcciones, no re-entrenamiento del modelo.
- "Ventas" para el 3% de encargadas = bebidas + covers + shampagne de cada casa (sin limpieza ni ingresos fuera del POS).
- Los pagos al personal se escriben en Bs enteros: lo esperado por reparto se redondea al boliviano.
- Depende del spec 004 (informe solo desde planilla de encargada) desplegado.
