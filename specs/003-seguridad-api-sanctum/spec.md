# Feature Specification: Seguridad de la API — Autenticación Obligatoria, Eliminación de Puertas Traseras y Autorización por Rol

**Feature Branch**: `003-seguridad-api-sanctum`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Seguridad de la API /api/v1 con Laravel Sanctum. 113 de 118 rutas no exigen autenticación (verificado: GET /usuarios sin token responde 200), el login web acepta contraseñas fijas 123456/1234/9999 para cualquier usuario (puerta trasera), la verificación de sucursal POS tiene una clave maestra quemada que reescribe el token de la sucursal, el secreto del webhook tiene valor por defecto en el código, y la configuración del agente POS con credenciales SQL Server está versionada en git. Objetivos: autenticación obligatoria salvo rutas públicas y de máquina; eliminar puertas traseras; secretos solo desde configuración del entorno; sacar credenciales del repositorio; autorización por rol. Sin romper panel web, bot ni app."

## Contexto *(por qué existe esta feature)*

La plataforma es el libro de auditoría central de Grupo Punto Frío (Constitución, Principio V). Hoy cualquier persona que conozca la URL pública puede, sin iniciar sesión, crear usuarios administradores, cambiar PINs, registrar rellenos y bajas, aprobar gastos, disparar envíos por el WhatsApp de la empresa y leer registros internos del sistema. Esto invalida la trazabilidad por actor que exige la Constitución (Principios III y V, "Inmutabilidad de Registros"): un registro hecho sin identidad no es auditable.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Bloqueo de Acceso Anónimo a la Plataforma (Priority: P1) 🎯 MVP

Como dueño de Grupo Punto Frío, necesito que ninguna operación ni consulta de la plataforma pueda hacerse sin una sesión válida, para que cada dato y cada movimiento quede atado a una persona identificada.

**Why this priority**: Es la exposición activa más grave hoy en producción. Por sí sola elimina el acceso anónimo a usuarios, inventario, auditorías, dinero y WhatsApp.

**Independent Test**: Sin credenciales, intentar listar usuarios, registrar un relleno y enviar un mensaje de WhatsApp: los tres deben ser rechazados como "no autenticado". Con una sesión válida del panel web y de la app, las mismas pantallas siguen funcionando.

**Acceptance Scenarios**:

1. **Given** una petición sin credenciales, **When** solicita cualquier operación protegida (p. ej. listar usuarios), **Then** recibe un rechazo "no autenticado" con mensaje en formato estructurado y no se devuelve ningún dato.
2. **Given** una credencial inválida, vencida o revocada, **When** solicita una operación protegida, **Then** recibe el mismo rechazo "no autenticado".
3. **Given** el panel web recibe un rechazo "no autenticado", **When** la persona está navegando, **Then** el panel la lleva a la pantalla de inicio de sesión (comportamiento ya existente del panel).
4. **Given** una persona en la pantalla de login de la app, **When** carga la lista de sucursales e inicia sesión con PIN, **Then** puede hacerlo sin sesión previa (rutas públicas de acceso).
5. **Given** una sesión válida del panel web o de la app, **When** usa sus pantallas habituales, **Then** todo funciona igual que antes de la feature.

---

### User Story 2 - Eliminación de Puertas Traseras y Secretos en el Código (Priority: P1)

Como responsable de seguridad, necesito que no exista ninguna contraseña, clave maestra o secreto escrito en el código o en el repositorio, para que conocer el código fuente no otorgue acceso a la plataforma, a la caja POS ni al bot.

**Why this priority**: Las contraseñas fijas permiten entrar como cualquier usuario web, incluido el super administrador, aun con la US1 implementada. Sin esta historia, la US1 queda anulada.

**Independent Test**: Intentar iniciar sesión en el panel web con `123456`, `1234` o `9999` para un usuario cuya contraseña es otra: debe fallar. Enviar la antigua clave maestra POS: debe ser rechazada y el token de la sucursal no debe cambiar. Arrancar el sistema sin secreto de webhook configurado: las peticiones del bot deben rechazarse en lugar de aceptarse con un valor por defecto.

