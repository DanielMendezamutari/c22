# Feature Specification: Informes de Cierre Basados en las Encargadas y Entrega Confiable al Grupo "Control de Recaudo"

**Feature Branch**: `004-informes-cierre-encargadas`

**Created**: 2026-10-16

**Status**: Approved (aprobado por el usuario el 2026-10-16: "haz lo propuesto, me parece bien")

**Input**: User description: "Llegan mensajes al grupo, pero parece que está tomando el cierre de los barmans y eso no es correcto: tiene que tomar los cierres que envían a los grupos las encargadas. Los mensajes llegan al grupo Control de Recaudo pero no se leen, quedan como 'esperando'. Las encargadas mezclan los cierres; por eso está la API de Google para analizar los cierres de cada casa. Los barmans solo cuentan sus productos con la app. El recaudo varía y son varias encargadas de distintos turnos, no solo Fanny."

## Contexto y evidencia (producción, 2026-10-16, solo lectura)

- Log de Laravel: `CerrarTurnoUseCase: Informe despachado automáticamente tras cierre de Turno #50…#56`. **Cada vez que un barman cierra su conteo en la app se despacha el informe de recaudo**, sin planilla de la encargada: el informe sale con el barman como "Encargada" y montos en cero.
- Últimos 25 mensajes recibidos: ninguna imagen desde el 2026-10-14; todos "desconocido / por confirmar". Remitentes llegan como identificadores LID (`268263925760142`), no como teléfonos.
- Log del bot: `error in sending message again … Cannot destructure property 'user' of 'jidDecode(...)'` en `relayMessage` / `sendMessagesAgain` (Baileys 6.7.24, rama *legacy*). Cuando un teléfono del grupo pide reenviar un mensaje que no pudo descifrar, el reenvío falla → **"Esperando mensaje" permanente**.
- `GeminiVisionAuditorService::simularClasificacion` devuelve, ante cualquier fallo de la IA, una planilla falsa (Casa Belén, 32.690 Bs, "Fanny Moron", 90% de confianza) que cierra turno y despacha informe.
- `LoginPinUseCase` escribe el PIN en texto plano en el log, y ese log es legible sin autenticación vía `GET /whatsapp/bot-logs`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El informe de recaudo nace solo del cierre de la encargada (Priority: P1) 🎯 MVP

Como dueño, quiero que el informe que llega a "Control de Recaudo" se genere únicamente a partir del cierre que la encargada de turno envía por WhatsApp, para auditar el dinero real de cada casa; el conteo de productos del barman en la app es control de inventario y no debe generar un informe de recaudo.

**Why this priority**: Hoy el grupo recibe informes vacíos y atribuidos al barman cada vez que se cierra un conteo; es el error que ve el usuario.

**Independent Test**: Cerrar un turno desde la app como barman → no sale ningún informe. Enviar la foto de la planilla de cierre de la encargada al grupo de la casa → sale un informe con los montos de la planilla y el nombre de la encargada.

**Acceptance Scenarios**:

1. **Given** un barman cierra su turno en la app, **When** el cierre se confirma, **Then** no se despacha ningún informe a "Control de Recaudo" (el cierre de inventario sigue funcionando igual).
2. **Given** una encargada envía la foto de su planilla de cierre de caja, **When** la IA la reconoce como planilla de caja de una casa, **Then** se despacha un informe con los montos de esa planilla y el nombre de la encargada leído de la planilla o del remitente, nunca el del barman.
3. **Given** un turno ya tiene la planilla de una encargada, **When** llega otra imagen clasificada como planilla para el mismo turno, **Then** no reemplaza en silencio la anterior: se conserva y queda registrada para revisión, y el informe ya enviado no se duplica salvo reenvío manual.
4. **Given** la foto de un conteo de productos o de botellas, **When** la IA la analiza, **Then** se clasifica como cierre de barra/inventario y no genera informe de recaudo.

---

### User Story 2 - El sistema nunca inventa cifras (Priority: P1)

Como auditor, necesito que si la IA no puede analizar una imagen, el sistema lo diga y espere revisión, en lugar de rellenar montos de ejemplo, porque un informe con cifras inventadas es peor que no tener informe.

**Why this priority**: Viola la Constitución (Principio I: datos empíricos; Principio V: trazabilidad). Un informe falso puede imputar faltantes a una encargada.

**Independent Test**: Con la IA no disponible (sin clave o con error), enviar una imagen → queda "pendiente de revisión", sin turno cerrado, sin planilla y sin informe.

**Acceptance Scenarios**:

1. **Given** la IA no está configurada o falla con todos los modelos, **When** llega una imagen, **Then** el mensaje queda en estado de revisión con confianza 0 y no se crean planillas, gastos ni informes.
2. **Given** la clave de la IA está en la configuración del servidor, **When** la configuración está cacheada en producción, **Then** el sistema la lee correctamente.

---

### User Story 3 - Los informes se pueden leer en el grupo (Priority: P1)

Como dueño, quiero que los mensajes del bot en "Control de Recaudo" se puedan leer en todos los teléfonos del grupo, sin quedar en "Esperando mensaje".

**Why this priority**: Aunque el informe sea correcto, si nadie lo puede leer no sirve.

**Independent Test**: Despachar un informe de prueba al grupo, reiniciar el bot, y verificar desde 2 teléfonos distintos del grupo (uno que nunca lo había abierto) que el mensaje se lee.

**Acceptance Scenarios**:

1. **Given** un teléfono del grupo no pudo descifrar un mensaje del bot, **When** pide el reenvío, **Then** el bot lo reenvía correctamente (sin el error de reenvío del log), incluso si el bot se reinició entre medio.
2. **Given** una reconexión pedida desde la web o una purga de llaves, **When** el bot se reinicia, **Then** existe una sola conexión activa (nunca dos sesiones simultáneas con la misma cuenta).
3. **Given** el bot envía un informe, **When** ese mismo mensaje vuelve como evento entrante, **Then** el bot lo ignora (no se re-ingesta como mensaje "desconocido").

---

### User Story 4 - Encargadas reconocidas por datos, no por nombres fijos (Priority: P2)

Como administrador, quiero que el sistema reconozca a las encargadas y la casa de cada cierre a partir del personal registrado en la plataforma, porque hay varias encargadas por turno y el personal rota.

**Why this priority**: Hoy los nombres (Fanny, Sisiela, Mabel, Sergio…) están escritos dentro de las instrucciones de la IA; cada cambio de personal exige tocar código.

**Independent Test**: Registrar una encargada nueva (rol cajera) en una casa → la IA recibe su nombre como encargada de esa casa sin cambiar código.

**Acceptance Scenarios**:

1. **Given** las cajeras/encargadas activas registradas por casa, **When** se analiza una planilla, **Then** las instrucciones de la IA listan esos nombres por casa y los barmans/garzones registrados como "no encargados".
2. **Given** no hay personal registrado para una casa, **When** se analiza una planilla, **Then** la IA identifica la casa por membrete y deja el nombre como lo lea.

---

### Edge Cases

- La encargada envía la planilla antes de que el barman cierre su turno: el cierre de la encargada sigue generando el informe (no depende del cierre del barman).
- La encargada envía varias fotos del mismo cierre (planilla general, champagne, desglose): solo una genera informe; las demás enriquecen o quedan para revisión, sin varios informes en el grupo.
- Mensajes antiguos enviados por el bot antes de la corrección: no se pueden recuperar; la corrección aplica a mensajes nuevos.
- El bot reinicia con la librería nueva: la sesión debe conservarse; si WhatsApp exige nuevo QR, el procedimiento de despliegue lo indica.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El cierre de turno desde la app (barman/cajera) NO DEBE despachar informes de recaudo.
- **FR-002**: El informe de recaudo DEBE despacharse solo cuando llega una planilla de caja clasificada por la IA (o por reenvío manual del administrador).
- **FR-003**: El nombre de "Encargada" en el informe DEBE salir de la planilla o del remitente, nunca del barman del turno.
- **FR-004**: Una segunda planilla para el mismo turno NO DEBE sobrescribir la primera; DEBE quedar registrada y marcada para revisión, sin nuevo despacho automático.
- **FR-005**: La IA DEBE distinguir un nuevo tipo "cierre de barra / conteo de inventario", que se registra sin generar informe ni cerrar turnos.
- **FR-006**: Si la IA no está disponible o falla, el sistema NO DEBE generar datos de ejemplo; el mensaje queda "pendiente de revisión" sin efectos contables.
- **FR-007**: La clave y el modelo de la IA DEBEN leerse de forma que funcionen con la configuración cacheada de producción.
- **FR-008**: El bot DEBE poder reenviar mensajes que los destinatarios no pudieron descifrar, también tras un reinicio (memoria persistente de lo enviado).
- **FR-009**: El bot DEBE garantizar una única conexión activa ante reconexiones, purgas o caídas.
- **FR-010**: El bot DEBE ignorar como entrada sus propios mensajes enviados.
- **FR-011**: El bot DEBE usar una versión de la librería de WhatsApp compatible con los identificadores LID de los grupos actuales.
- **FR-012**: Las instrucciones de la IA DEBEN construirse con el personal activo registrado por casa (encargadas = rol cajera; barmans/garzones), sin nombres fijos en el código.
- **FR-013**: El sistema NO DEBE registrar PINs ni contraseñas en los logs.
- **FR-014**: DEBEN existir pruebas automatizadas para FR-001, FR-002, FR-004, FR-005 y FR-006, y la suite existente debe seguir en verde.

### Key Entities

- **Planilla de caja (encargada)**: cierre de dinero del turno por casa; fuente única del informe.
- **Cierre de barra (barman)**: conteo de productos; control de inventario, no de recaudo.
- **Mensaje entrante**: foto/texto recibido; clasificación IA, confianza, estado (procesado, requiere_confirmacion, requiere_revision).
- **Mensaje enviado por el bot**: guardado de forma persistente para atender reenvíos.

## Success Criteria *(mandatory)*

- **SC-001**: 0 informes despachados por cierres de barman en la app (verificable en el log).
- **SC-002**: 100% de los informes del grupo muestran montos y encargada de una planilla real; 0 informes con datos de ejemplo.
- **SC-003**: Los informes se leen en todos los teléfonos del grupo (0 "Esperando mensaje" en 3 días de operación).
- **SC-004**: Agregar una encargada nueva no requiere cambios de código.

## Assumptions

- "Encargada" = usuario con rol `cajera` en la plataforma; las encargadas que no estén registradas se reconocen por lo que la IA lea en la planilla.
- La actualización de la librería del bot se prueba en producción con respaldo de la sesión (`auth_info_baileys`) y vuelta atrás documentada; no hay entorno de WhatsApp de pruebas.
- Los reenvíos manuales desde la web (`despachar-informe-cierre`, `auditar-hoy`) siguen disponibles para el administrador.
- La autenticación de la API (spec 003) es independiente y se implementa aparte.