**Acceptance Scenarios**:

1. **Given** un usuario web con contraseña propia, **When** intenta entrar con una de las contraseñas fijas históricas, **Then** el acceso se rechaza.
2. **Given** una petición de sincronización POS con la antigua clave maestra, **When** llega al sistema, **Then** se rechaza y ningún token de sucursal se modifica.
3. **Given** que falta el secreto de webhook en la configuración del entorno, **When** el bot u otro cliente envía cualquier valor, **Then** el sistema rechaza la petición (falla cerrado) y deja registro del error de configuración.
4. **Given** el repositorio, **When** se revisa su contenido actual, **Then** no contiene credenciales reales del agente POS; solo una plantilla de ejemplo con valores ficticios.
5. **Given** un usuario sin contraseña web ni PIN registrados, **When** se despliega la feature, **Then** existe un procedimiento documentado para que un administrador le asigne credenciales antes de que pierda el acceso.

---

### User Story 3 - Clientes de Máquina Siguen Operando con su Propio Secreto (Priority: P1)

Como operador del sistema, necesito que el bot de WhatsApp y el agente POS sigan funcionando 24/7 con su secreto de máquina, sin requerir una sesión de persona, para que la ingesta de cierres y transacciones no se interrumpa.

**Why this priority**: El modo operativo actual es "Solo WhatsApp". Si el bot deja de entregar mensajes, la auditoría diaria de las 4 casas se detiene.

**Independent Test**: Con el secreto correcto, el bot reporta su estado, descarga la lista de grupos autorizados, informa grupos descubiertos y entrega mensajes; el agente POS sincroniza transacciones. Con secreto incorrecto o ausente, ambos son rechazados. La consulta de estado del bot funciona tanto para el bot (secreto) como para el panel web (sesión de persona).

**Acceptance Scenarios**:

1. **Given** el bot con el secreto correcto, **When** llama a sus operaciones de máquina (estado, lista de grupos autorizados, grupos descubiertos, entrega de mensajes), **Then** son aceptadas sin sesión de persona.
2. **Given** el agente POS con el token correcto de su sucursal, **When** sincroniza transacciones, **Then** se aceptan y quedan asociadas solo a esa sucursal.
3. **Given** la consulta de estado del bot, **When** la hace el bot con su secreto o el panel web con una sesión válida, **Then** ambas son aceptadas; **When** no trae ninguna de las dos, **Then** se rechaza.
4. **Given** un secreto de máquina, **When** se usa para una operación de persona (p. ej. crear usuarios), **Then** se rechaza: los secretos de máquina solo abren sus propias operaciones.

---

### User Story 4 - Autorización por Rol (Priority: P2)

Como dueño, necesito que cada persona solo pueda hacer lo que corresponde a su rol, para que un barman no pueda crear administradores ni una auxiliar contable aprobar pagos.

**Why this priority**: Con US1–US3 ya no hay acceso anónimo; esta historia reduce el riesgo interno. Requiere más validación con el uso real de cada pantalla, por eso va después.

**Independent Test**: Con sesión de barman, intentar crear un usuario o cambiar un PIN: rechazo "sin permiso". Con sesión de administrador: se permite. Con sesión de contadora, gestionar caja chica: se permite; con auxiliar contable, aprobar caja chica: rechazo.

**Acceptance Scenarios**:

1. **Given** una sesión de rol operativo (barman, garzón, cajera), **When** intenta una operación administrativa (usuarios, PINs, sucursales, catálogo de productos, recetas, motivos de baja, envío de WhatsApp, gestión del bot, registros del sistema), **Then** recibe un rechazo "sin permiso" distinguible del "no autenticado".
2. **Given** una sesión de administrador o super administrador, **When** realiza operaciones administrativas, **Then** se permiten.
3. **Given** una sesión de rol operativo, **When** realiza operaciones de barra (abrir/cerrar turno, conteos, rellenos, bajas, recepción, traspasos, cobro), **Then** se permiten.
4. **Given** una sesión web de dueño, contadora o auxiliar contable, **When** usa una operación, **Then** se permite o rechaza según los permisos que su rol ya recibe al iniciar sesión (dueño: ver; contadora: caja chica, recaudación y liquidaciones; auxiliar: revisar caja chica y ver recaudación).

---

### Edge Cases

- **Sesión vencida a mitad del turno**: el barman con la app offline acumula registros; al reconectar con credencial vencida, la sincronización recibe "no autenticado" y debe pedir nuevo PIN sin perder la cola local.
- **Usuario desactivado con sesión abierta**: sus credenciales vigentes dejan de servir en la siguiente petición.
- **Cambio de PIN**: las sesiones previas de ese usuario quedan revocadas.
- **Secreto rotado**: tras cambiar el secreto en el servidor, el bot con el secreto viejo es rechazado hasta actualizarse; el despliegue debe actualizar ambos lados juntos.
- **Rol desconocido o vacío**: se trata con el mínimo privilegio (solo ver tablero), nunca como administrador.
- **Rutas no listadas o nuevas**: toda ruta nueva es protegida por defecto; ser pública requiere declararlo explícitamente.
- **Respuesta a navegadores**: los rechazos se devuelven siempre en formato estructurado, nunca como redirección a una página HTML de login.

## Requirements *(mandatory)*

### Functional Requirements

**Autenticación (US1)**
- **FR-001**: El sistema DEBE rechazar como "no autenticado" toda petición a la API sin credencial válida, salvo las rutas públicas de FR-002 y las de máquina de FR-008.
- **FR-002**: Las únicas rutas públicas DEBEN ser: inicio de sesión por PIN, inicio de sesión web, lista de sucursales para el login y lista de usuarios por sucursal para el login. Los datos que devuelven DEBEN limitarse a lo necesario para mostrar la pantalla de login (sin PINs, hashes, teléfonos ni datos financieros).
- **FR-003**: Toda ruta nueva DEBE quedar protegida por defecto; declarar una ruta pública DEBE ser una decisión explícita y visible.
- **FR-004**: Los rechazos DEBEN distinguir "no autenticado" de "sin permiso" y entregarse en formato estructurado con un mensaje legible.
- **FR-005**: Las credenciales de un usuario DEBEN dejar de ser válidas cuando el usuario se desactiva o cambia su PIN/contraseña.

**Puertas traseras y secretos (US2)**
- **FR-006**: El login web DEBE aceptar únicamente la contraseña o PIN registrados del usuario; DEBEN eliminarse las contraseñas fijas de respaldo.
- **FR-007**: DEBE eliminarse la clave maestra POS y cualquier lógica que modifique el token de una sucursal como efecto de una petición de sincronización.
- **FR-008**: Los secretos de máquina (webhook del bot, secreto interno de despacho, tokens de sucursal) DEBEN provenir solo de la configuración del entorno o de la base de datos; si faltan, el sistema DEBE rechazar las peticiones (fallar cerrado) y registrar el error.
- **FR-009**: El repositorio NO DEBE contener la configuración real del agente POS; DEBE existir una plantilla de ejemplo con valores ficticios y la configuración real DEBE quedar excluida del control de versiones.
- **FR-010**: DEBE existir un procedimiento (comando administrativo) para asignar contraseña web a un usuario, y un reporte previo al despliegue que liste los usuarios web que quedarían sin credencial válida.

**Clientes de máquina (US3)**
- **FR-011**: Las operaciones del bot (reporte de estado, lista de grupos autorizados, grupos descubiertos, entrega de mensajes) DEBEN aceptar el secreto de webhook sin sesión de persona.
- **FR-012**: La consulta de estado del bot DEBE aceptar el secreto de webhook o una sesión de persona válida.
- **FR-013**: Un secreto de máquina NO DEBE abrir ninguna operación de persona.
- **FR-014**: El bot NO DEBE tener un secreto por defecto en su código; sin secreto configurado DEBE negarse a arrancar con un mensaje claro.

**Autorización (US4)**
- **FR-015**: Las operaciones administrativas (usuarios y PINs, sucursales, catálogo de productos, proveedores, recetas, motivos de baja, alertas de discrepancia, gestión y envío por WhatsApp, registros del sistema, autorización de reconteo) DEBEN estar restringidas a administrador y super administrador.
- **FR-016**: Las operaciones de barra DEBEN estar disponibles para barman, garzón, cajera y administrador, limitadas a la sucursal de la sesión salvo para administradores globales.
- **FR-017**: Las sesiones web DEBEN respetar los permisos por rol que ya se asignan al iniciar sesión (dueño, contadora, auxiliar contable, super administrador).
- **FR-018**: Un rol desconocido DEBE recibir el mínimo privilegio.

**Verificación**
- **FR-019**: DEBEN existir pruebas automatizadas que verifiquen: rechazo sin credencial en una muestra de cada grupo de rutas, rechazo de las contraseñas fijas y de la clave maestra, acceso correcto del panel web, de la app y del bot, y rechazo "sin permiso" por rol.
- **FR-020**: La suite de pruebas existente DEBE seguir pasando completa.

### Key Entities

- **Sesión de persona (credencial)**: credencial emitida al iniciar sesión por PIN (app) o contraseña (web); pertenece a un usuario, lleva los permisos de su rol y puede revocarse.
- **Secreto de máquina**: valor compartido entre el servidor y un cliente automático (bot, agente POS); abre solo las operaciones de ese cliente.
- **Rol**: operativos (barman, garzón, cajera), administrativo (admin) y web (super administrador, dueño, contadora, auxiliar contable), cada uno con un conjunto de permisos.
- **Ruta pública**: operación declarada explícitamente como accesible sin credencial.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las operaciones de la API, salvo las públicas y de máquina declaradas, rechaza peticiones sin credencial (hoy: ~4%).
- **SC-002**: 0 contraseñas, claves maestras o secretos reales en el código y en el contenido actual del repositorio.
- **SC-003**: Tras el despliegue, el bot sigue entregando el 100% de los mensajes de los grupos autorizados y el informe de cierre llega a "Control de Recaudo" sin intervención.
- **SC-004**: El panel web y la app completan sus flujos principales (login, tablero, recaudaciones, gestión WhatsApp; login PIN, apertura y cierre de turno) sin errores nuevos.
- **SC-005**: Un rol operativo no puede ejecutar ninguna operación administrativa (0 de N en la prueba de matriz de permisos).
- **SC-006**: Toda operación de escritura queda atribuida a un usuario identificado o a un cliente de máquina identificado.

## Assumptions

- Se reutiliza el mecanismo de credenciales que el sistema ya emite en ambos logins y que los clientes ya envían en cada petición; no se cambia el formato de login ni de las respuestas exitosas.
- El rol `admin` de la app equivale a super administrador en la web, como ya hace el login web actual.
- Vencimiento de credenciales: sin vencimiento automático en esta feature (se revocan al desactivar usuario o cambiar PIN/contraseña); una política de vencimiento se evalúa aparte para no forzar re-login de barmen a mitad de turno.
- La rotación de las credenciales ya expuestas (clave SQL Server de la caja, secreto de webhook, clave maestra POS) y la limpieza del historial de git son tareas operativas del usuario, fuera del alcance del código; esta feature las documenta en un checklist de despliegue.
- Las operaciones internas de despacho de Laravel hacia el bot (puerto local) ya usan su propio secreto interno y no cambian, salvo eliminar valores por defecto.
- La app Flutter está en espera; basta con que su flujo de login PIN y operaciones de barra sigan funcionando con la credencial que ya envía.
