# Feature Specification: Sistema de Inteligencia y Control de Inventario (Grupo Punto Frío) v1.2

**Feature Branch**: `001-control-inventario-transformaciones`

**Created**: 2026-09-05

**Status**: Draft

**Input**: User description: "Especificación: Sistema de Inteligencia y Control de Inventario (Grupo Punto Frío) v1.2"

## Clarifications

### Session 2026-09-05
- Q: ¿Cómo debe quedar sellado o finalizado el "Resumen a Cajera" una vez que la cajera entrega el dinero en efectivo? → A: Botón "Cobro Recibido / Finalizar Turno": El barman confirma el desembolso, el sistema sella el turno como 'Cobrado' y muestra un recibo con código único durante 60 segundos antes de bloquear nuevos cobros.
- Q: ¿Cómo deben definirse en el sistema los productos transformables (rellenos) y sus tarifas de comisión? → A: Catálogo dinámico de recetas: El administrador define las relaciones válidas (Insumo Origen → Producto Terminado), la tarifa de comisión en Bs por unidad y el ratio esperado de consumo.
- Q: ¿Cómo opera realmente el pago de comisiones por relleno de botellas, los sueldos y los descuentos por faltantes de inventario? → A: Modelo Real de Sueldos y Comisiones: 
  1) La comisión por rellenado de botellas se paga DIARIAMENTE a todos los turnos (tanto día como noche) al finalizar su jornada mediante la pantalla "Resumen a Cajera".
  2) En el Turno Día, quienes atienden la barra son garzones que no tienen sueldo semanal; cobran su jornal diario al finalizar su turno, por lo que cualquier faltante de inventario se descuenta directamente en el día en caja.
  3) En el Turno Noche, los barmen titulares cuentan con un sueldo base semanal fijo aparte de sus comisiones de botellas. Por ello, las sanciones o faltantes de botellas no justificados detectados en las auditorías nocturnas del Ticket Z se acumulan y se descuentan de su sueldo base al finalizar la semana.
- Q: ¿Qué función cumple y cómo debe operar la pantalla de "Liquidación Semanal"? → A: Liquidación Semanal de Sueldos de Barmen (Turno Noche): Es el módulo donde el Administrador consulta y liquida el sueldo semanal de los barmen titulares de la noche. Se eliminan códigos confusos como "2026-W36"; la pantalla cuenta con botones intuitivos ("Esta Semana", "Semana Pasada" o rango de fechas) y presenta tarjetas claras por barman: [Sueldo Base Semanal (+Bs)] - [Faltantes de Inventario Acumulados (-Bs)] = [Sueldo Neto a Pagar en Efectivo (=Bs)], con botón "Registrar Sueldo Pagado" y comprobante.
- Q: ¿Cómo debe estructurarse la autenticación en el Login considerando la seguridad y el rol del Administrador? → A: Selector de Sucursal y PIN Ciego (Opción B): Se elimina por completo el selector público de usuarios de la pantalla de inicio de sesión para no exponer identidades ni roles. El barman ve el selector de sucursal (recordado automáticamente de su rotación semanal) y el teclado numérico de 4 dígitos. Al digitar el PIN, el sistema valida implícitamente las credenciales contra la base de datos; si el PIN pertenece a un Administrador (ej. 9999), se ignora la sucursal seleccionada y la app ingresa de inmediato al Panel Global de Administración (`DashboardAdminScreen`), ya que el Administrador supervisa y gestiona todas las sucursales globalmente sin quedar restringido a ninguna. Si el PIN pertenece a un Barman, ingresa al `DashboardBarmanScreen` de la sucursal seleccionada.
- Q: ¿Cómo debe el Administrador gestionar los usuarios y sus credenciales de acceso? → A: Módulo Administrativo de Usuarios y Cambio de PIN: El Administrador cuenta con un módulo en la app Flutter y endpoints en la API para listar todos los empleados, crear nuevos usuarios (Barman, Garzón, Administrador), editar sus datos, asignar sueldos bases semanales o jornales, sucursales y restablecer o cambiar su PIN de acceso de 4 dígitos.
- Q: ¿Cómo deben operar los traspasos entre sucursales cuando se transfieren múltiples productos en un mismo traslado? → A: Traspasos Multi-Producto (Cabecera y Detalle): Un solo despacho de traspaso puede incluir múltiples productos y cantidades distintas (ej. 12 Coronas, 6 Paceñas y 2 botellas de Fernet). En la recepción, el barman receptor verifica cada ítem de la lista, confirmando unidades conformes y registrando faltantes o roturas por producto antes de cerrar el traspaso.
- Q: ¿Cómo debe el Barman registrar bajas y roturas operativas directas durante su turno de barra? → A: Módulo de Bajas y Roturas en Turno: El acceso "BAJAS / ROTURAS" del panel del barman (`DashboardBarmanScreen`) se enlaza a una pantalla dedicada `BajasRoturasScreen`, donde el barman puede registrar roturas accidentales o mermas operativas en barra seleccionando el producto, la cantidad y el motivo, afectando el inventario físico y descontando la responsabilidad o comisión de forma inmediata y transparente.
- Q: ¿Cómo debe estructurarse el flujo del módulo de Auditoría para que el Administrador lo entienda y opere con total facilidad al conciliar el inventario físico con el Ticket Z de caja? → A: Asistente en 3 pasos guiados: Paso 1 (Selección del turno y sucursal a auditar), Paso 2 (Ingreso visual de ventas de Ticket Z con desglose automático de combos), Paso 3 (Resultado comparativo claro con faltantes en rojo, sobrantes en azul y botón "Descargar Reporte PDF" para respaldo oficial firmado).
- Q: ¿Cómo debe operar el registro de compras e ingreso de mercadería cuando un proveedor entrega varios productos en una sola factura o nota de remisión? → A: Orden de compra multi-producto: Cabecera única (sucursal, proveedor/nota, foto obligatoria de respaldo) con lista dinámica de productos (+ Añadir Producto, cantidad recibida) que actualiza el inventario de todos los ítems en un solo guardado.
- [X] Q: ¿Cómo debe operar la captura de foto de respaldo (dinero en efectivo o comprobante de transferencia) al momento de confirmar el pago de comisiones de relleno? → A: Al presionar "Cobro Recibido / Finalizar Turno", la app Flutter abre obligatoriamente la cámara para capturar la fotografía del dinero en efectivo o comprobante de transferencia bancaria, muestra una vista previa para verificar nitidez y, tras la confirmación, sella el turno como 'Cobrado' vinculando la foto y mostrando el recibo con código único durante 60 segundos.
- Q: ¿Cómo debe operar el sistema ante sustitución de insumos de relleno (ej. rellenar Corona con Moema, o si no hay, con Paceña u Orureña) y ante mezclas compuestas de destilados (Blackstone + Chancellor → Johnnie Walker Red Label)? → A: 1) En el panel de recetas (`RecetasScreen`), el Administrador puede alternar el insumo base activo para la Corona con 1 toque. 2) En el registro de relleno (`TransformacionScreen`), el Barman puede seleccionar qué cerveza utilizó si no había la predeterminada para que el descuento de stock sea exacto al producto abierto. 3) Para destilados, se permite registrar fórmulas compuestas (N insumos con fracciones hacia 1 producto terminado).
- Q: ¿Qué respaldo debe generarse al confirmar el corte inicial de turno (Corte de Apertura)? → A: Al confirmar el inicio de turno en `CorteInventarioScreen`, el sistema genera de forma automática un PDF oficial con membrete del Grupo Punto Frío ("Acta de Conteo Físico Inicial") y despliega la opción para compartirlo inmediatamente en el grupo de WhatsApp de supervisores.
- Q: ¿Cómo debe registrar el Barman la recepción de mercadería cuando llega una nota con múltiples productos desde Licorería Punto Frío o distribuidores? → A: La tarjeta "NUEVO INGRESO" del barman abre un formulario multi-producto donde se agregan todos los artículos recibidos con sus cantidades, proveedor/remisión y fotografía obligatoria de la nota, acreditando el inventario de una sola vez.

### Session 2026-09-06
- Q: ¿Cómo debe proceder el sistema si el barman entrante detecta un faltante respecto al cierre anterior al momento de abrir el turno? → A: Opción A (Apertura con registro inmutable y notificación a WhatsApp): El sistema no bloquea la apertura del turno para no detener las ventas comerciales del local, pero registra la discrepancia inmutablemente en `alertas_discrepancias`, advierte al barman en pantalla, notifica al dispositivo maestro del Administrador y despliega el botón obligatorio para despachar el reporte detallado a WhatsApp (wa.me/59167369293).
- Q: Cuando el Administrador active el interruptor "Este es mi celular personal" en un teléfono nuevo, ¿cómo debe gestionar el sistema los celulares registrados anteriormente? → A: Opción B (Múltiples dispositivos autorizados): Permitir vincular varios dispositivos maestros (ej. celular personal, tablet o teléfono secundario del Administrador), registrándolos en la base de datos y listándolos en el panel de Ajustes del Admin con su nombre de modelo y fecha de enlace, permitiendo desvincularlos individualmente cuando se desee.
- Q: Al generar el PDF del Acta de Cierre de Turno, ¿hacia dónde debe dirigir el botón "COMPARTIR EN WHATSAPP"? → A: Opción A (Hoja nativa de compartir): Al presionar "COMPARTIR EN WHATSAPP", se activa la hoja nativa de WhatsApp/Android mediante `Printing.sharePdf` para que el barman pueda enviar el archivo PDF del acta oficial al grupo de WhatsApp de supervisión de la empresa o a cualquier contacto.
- Q: Cuando el Administrador desactive o elimine un motivo de baja, ¿cómo deben tratarse las bajas y roturas históricas registradas en turnos anteriores con ese motivo? → A: Opción A (Desactivación lógica con historial protegido): Los motivos eliminados se marcan como inactivos (`activo = false`). Las bajas y roturas históricas conservan intacta su descripción para efectos contables y auditorías pasadas, y el selector del barman filtra únicamente motivos activos.
- Q: ¿Qué valores deben tener los botones de atajo rápido por caja en los selectores de conteo y recepción de mercadería? → A: Opción A (Atajos +12, +24, +6 y -12 y teclado numérico directo): Se incorporan botones de atajo rápido por caja (+12, +24, +6 y -12 para correcciones rápidas) junto con la capacidad de tocar el número para escribir libremente cualquier cantidad entera directamente con el teclado táctil.
- Q: ¿Cómo debe comportarse el sistema cuando en un turno la cantidad de botellas rellenadas sea cero (0)? → A: Soporte nativo de Rellenos en Cero: En muchos turnos o jornadas bajas no se realizan transformaciones ni rellenos. El sistema (pantalla de Rellenos, Resumen a Cajera y backend) debe soportar explícitamente 0 unidades producidas y 0 insumos consumidos sin bloquear con mensajes de error, calculando 0.00 Bs de comisión y permitiendo la liquidación normal del turno.
- Q: ¿Cómo debe operar la visualización y re-impresión del Conteo Inicial (Corte de Apertura)? → A: Botón de Conteo Activo Re-imprimible hasta el Cierre: Una vez realizado el conteo de apertura, debe existir un botón accesible en el Dashboard del Barman para previsualizar, imprimir y compartir el PDF del conteo en WhatsApp tantas veces como sea necesario a lo largo del turno. Al ejecutarse el Corte de Cierre de turno, este botón desaparece de la vista activa y los cortes quedan archivados en el Historial de Cortes.

### Session 2026-09-07
- Q: ¿Cómo debe comportarse la apertura de turno, la vinculación del turno activo en la aplicación móvil y la consulta de comisiones en Resumen a Cajera? → A: Sincronización robusta de Turno Activo: Al realizar el Corte de Apertura, el sistema asienta el conteo y resuelve la relación de usuario sin errores. El ID del turno activo queda almacenado y garantizado en la sesión local. Tanto la pantalla de Registro de Rellenos como la de Resumen a Cajera y Corte de Inventario recuperan y sincronizan automáticamente el turno activo desde el backend (`GET /turnos/activo`) si estuviera ausente en memoria, impidiendo registros en turnos ficticios o cerrados y asegurando que las comisiones por relleno configuradas por el administrador (ej. 1 Bs/Corona) se reflejen de inmediato en pantalla.
- Q: ¿Cómo debe autorizarse el registro y consulta de Celulares Maestros del Administrador? → A: Autorización flexible y multi-vía: Los endpoints de gestión de dispositivos maestros autorizan al usuario administrador tanto a través de token Sanctum, parámetro de usuario o resolución por rol en base de datos, garantizando que el switch de activación de Celular Maestro opere de inmediato sin generar errores 403.
- Q: ¿Cómo debe actuar el sistema cuando un barman intente registrar un relleno de Coronas cuyo insumo supere el stock físico disponible en su turno? → A: Opción A (Bloqueo Estricto de Balance de Insumos y Suma de Ingresos en Acta):
  1) Bloqueo estricto: El backend y la app calculan el Stock Disponible en el turno (`Stock Disponible = Conteo Inicial + Recepciones/Ingresos - Bajas - Consumos Previos de Relleno`). Si la cantidad de insumo requerida supera dicho disponible, la operación se bloquea inmediatamente y se despliega un error explicativo en pantalla: *"Stock insuficiente de [Producto] (Disponible en turno: X, Requerido: Y)"*.
  2) Trazabilidad acumulativa de Ingresos en el Acta de Conteo PDF: Todo nuevo ingreso de mercadería recepcionado por el barman durante el turno se suma al inventario disponible del turno. Al previsualizar o re-imprimir el Acta de Conteo de inventario en PDF (y en la app), se incorpora la columna `Ingresos (+)` reflejando claramente: `[Producto] | [Conteo Apertura] | [Ingresos (+)] | [Stock Total Disponible]`, garantizando total transparencia física y respaldo ante supervisores.


## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registro de Rellenos y Liquidación Visual a Cajera (Priority: P1)

Como Barman de Turno Día, quiero registrar mis productos transformados/rellenados en la barra (descontando bajas o roturas y deudas previas si existieran) y disponer de una pantalla de resumen a pantalla completa con números grandes y reloj en tiempo real, para mostrársela a la cajera al finalizar mi jornada (o a primera hora del día siguiente) y cobrar mi comisión en efectivo de forma inmediata e indiscutible.

**Why this priority**: Es el núcleo operativo de valor diario. Resuelve de inmediato la distorsión de comisiones (Principio Constitucional II) y proporciona el incentivo directo para que el barman registre sus datos con honestidad y puntualidad sin depender de una cuenta de usuario para la cajera.

**Independent Test**: Puede probarse de forma aislada registrando 12 botellas transformadas y 1 botella rota; el sistema calcula inmediatamente 11 unidades netas a pagar (11 Bs a razón de 1 Bs/u) y muestra la pantalla de cobro a pantalla completa con el segundero activo y datos de turno inmutables.

**Acceptance Scenarios**:

1. **Given** un barman de turno día que registra 12 botellas de Corona rellenadas y 1 botella declarada como rotura/baja, **When** solicita abrir la vista "Mostrar Resumen a Cajera", **Then** el sistema presenta una pantalla limpia a pantalla completa con "11 Bs a pagar", "11 unidades netas transformadas", el nombre del barman, la fecha/hora y un indicador dinámico animado / reloj con segundero en vivo que demuestra que la pantalla no es una captura estática.
2. **Given** la pantalla de resumen visible para la cajera, **When** la cajera observa el monto y el segundero en movimiento, **Then** tiene la certeza visual de que el monto corresponde al turno en curso sin requerir iniciar sesión en el sistema.
3. **Given** la entrega del dinero en efectivo o transferencia por parte de la cajera, **When** el barman presiona el botón "Cobro Recibido / Finalizar Turno", **Then** el sistema activa obligatoriamente la cámara del dispositivo para capturar la fotografía del dinero en efectivo o comprobante de transferencia, muestra una vista previa para validación y, al confirmar, transiciona el turno al estado 'Cobrado', vincula la evidencia fotográfica inmutable, genera y muestra un código alfanumérico único de recibo durante 60 segundos con cuenta regresiva, y bloquea permanentemente la pantalla para impedir cobros duplicados.

---

### User Story 2 - Recepción de Compras y Mercadería Multi-Producto con Evidencia Fotográfica (Priority: P2)

Como Barman o Administrador, quiero registrar compras o ingresos de mercadería de proveedores conteniendo múltiples productos en una sola orden o factura, capturando obligatoriamente una fotografía del comprobante o de los productos recibidos, para actualizar de inmediato el stock físico de todos los artículos y proteger la trazabilidad de mi turno.

**Why this priority**: Evita que los faltantes de entregas de proveedores o almacén central se imputen injustamente al turno del barman (Principio Constitucional III y Regla de Evidencia) y agiliza el abastecimiento sin requerir un registro separado por cada producto.

**Independent Test**: Puede probarse registrando un ingreso de stock externo con 2 productos distintos (ej. 10 fardos de Paceña y 5 botellas de Vodka) adjuntando la fotografía de la nota; el sistema valida la imagen y actualiza de inmediato el stock de ambos productos.

**Acceptance Scenarios**:

1. **Given** un usuario recibiendo mercadería de proveedores, **When** intenta guardar el ingreso sin adjuntar fotografía, **Then** el sistema bloquea el guardado y exige capturar la fotografía de respaldo de la nota de remisión o lote físico.
2. **Given** un ingreso guardado con fotografía y hora registrada, **When** el administrador consulta el historial del turno, **Then** puede visualizar la imagen adjunta, la hora exacta de recepción y el detalle de cantidades añadidas al inventario.
3. **Given** una compra que incluye varios productos distintos en la misma nota o factura, **When** el usuario añade múltiples líneas de productos y confirma el registro con la fotografía adjunta, **Then** el sistema procesa la orden de forma atómica (`compras_detalles`) e incrementa el inventario de cada producto en la sucursal correspondiente.

---

### User Story 3 - Gestión de Turnos de 12 Horas, Cortes y Fracciones de Licores (Priority: P2)

Como Barman entrante o saliente, quiero registrar el conteo físico de inventario al inicio y cierre de cada turno de 12 horas, registrando botellas enteras y fracciones predefinidas de licores abiertos (0.25, 0.50, 0.75), para delimitar con exactitud mi responsabilidad sobre el inventario.

**Why this priority**: Cumple con el Principio Constitucional III (Cortes de 12 horas y fracciones), eliminando la dilución de responsabilidades entre turnos cuando las botellas quedan abiertas.

**Independent Test**: Puede probarse realizando el corte de inicio con 3.50 botellas de ron (3 enteras y 1 a mitad = 0.50) y corte de cierre con 2.75 botellas; el sistema calcula un consumo exacto de 0.75 botellas atribuible exclusivamente a ese turno.

**Acceptance Scenarios**:

1. **Given** un barman en el proceso de corte de turno, **When** ingresa el stock de botellas abiertas, **Then** la interfaz le permite seleccionar de forma ágil y en un solo toque fracciones de 0.25, 0.50, 0.75 o unidades completas.
2. **Given** el cierre de turno completado y firmado digitalmente por el barman, **When** se inicia el turno siguiente, **Then** el stock final del turno anterior se transfiere automáticamente como stock inicial inmutable del nuevo turno.

---

### User Story 4 - Traspasos de Stock Inter-Sucursales Multi-Producto con Custodia en Tránsito y Discrepancias (Priority: P3)

Como Barman o Administrador, quiero registrar traspasos de múltiples productos y cantidades distintas entre sucursales (Casa22, Casa Coron, Madan) en una sola orden de despacho, manteniendo el lote completo en estado "En Tránsito" hasta que la sucursal receptora verifique producto por producto la llegada física, registrando unidades conformes y mermas en tránsito individuales si hubieran faltantes o roturas.

**Why this priority**: Resuelve la fuga de inventario en traslados entre sedes y garantiza la trazabilidad multi-sucursal y multi-producto eficiente (Principio Constitucional V).

**Independent Test**: Puede probarse enviando un lote con 24 botellas de Corona y 12 latas de Paceña desde Casa22 a Casa Coron; el inventario de Casa22 se reduce para ambos productos; Casa Coron recibe 22 Coronas conformes (2 rotas) y 12 Paceñas intactas; el sistema suma las unidades conformes en destino y clasifica las 2 Coronas como "Merma en Tránsito" auditables.

**Acceptance Scenarios**:

1. **Given** un traspaso registrado desde Casa22 hacia Casa Coron conteniendo múltiples productos, **When** el lote sale de la barra de origen, **Then** el sistema descuenta las unidades de cada producto del stock disponible de Casa22 y coloca la orden en estado global "En Tránsito".
2. **Given** una orden de traspaso en estado "En Tránsito", **When** el barman de Casa Coron confirma la recepción física revisando ítem por ítem, **Then** el sistema acredita en destino únicamente las unidades conformes de cada producto, asienta las diferencias como "Merma en Tránsito" con notificación al administrador y cierra el ciclo de custodia.

---

### User Story 5 - Módulo de Auditoría de Ventas Reales (Ticket Z), Desglose de Combos, Faltantes y Sobrantes (Priority: P1)

Como Administrador / Auditor, quiero ingresar las ventas reales del cierre de caja (Ticket Z) en el módulo de auditoría de la app Flutter —incluyendo tanto productos individuales como combos o baldes que el sistema descompone automáticamente— para cruzarlas con el inventario físico reportado por el barman, identificando faltantes (en rojo con sanción) y sobrantes (en azul informativo sin penalización), y emitiendo las liquidaciones correspondientes.

**Why this priority**: Es la herramienta que materializa el control financiero de la empresa, cerrando la fórmula constitucional de balance de masa y permitiendo recuperar pérdidas económicas según la modalidad de pago de cada turno sin penalizaciones injustas en caso de sobrantes.

**Independent Test**: Puede probarse ingresando un Ticket Z con 5 Baldes de 6 Coronas (30 botellas) y 10 Coronas sueltas (total 40 botellas); el sistema descompone automáticamente los baldes a unidades físicas, compara contra la salida del inventario físico y resalta faltantes (rojo) o sobrantes (azul).

**Acceptance Scenarios**:

1. **Given** un turno cerrado con ventas reales cargadas por el administrador desde el Ticket Z conteniendo combos y productos sueltos, **When** el sistema calcula el desglose automático mediante `recetas_combos` y aplica la fórmula de auditoría `(Stock Inicial + Ingresos + Traspasos - Materia Prima + Prod. Terminado) - Bajas - Stock Final`, **Then** compara el resultado contra las ventas totales desglosadas y reporta las diferencias exactas.
2. **Given** una diferencia negativa (faltante injustificado donde consumo físico > ventas), **When** el administrador genera el reporte de auditoría, **Then** el sistema marca la fila del turno/barman en color rojo de alerta y habilita la generación de un registro de deuda/sanción.
3. **Given** barmen titulares del Turno Noche con sueldo base semanal, **When** el administrador consulta la Liquidación Semanal de Sueldos mediante botones amigables ("Esta Semana", "Semana Pasada"), **Then** el sistema presenta el sueldo base semanal de cada barman, resta el total de sanciones acumuladas por faltantes de inventario de la semana, calcula el sueldo neto exacto a pagar en efectivo y habilita el botón "Registrar Sueldo Pagado".
4. **Given** un turno donde las ventas del Ticket Z superan la salida física del inventario (`Ventas > Consumo Físico`), **When** el administrador genera el reporte de auditoría, **Then** el sistema cataloga la diferencia como 'Sobrante de Inventario' destacada en color azul/ámbar informativo, sin imputar deudas ni sanciones al barman.
5. **Given** un administrador realizando una auditoría en la app Flutter, **When** accede al módulo de auditoría, **Then** el sistema presenta un asistente guiado de 3 pasos (Paso 1: Seleccionar turno cerrado de la sucursal; Paso 2: Ingresar las ventas del Ticket Z con descomposición de combos; Paso 3: Resumen claro con diferencias en rojo/azul y botón "Descargar Reporte PDF" para generar e imprimir el documento oficial de auditoría).

---

### User Story 6 - Análisis de Ratio de Conversión y Detección de Mermas Anómalas (Priority: P2)

Como Administrador, quiero visualizar el ratio de conversión empírico (cantidad de materia prima en latas gastadas vs. botellas terminadas logradas) por barman y por sucursal, para detectar consumos excesivos o fugas disfrazadas de merma.

**Why this priority**: Da cumplimiento al Principio Constitucional I (Descubrimiento Empírico de Rendimiento), identificando ineficiencias de operación o sospechas de robo sin imponer equivalencias teóricas arbitrarias.

**Independent Test**: Puede probarse registrando dos barmen: Barman A usa 15 latas para 10 botellas (ratio 1.50) y Barman B usa 11 latas para 10 botellas (ratio 1.10); el sistema resalta al Barman A con advertencia de merma fuera de tolerancia.

**Acceptance Scenarios**:

1. **Given** registros continuos de transformaciones de cerveza en lata a botella Corona, **When** el administrador consulta el reporte de rendimiento, **Then** el sistema presenta el ratio promedio histórico por sucursal y lista el coeficiente individual de cada barman.
2. **Given** un barman con un ratio que supere el umbral de tolerancia respecto al promedio histórico (ej. ratio > 1.35), **When** el administrador evalúa el turno, **Then** el sistema emite una alerta visual destacada indicando posible fuga o desperdicio excesivo.

---

### User Story 7 - Acceso Seguro por PIN Ciego, Rotación Semanal y Bypass Admin en App Flutter (Priority: P1)

Como Barman o Administrador, quiero abrir la app en mi teléfono o en la barra, confirmar la sucursal de mi semana (en caso de barman) e ingresar directamente mi PIN ciego de 4 dígitos en un teclado numérico táctil sin exponer listas desplegables de usuarios; si soy Administrador, quiero que mi PIN me otorgue acceso global directo sin necesidad de seleccionar una sucursal específica.

**Why this priority**: Protege la confidencialidad de la nómina de empleados (evita filtrar nombres o roles en pantalla pública), agiliza el acceso en menos de 3 segundos y dota al administrador de una experiencia de supervisión global sin fricciones entre sucursales.

**Independent Test**: Puede probarse abriendo la app Flutter sin seleccionar usuario, digitando el PIN de barman "1234"; la app reconoce implícitamente al barman y abre el turno en la sucursal activa. Luego, cerrando sesión e ingresando el PIN de admin "9999", la app entra directamente a `DashboardAdminScreen` con alcance omnicanal.

**Acceptance Scenarios**:

1. **Given** un barman abriendo la app Flutter, **When** visualiza la pantalla de login, **Then** la pantalla muestra únicamente el selector de sucursal (recordado automáticamente) y el teclado numérico PIN ciego, sin listas ni desplegables con nombres de empleados.
2. **Given** un barman digitando su PIN de 4 dígitos, **When** se completa el cuarto dígito, **Then** el sistema valida implícitamente el PIN contra la base de datos, reconoce su identidad y abre su turno en `DashboardBarmanScreen` para la sucursal seleccionada.
3. **Given** un usuario con rol de Administrador digitando su PIN (ej. 9999), **When** se valida el PIN, **Then** la app ignora cualquier sucursal seleccionada y redirige de inmediato a `DashboardAdminScreen` para administrar todas las sucursales globalmente.
4. **Given** una contingencia donde la conexión a internet del hosting compartido falla, **When** el personal técnico accede a los ajustes protegidos de la app Flutter, **Then** puede cambiar la URL base por una IP y puerto local (ej. `http://192.168.0.7:81/api`) para operar en red local.

---

### User Story 8 - Gestión del Catálogo Maestro de Productos por el Administrador (Priority: P2)

Como Administrador / Auditor, quiero poder dar de alta nuevos productos en el catálogo oficial de la empresa (ej. si llega un nuevo lote o marca de cerveza, licor o insumo) directamente desde la app Flutter o la API, especificando si es insumo o terminado, y si se contabiliza por unidades o por cuartos de licor (0.25, 0.50, 0.75), para que aparezca disponible de forma inmediata en las barras de todas las sucursales sin requerir intervención técnica en la base de datos.

**Why this priority**: Permite que el catálogo crezca dinámicamente de 20 a 21 o más productos de manera controlada y centralizada (Principio Constitucional V), impidiendo que los barmen creen productos arbitrarios o duplicados.

**Independent Test**: Puede probarse creando desde la app del administrador el producto "Cerveza Paceña Lata 355ml" (tipo: insumo, unidad: unidad); verificar que se guarde vía `POST /api/v1/productos` y aparezca de inmediato disponible en la lista de productos para cortes y recetas.

**Acceptance Scenarios**:

1. **Given** un administrador autenticado en la app Flutter con PIN, **When** accede al módulo "Catálogo de Productos" y presiona "+ Nuevo Producto", **Then** el sistema presenta un formulario para ingresar: nombre, código/SKU opcional, tipo (insumo, terminado, ambos), unidad de medida (unidad, fraccion_cuartos) y si es transformable.
2. **Given** los datos del nuevo producto completados válidamente, **When** el administrador confirma el guardado, **Then** el backend Laravel lo almacena en la tabla `productos`, lo activa globalmente y retorna código 201 Created.
3. **Given** un nuevo producto registrado exitosamente en el catálogo, **When** un barman en cualquier sucursal abre la pantalla de corte de inventario o recepción de mercadería, **Then** el nuevo producto aparece listado de inmediato en su catálogo disponible.

---

### User Story 9 - Gestión Integral de Usuarios y PINs por el Administrador (Priority: P2)

Como Administrador, quiero disponer de un módulo en la app Flutter para dar de alta nuevos empleados (Barmen, Garzones, Administradores), editar sus datos, asignarles sucursales de trabajo y restablecer o cambiar sus códigos PIN de 4 dígitos cuando lo soliciten o por motivos de seguridad, para mantener el control y la rotación del personal sin requerir soporte de base de datos.

**Why this priority**: Es indispensable para la operación continua de los locales; el personal rota y requiere cambios de credenciales y altas de nuevos barmen de forma autónoma por la administración.

**Independent Test**: Acceder al módulo "Gestión de Usuarios", crear un nuevo usuario "Juan Pérez" con rol barman y PIN "4567"; posteriormente editar el usuario y cambiar su PIN a "8888"; verificar que el nuevo PIN sea el único válido para autenticarlo.

**Acceptance Scenarios**:

1. **Given** un administrador autenticado en el panel global, **When** accede a la opción "Gestión de Usuarios", **Then** el sistema muestra la lista completa de empleados con su rol, estado (activo/inactivo) y sucursal asignada.
2. **Given** el formulario de nuevo usuario o edición de usuario existente, **When** el administrador ingresa un nuevo PIN de 4 dígitos y presiona guardar, **Then** el sistema almacena el hash seguro en la base de datos y actualiza inmediatamente las credenciales activas del usuario.
3. **Given** un usuario desactivado por el administrador, **When** intenta ingresar su PIN en la pantalla de login, **Then** el sistema deniega el acceso indicando que la cuenta se encuentra inactiva.

---

### User Story 10 - Declaración Directa de Bajas y Roturas Operativas en Barra (Priority: P2)

Como Barman en turno activo, quiero pulsar el botón "BAJAS / ROTURAS" de mi panel de inicio para registrar de inmediato botellas caídas, mermas de manipulación o productos defectuosos durante el despacho, seleccionando el producto del catálogo y el motivo, para que el sistema descuente el stock físico al instante y la merma quede registrada con transparencia.

**Why this priority**: Permite al barman justificar mermas accidentales en tiempo real, evitando que sean consideradas faltantes injustificados con sanción durante la conciliación con el Ticket Z.

**Independent Test**: Abrir "BAJAS / ROTURAS" desde el panel de barman, registrar 2 botellas de Corona rotas accidentalmente; verificar que se genere el registro de baja vinculado al turno actual y se descuente del inventario físico.

**Acceptance Scenarios**:

1. **Given** un barman en su pantalla de inicio (`DashboardBarmanScreen`), **When** presiona la tarjeta "BAJAS / ROTURAS", **Then** el sistema abre de inmediato la pantalla `BajasRoturasScreen` con el catálogo de productos disponibles en su sucursal.
2. **Given** la pantalla de bajas, **When** el barman selecciona el producto, ingresa la cantidad rota o defectuosa, el motivo de la merma y confirma, **Then** el sistema asienta el movimiento en el turno activo, reduce el stock físico y muestra un mensaje de confirmación con el resumen de la baja.

---

### Edge Cases

- **Intento de cobro con captura de pantalla o cobro duplicado**: Si un barman intenta engañar a la cajera mostrando una captura estática, la ausencia de animación y reloj en tiempo real lo delata. Asimismo, una vez que el turno entra en estado 'Cobrado' tras presionar el botón de confirmación, cualquier intento de abrir nuevamente la pantalla muestra un banner inmutable indicando "Turno ya cobrado con código [XYZ] el [FECHA/HORA]", imposibilitando el cobro duplicado.
- **Auditoría posterior al desembolso de la comisión (Turno Día)**: Si la cajera ya pagó la comisión del turno de día según la pantalla del barman, y horas después el administrador descubre un faltante en la auditoría del Ticket Z, el sistema crea un saldo deudor (sanción) asignado a la ficha del barman para deducirse automáticamente en la pantalla de cobro del próximo turno (o a más tardar al día siguiente).
- **Turnos de Noche con comisiones semanales**: Los barmen del turno noche no cobran a diario con la cajera mediante la pantalla de cobro individual; sus turnos se consolidan semanalmente y el administrador utiliza el Informe de Liquidación Semanal para pagar el neto (comisiones menos faltantes acumulados).
- **Sobrante de Inventario (Ventas > Consumo Físico)**: Cuando las ventas del Ticket Z superan las salidas físicas declaradas, el sistema marca el evento como 'Sobrante' en color azul/ámbar para auditoría informativa; no se genera sanción ni deuda al barman, reservándose el registro para que la administración revise errores de conteo o de facturación en caja.
- **Pérdida o daño en mercadería En Tránsito**: Si durante un traspaso entre sucursales se rompen unidades o no llega el lote completo, el receptor registra la recepción declarando lo conforme y la discrepancia; la diferencia se asienta como "Merma en Tránsito" para dictamen y auditoría del administrador sin bloquear el inventario conforme.
- **Pérdida de conectividad en barra durante el registro**: La aplicación móvil en Flutter implementa arquitectura Local-First, almacenando en almacenamiento persistente (SQLite/Hive) todos los conteos de corte, transformaciones y fotos tomadas con marca de tiempo. Al detectar el restablecimiento de conexión, sincroniza en segundo plano automáticamente y muestra un indicador de sincronización completa.
- **Rotación de fin de semana**: Cuando llega el fin de semana y el barman rota a otra casa (ej. de Casa22 a Madan), la app le presenta un aviso al iniciar el primer turno del nuevo ciclo: "¿Sigues en Casa22 o cambias de sucursal?", permitiéndole cambiar a Madan en 1 toque.
- **Modificación fraudulenta de un corte cerrado**: Un corte de turno ya confirmado no puede ser alterado bajo ninguna circunstancia por el barman; cualquier corrección requerirá un registro de ajuste firmado por el administrador con justificación auditable.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE proveer un módulo de registro de "Transformación / Relleno" donde el barman seleccione la receta de transformación activa y especifique la cantidad de insumos consumidos (ej. latas de cerveza), las unidades de producto terminado obtenidas (ej. botellas Corona) y las bajas/roturas ocurridas.
- **FR-002**: El sistema DEBE calcular automáticamente el importe de comisión a pagar al barman multiplicando las unidades netas transformadas (`unidades_terminadas - roturas_declaradas`) por la tarifa de comisión estipulada en la receta de transformación seleccionada.
- **FR-003**: El sistema DEBE contar con una vista optimizada "Resumen a Cajera" para turnos diarios, desplegada en pantalla completa con tipografía de gran tamaño indicando el monto total neto en Bolivianos (Bs) descontando deudas previas si aplican, nombre del barman, fecha del turno, y un reloj dinámico con segundero en vivo / animación continua para certificar que la aplicación está activa y no es una captura de pantalla.
- **FR-004**: El sistema DEBE requerir la captura fotográfica obligatoria mediante la cámara del dispositivo móvil al registrar recepciones de mercadería externa, almacenando la imagen, fecha, hora y usuario receptor.
- **FR-005**: El sistema DEBE permitir el inventariado de licores abiertos mediante la selección directa de fracciones parametrizadas (0.25, 0.50, 0.75 y unidades enteras) al inicio y cierre de cada turno de 12 horas.
- **FR-006**: El sistema DEBE soportar el circuito de "Traspasos" entre sucursales (Casa22, Casa Coron, Madan y futuras), manteniendo los artículos en estado "En Tránsito"; al confirmar recepción física, el sistema debe permitir declarar unidades recibidas conformes y registrar discrepancias (roturas o faltantes) clasificadas automáticamente como "Merma en Tránsito" para su investigación y dictamen por el administrador.
- **FR-007**: El sistema DEBE proveer en la app Flutter (módulo de administración) una interfaz para la carga de ventas reales de caja (Ticket Z) y ejecutar automáticamente mediante la API el cruce de auditoría con base en la ecuación constitucional:
  `Balance = (Stock Inicial + Ingresos + Traspasos - Materia Prima Usada + Producto Terminado) - Bajas - Stock Final`
  comparándolo contra `Ventas Reales + Faltantes Justificados`.
- **FR-008**: El sistema DEBE resaltar en color rojo dentro del tablero de auditoría a aquellos turnos o empleados que presenten faltantes de inventario no justificados tras la conciliación con el Ticket Z.
- **FR-009**: El sistema DEBE permitir al administrador registrar sanciones económicas vinculadas a faltantes de inventario, imputándolas como saldo deudor a la ficha del empleado.
- **FR-010**: El sistema DEBE computar y graficar el ratio de conversión empírico (`materia_prima_consumida / producto_terminado_obtenido`) por sucursal y por barman, emitiendo alertas cuando un ratio exceda el umbral de tolerancia establecido en la receta.
- **FR-011**: El sistema DEBE asegurar que los cortes de turno cerrados sean inmutables para el barman, garantizando que el stock de cierre de un turno sea automáticamente el stock de apertura del siguiente.
- **FR-012**: El sistema DEBE proveer en la vista "Resumen a Cajera" el botón de acción "Cobro Recibido / Finalizar Turno"; al activarse por el barman tras recibir el pago, la aplicación DEBE abrir obligatoriamente la cámara del dispositivo móvil para capturar y validar una fotografía de respaldo del dinero en efectivo o comprobante de transferencia bancaria; tras la confirmación, el sistema transiciona el turno al estado 'Cobrado', vincula la evidencia fotográfica inmutable, congela de manera definitiva los importes, genera un código alfanumérico único de recibo desplegado durante 60 segundos con temporizador regresivo y bloquea permanentemente cualquier intento posterior de cobro duplicado.
- **FR-013**: El sistema DEBE proveer en la app Flutter (pantalla de administración) y en la API RESTful de Laravel los endpoints para gestionar el catálogo dinámico de recetas de transformación y combos, permitiendo crear, listar y editar pares de Insumo Origen → Producto Terminado, asignando la tarifa de comisión en Bs por unidad, el ratio esperado de referencia y el umbral de tolerancia para auditoría de mermas.
- **FR-014**: El sistema DEBE soportar la operativa real de remuneración y comisiones:
  a) **Comisión Diaria de Relleno (Todos los Turnos)**: Todos los barmen (tanto día como noche) cobran diariamente al cierre de su turno sus comisiones netas por botellas rellenadas mediante la pantalla "Resumen a Cajera".
  b) **Cobro Diario de Jornal (Turno Día)**: Quienes atienden la barra de día son garzones que cobran su jornal diario al término del turno; cualquier sanción o faltante de inventario se descuenta en caja ese mismo día.
  c) **Liquidación Semanal de Sueldos (Turno Noche)**: Los barmen titulares de noche cuentan con un sueldo base semanal fijo. La pantalla de "Liquidación Semanal" permite al Administrador seleccionar semanas mediante botones amigables ("Esta Semana", "Semana Pasada"), mostrando por cada barman: [Sueldo Base Semanal (+Bs)] - [Sanciones por Faltantes de Inventario Acumuladas en la Semana (-Bs)] = [Sueldo Neto a Pagar en Efectivo (=Bs)], con botón para marcar el sueldo como pagado y congelar las sanciones liquidadas.
- **FR-015**: La aplicación móvil del barman DEBE implementar una arquitectura orientada a trabajo local (Local-First), almacenando en memoria persistente del dispositivo los conteos de corte, registros de transformación y fotografías ante caídas de red, sincronizándolos automáticamente en segundo plano en cuanto se detecte conectividad activa.
- **FR-016**: La aplicación móvil para barmen DEBE ser desarrollada en Flutter e implementar autenticación ágil mediante un teclado numérico táctil nativo con código PIN de 4 dígitos, manteniendo la sesión persistente de forma segura en el dispositivo.
- **FR-017**: La aplicación móvil Flutter DEBE venir preconfigurada con la URL base de la API en el hosting compartido en la nube, proveyendo un menú de ajustes técnicos protegido para reconfigurar la URL o ingresar una IP y puerto local (ej. `http://192.168.0.7:81/api`) en caso de contingencias de red local.
- **FR-018**: El sistema DEBE gestionar la rotación semanal de los barmen entre sucursales (cambio de local cada fin de semana), permitiendo al barman seleccionar o confirmar su sucursal de trabajo al inicio de la semana y asociando todos los turnos, aperturas, cierres de 12h y comisiones a dicha sede durante todo el ciclo semanal.
- **FR-019**: El sistema DEBE permitir registrar recetas de combos (tabla de equivalencias de combos o baldes a productos terminados) y realizar el desglose automático al cargar las ventas del Ticket Z: cuando el administrador ingrese cantidades de combos vendidos (ej. 5 Baldes de 6 Coronas), el motor de auditoría descompondrá automáticamente las unidades individuales resultantes (-30 Coronas) para su conciliación contra el balance físico de inventario.
- **FR-020**: En caso de que la conciliación de auditoría determine que las ventas del Ticket Z superan la salida física del inventario (`Ventas Reales > Consumo Físico`), el sistema DEBE registrar el resultado como "Sobrante de Inventario", marcando el registro con una alerta visual informativa (color azul/ámbar) para investigación administrativa; dicho sobrante NO generará penalizaciones económicas ni saldos deudores al barman.
- **FR-021**: El sistema DEBE proveer en la app Flutter (módulo de administración) y en la API RESTful de Laravel la funcionalidad para que el Administrador gestione el Catálogo Maestro de Productos (alta de nuevos productos indicando nombre, código de barra, tipo insumo/terminado/ambos, unidad entera o fraccionada en cuartos de licor, y si es transformable), listar todos los productos y modificar su estado o atributos, sincronizándose de inmediato con el catálogo operativo de barra.
- **FR-022**: El inicio de sesión de la app Flutter DEBE prescindir de cualquier lista o menú desplegable de usuarios visible al público; la pantalla debe desplegar únicamente el selector de sucursal (recordado automáticamente de la rotación semanal) y el teclado numérico ciego para PIN de 4 dígitos. Al ingresar el PIN, el sistema valida implícitamente las credenciales; si el PIN pertenece a un Administrador, la app DEBE conceder acceso directo e incondicional al Panel Global de Administración ignorando cualquier sucursal seleccionada.
- **FR-023**: El sistema DEBE proveer en la app Flutter (módulo admin) y en la API RESTful de Laravel los endpoints y pantallas para la gestión integral de empleados: listar usuarios, crear nuevos perfiles (Barman, Garzón, Administrador), activar/desactivar cuentas, asignar sueldo semanal base o jornal diario y cambiar o restablecer su código PIN de 4 dígitos.
- **FR-024**: El sistema DEBE permitir que los traspasos inter-sucursales manejen múltiples productos y cantidades distintas en una sola orden de despacho (`traspasos_detalles`), registrando individualmente en origen las unidades despachadas y en destino las unidades recibidas conformes y las mermas o roturas en tránsito por cada producto transferido.
- **FR-025**: El sistema DEBE habilitar en la pantalla principal del barman (`DashboardBarmanScreen`) el acceso operativo a la pantalla "Bajas y Roturas en Turno", permitiendo registrar mermas o roturas de botellas/insumos con motivo y cantidad, descontando el inventario físico del turno en curso.
- **FR-026**: El rol Administrador es de alcance global y omnicanal sobre todas las sucursales (Casa22, Casa Coron, Madan y futuras), no requiriendo estar asignado a una sucursal fija ni conmutar de sucursal en el login para consultar o auditar inventarios.
- **FR-027**: El sistema DEBE estructurar la pantalla de auditoría del Administrador mediante un asistente visual guiado en 3 pasos (Paso 1: Selección de turno cerrado, Paso 2: Carga de ventas de Ticket Z desglosando combos, Paso 3: Balance comparativo con semáforo de faltantes/sobrantes) e incorporar la generación y descarga de un Reporte de Auditoría en formato PDF oficial para respaldo e impresión.
- **FR-028**: El módulo de recepción de compras y abastecimiento DEBE admitir el registro de múltiples productos y cantidades en una sola orden (`compras_detalles`), exigiendo de manera obligatoria la captura fotográfica del comprobante o remisión para incrementar el stock físico de todos los artículos involucrados.
- **FR-029**: El sistema DEBE permitir al Administrador gestionar integralmente las sucursales de la red comercial (creación de nuevas sucursales, edición de nombre, código y dirección, y suspensión/reactivación con toggle `activo`), asegurando que las sucursales inactivas queden excluidas de inmediato de los selectores operativos de apertura de turno, compras y traspasos.

- **FR-030**: El sistema DEBE permitir al Administrador configurar recetas de transformación dinámicas tanto simples (1 insumo origen → 1 terminado) como compuestas (2 insumos origen → 1 terminado) enlazadas exclusivamente a productos reales de la base de datos, asignando tarifa de comisión (Bs) y ratio, y el Barman DEBE registrar rellenos seleccionando materias primas existentes en su inventario físico sin datos quemados o estáticos.
- **FR-031**: El sistema DEBE proveer en los selectores de conteo de inventario (corte de apertura/cierre) y formularios de ingreso/compra de mercadería la capacidad de ingresar cantidades numéricas enteras directamente por teclado táctil (al pulsar el número o campo numérico) junto con atajos rápidos de incremento por cajas (`+12`, `+24`, `-12`), garantizando que la recepción o conteo de decenas de cajas (ej. 30 cajas = 360 botellas) se realice en menos de 5 segundos sin forzar cientos de pulsaciones individuales.
- **FR-032**: El sistema DEBE proveer en la app Flutter (módulo de administración) y en la API RESTful de Laravel la funcionalidad para la gestión integral de Motivos de Bajas y Roturas (alta de nuevos motivos indicando descripción, edición y eliminación/desactivación), y la pantalla operativa de bajas del barman (`BajasRoturasScreen`) DEBE cargar dinámicamente dichos motivos desde la base de datos sin opciones estáticas ni fijas en el código.
- **FR-033**: Al completar el Corte de Cierre de Turno (`CorteInventarioScreen`), la aplicación DEBE generar de manera obligatoria el "Acta Oficial de Cierre de Turno y Balance de Inventario" en PDF vectorial (detallando stock inicial, ingresos recibidos, transformaciones efectuadas, bajas/roturas descontadas y stock final físico) y desplegar el diálogo con el botón destacado "COMPARTIR EN WHATSAPP", garantizando que el reporte oficial sea transmitido de inmediato al grupo de administración.
- **FR-034**: El sistema DEBE comparar automáticamente en el backend, al momento de abrir un turno (`POST /turnos/abrir`), el conteo físico inicial declarado contra el corte de cierre del último turno cerrado en esa misma sucursal; si se detecta cualquier discrepancia ($Cierre_{anterior} \neq Apertura_{entrante}$), el sistema DEBE registrar una alerta de fuga inter-turnos en `alertas_discrepancias`, advertir al barman entrante en pantalla y desplegar una alerta prioritaria en el teléfono móvil del Administrador (banner flotante rojo en `DashboardAdminScreen` y notificación en el dispositivo con detalle de sucursal, producto, cantidades y botón de llamada/WhatsApp a los responsables).
- **FR-035**: El sistema DEBE validar de forma estricta y previa el balance de stock disponible en el turno (`Stock Disponible = Stock Apertura + Ingresos de Mercadería Recepcionados - Bajas/Roturas - Consumos Previos`) antes de asentar cualquier transformación o relleno; si la cantidad de insumos requerida supera el stock disponible en la barra, el sistema DEBE rechazar la transacción con error HTTP 400 explícito impidiendo saldos negativos y sobregiro de insumos.
- **FR-036**: El sistema DEBE reflejar los nuevos ingresos de mercadería recepcionados durante el turno activo en el Acta de Conteo de inventario (PDF y vista móvil), incorporando la columna `Ingresos (+)` (`[Producto] | [Apertura] | [Ingresos (+)] | [Total Disponible]`), permitiendo re-imprimir y compartir por WhatsApp un balance físico fiel y acumulativo del turno en curso.
- **FR-037**: El sistema DEBE proveer en la app Flutter (módulo de administración) y en la API RESTful de Laravel la funcionalidad para la gestión integral de Proveedores comerciales (creación, edición, consulta y estado `activo`), registrando nombre comercial, persona de contacto, teléfono/WhatsApp, NIT/CI y dirección física.
- **FR-038**: En el formulario de Recepción de Mercadería (`ingreso_mercaderia_screen.dart`), el sistema DEBE desplegar un selector con búsqueda rápida de proveedores activos registrados en la base de datos, permitiendo al barman seleccionar el proveedor emisor en lugar de ingresar texto libre genérico.
- **FR-039**: El sistema DEBE proveer un módulo centralizado de Supervisión y Auditoría Operativa por Sucursal en el Dashboard del Administrador (`InformeSucursalesScreen`), permitiendo seleccionar cualquier casa comercial (Casa22, Corona, Madan, etc.) y auditar el turno activo en curso o turnos pasados cerrados.
- **FR-040**: El sistema DEBE compilar y generar un Informe Operativo Integral en formato PDF (`informe_operativo_turno_pdf_service.dart`) para cualquier turno seleccionado por el Administrador, desglosando: Metadatos de jornada, Conteo de Apertura, Recepciones de Mercadería (con notas/facturas), Traspasos Entrantes y Salientes, Bajas/Roturas justificadas, Rellenos/Transformaciones efectuadas (con comisiones), Balance de Stock en custodia/cierre, y Liquidación Económica del barman (sueldo base + comisiones brutas = total a pagar).
- **FR-041**: En el módulo de despacho de traspasos (`enviar_traspaso_screen.dart`), el sistema DEBE cargar dinámicamente las sucursales destino reales desde la API `/sucursales` (excluyendo la sede emisora) y el catálogo de productos reales desde `/productos`, eliminando datos estáticos o quemados en código.
- **FR-042**: El sistema DEBE validar y comprobar en tiempo real que la sucursal de origen disponga de stock físico suficiente en su turno/barra para cada ítem antes de autorizar el despacho de un traspaso inter-sucursal; si la cantidad solicitada excede el stock disponible, el sistema DEBE bloquear el envío tanto en la interfaz móvil (alerta roja) como en el backend (`EnviarTraspasoUseCase` con HTTP 400), y al despachar conforme, DEBE asentar el movimiento `traspaso_salida` descontando el inventario en custodia de la sede emisora, acreditándose en destino como `traspaso_entrada` únicamente tras la confirmación de recepción física.

### User Story 11 - Gestión Integral de Sucursales por el Administrador (Altas, Bajas/Suspensión y Edición) (Priority: P2)

Como Administrador global del sistema Punto Frío, quiero crear nuevas sucursales, suspender temporalmente o reactivar sucursales existentes y editar sus datos principales (nombre, código único y dirección), para reflejar la apertura, cierre temporal o mantenimiento de locales comerciales directamente desde la aplicación móvil sin necesidad de intervenir manualmente la base de datos.

**Why this priority**: Permite la expansión o reestructuración de la cadena comercial sin depender de desarrolladores ni alterar la base de datos directamente, garantizando además que los locales suspendidos no generen operaciones erróneas.

**Independent Test**: Puede probarse creando una nueva sucursal (ej. "Sucursal Norte", código: "NORTE", dirección: "Av. Radial 10"); verificar su creación inmediata en la base de datos, editar su dirección, suspenderla para comprobar que no aparezca en el selector de turnos del login, y reactivarla para habilitar nuevamente sus operaciones.

**Acceptance Scenarios**:
1. **Given** el administrador en el módulo "Gestión de Sucursales", **When** ingresa nombre, código único y dirección y confirma, **Then** el sistema crea la sucursal y la despliega en la lista activa.
2. **Given** una sucursal existente, **When** el administrador modifica su nombre o dirección, **Then** los cambios se persisten inmediatamente y se reflejan en todos los reportes y comprobantes.
3. **Given** una sucursal activa, **When** el administrador activa la opción de suspender/desactivar, **Then** el sistema cambia su estado a inactiva y la excluye de inmediato del selector de sucursales en el login de turnos y en el destino de traspasos.

---

### User Story 12 - Gestión y Registro Dinámico de Recetas de Transformación (Simples y Compuestas sin Datos Quemados) (Priority: P1)

Como Administrador y Barman del sistema Punto Frío, quiero gestionar recetas de transformación simples (1 insumo origen) o compuestas (2 insumos origen como Chancellor + Blackstone) con tarifa de comisión configurable, y como Barman quiero registrar rellenos eligiendo insumos reales existentes en la base de datos, para que no existan datos estáticos ni discrepancias de stock al cerrar el turno.

**Why this priority**: Es el núcleo operativo de barra para cervezas y destilados. Elimina cualquier dato quemado en el código y asegura la consistencia contable del inventario.

**Independent Test**: En Admin, crear una receta de 2 insumos con comisión; ingresar como Barman, verificar que aparezca con los insumos reales de la base de datos, registrar la transformación y comprobar que el stock de los insumos seleccionados disminuya con exactitud y cuadre el cierre.

**Acceptance Scenarios**:
1. **Given** el Administrador en la pestaña "Transformaciones", **When** presiona el botón `+`, **Then** puede seleccionar si la receta es Simple (1 insumo) o Compuesta (2 insumos), asignar productos destino de la BD, tarifa de comisión y ratio.
2. **Given** el Barman en "Registrar Transformación", **When** selecciona una receta, **Then** solo se listan productos reales dados de alta en el inventario, calculando la comisión en tiempo real según la tarifa configurada.
3. **Given** una transformación registrada con insumos físicos reales, **When** el barman realiza el conteo final de cierre, **Then** el balance descuenta exactamente las unidades transformadas reflejando un cuadre matemático perfecto.

---

### User Story 13 - Entrada Numérica Directa y Atajos por Caja en Conteo y Recepción (Priority: P1)

Como Barman o Garzón responsable de la barra, quiero poder escribir directamente la cantidad de productos por teclado y contar con botones de incremento rápido por caja (`+12`, `+24`, etc.) tanto en los cortes de inventario como en la recepción de mercadería, para no perder tiempo pulsando cientos de veces el botón `+` cuando recibo 10, 20 o 30 cajas de mercadería.

**Why this priority**: Esencial para la experiencia del usuario y rapidez operativa nocturna. Si llegan 30 cajas de Huari (360 botellas), forzar 360 toques en la pantalla retrasa el inicio del turno y genera frustración al personal.

**Independent Test**:
1. En Corte de Inventario (`corte_inventario_screen.dart`), tocar el número entero del selector de botellas, escribir `360` en el teclado y verificar que el contador se actualice a 360 inmediatamente.
2. Usar los botones rápidos `+12` o `+24` y verificar que sumen exactamente una o dos cajas con un solo toque.
3. En Recepción de Mercadería (`ingreso_mercaderia_screen.dart`), escribir directamente `120` botellas para 10 cajas de cerveza y guardar la recepción sin demoras.

**Acceptance Scenarios**:
1. **Given** el selector de botellas en el corte de inventario con valor 0, **When** el usuario pulsa sobre el número de unidades enteras, **Then** se abre un diálogo o teclado numérico directo donde puede digitar cualquier cantidad (ej. 360) o presionar `+12` / `+24`.
2. **Given** el formulario de recepción de mercadería en barra, **When** el barman agrega un ítem, **Then** puede escribir la cantidad exacta en el campo numérico o presionar los atajos por caja sin limitarse a toques unitarios.
3. **Given** el formulario de compras del administrador, **When** se ingresan lotes masivos de producto, **Then** se puede ingresar la cantidad por teclado con validación inmediata.

---

### User Story 14 - Gestión de Motivos de Baja y PDF de Cierre por WhatsApp (Priority: P1)

Como Administrador, quiero crear, editar y eliminar los motivos de bajas o roturas de inventario (ej. "Corona rellenada con defecto", "Pérdida en transporte"), y como Barman quiero que al finalizar mi turno se genere un PDF oficial con el balance de inventario y un botón directo para compartirlo por WhatsApp a los dueños, para mantener la transparencia total de las mermas y el cierre operativo.

**Why this priority**: Evita motivos rígidos o incompletos y garantiza que cada cierre de turno deje constancia documental en el grupo de WhatsApp de la empresa tal como ya ocurre con la apertura.

**Independent Test**:
1. En Admin, crear un nuevo motivo "Botella Picada en Heladera", ingresar como Barman en "Bajas / Roturas" y verificar que el motivo figure en la lista desplegable dinámica.
2. Realizar el Corte de Cierre de Turno, verificar que se genere el PDF del Acta de Cierre con el botón "COMPARTIR EN WHATSAPP" y que se abra la ventana de compartir.

**Acceptance Scenarios**:
1. **Given** el Administrador en la gestión de motivos de baja, **When** registra un nuevo motivo, **Then** se persiste en la BD y queda disponible inmediatamente para todos los locales.
2. **Given** el Barman en `BajasRoturasScreen`, **When** abre el selector de motivos, **Then** se cargan los motivos activos desde la API sin datos quemados.
3. **Given** el Barman al cerrar su turno, **When** confirma el corte final, **Then** el sistema despliega el diálogo de éxito con el PDF vectorial generado y el botón verde para compartir en WhatsApp.

---

### User Story 15 - Detección Inteligente de Discrepancias entre Turnos y Alerta al Celular del Administrador (Priority: P1)

Como Administrador general de Punto Frío, quiero que el sistema detecte automáticamente si entre el cierre de un turno (ej. 20 Coronas al salir el turno Día) y la apertura del siguiente (ej. 19 Coronas al ingresar el turno Noche) existe un faltante o sobrante, y me alerte de inmediato en mi celular con una notificación y un banner rojo en mi pantalla principal, para frenar fugas de mercadería entre cambios de guardia sin esperar a la auditoría del Ticket Z.

**Why this priority**: Resuelve uno de los puntos ciegos más críticos de los bares nocturnos: la pérdida o consumo no autorizado de botellas entre el cambio de turno cuando la barra queda desatendida.

**Independent Test**:
1. Cerrar el turno de la tarde con 20 Coronas en Casa22.
2. Abrir el turno de la noche ingresando 19 Coronas en el conteo inicial.
3. El barman entrante recibe una advertencia visual sobre la diferencia detectada (-1 u.).
4. El Administrador al abrir su app en su celular ve inmediatamente un banner rojo flotante con la alerta: "Discrepancia en Casa22: 1 Corona faltante entre turno saliente y turno entrante", con botón directo para contactar a los barmen por WhatsApp.

**Acceptance Scenarios**:
1. **Given** la apertura de un turno nuevo en una sucursal con turnos previos cerrados, **When** el conteo físico difiere del stock final del turno anterior, **Then** el backend crea un registro en `alertas_discrepancias` marcando el producto, cantidades y responsables de ambos turnos.
2. **Given** el Administrador con la app móvil instalada en su celular, **When** existe una alerta de discrepancia pendiente, **Then** se despliega una tarjeta de alerta roja destacada en `DashboardAdminScreen` con sonido/notificación y acción directa para enviar mensaje de WhatsApp a los involucrados.

---

### User Story 16 - Estabilización Operativa, Rellenos Cero (0) y Conteo Activo Re-imprimible hasta Cierre (Priority: P1)

Como Barman, quiero poder re-imprimir y visualizar el PDF de mi conteo de apertura tantas veces como sea necesario durante mi turno activo (el cual desaparecerá al cerrar el turno para dar paso al historial), y registrar turnos con cero (0) rellenos sin bloqueos ni errores, para garantizar la transparencia operativa en barra.

**Independent Test**:
1. Con turno activo, presionar "Ver / Re-imprimir Conteo de Apertura": debe generarse el PDF.
2. Al cerrar el turno, el botón desaparece y se muestra "Historial de Cortes".

**Acceptance Scenarios**:
1. **Given** un turno abierto, **When** el barman pulsa "Ver Conteo de Apertura", **Then** el sistema despliega el diálogo de impresión y envío a WhatsApp con los datos inmutables del corte inicial.
2. **Given** el corte de cierre completado, **When** se refresca el dashboard, **Then** el botón de conteo activo se oculta.

---

### User Story 17 - Persistencia de Turno por Barman, Auto-adopción y Cierre Seguro (Priority: P1)

Como Barman, quiero que al cerrar y reabrir la app con mi PIN el sistema reconozca inmediatamente mi turno abierto en la sucursal, permitiéndome registrar rellenos y efectuar el corte final sin advertencias de falta de apertura ni errores de liquidación.

**Independent Test**:
1. Abrir turno con PIN de barman, salir de la app, reingresar: el turno activo debe continuar reconocido.
2. Registrar relleno y cerrar turno: finaliza exitosamente con liquidación de comisiones.

**Acceptance Scenarios**:
1. **Given** un barman que reingresa tras cerrar la app, **When** consulta su turno, **Then** el sistema vincula su ID al turno activo de la sucursal.
2. **Given** el cierre de turno, **When** se calcula la comisión acumulada, **Then** el sistema persiste `total_comision_bruta` sin arrojar excepción 500.

---

### User Story 18 - Control Estricto de Stock en Rellenos y Columna de Ingresos en Conteo PDF (Priority: P1)

Como Administrador y Barman, quiero que el sistema valide que existan insumos suficientes en el turno antes de permitir un relleno, muestre el stock disponible en la app y refleje los ingresos acumulados en el acta PDF de apertura, para evitar sobregiros de insumos y descuadres físicos.

**Independent Test**:
1. Con 4 latas en conteo inicial, intentar un relleno que requiere 24 latas: el sistema rechaza la operación con error 400 y mensaje en rojo.
2. Registrar un ingreso de 24 latas: el stock disponible sube a 28 latas y el PDF de conteo muestra la columna `Ingresos (+)`.
3. Registrar el relleno: se aprueba con éxito y descuenta 24 latas.

**Acceptance Scenarios**:
1. **Given** un intento de relleno con insumos insuficientes, **When** se envía la solicitud, **Then** el backend lanza error descriptivo y la app despliega un SnackBar rojo impidiendo el registro.
2. **Given** mercadería recepcionada en el turno, **When** se genera el PDF de conteo, **Then** se detalla la columna `INGRESOS (+)` y el `TOTAL DISPONIBLE`.

---

### User Story 19 - Gestión Centralizada de Proveedores y Selección Rápida en Recepción de Mercadería (Priority: P1)

Como Administrador, quiero gestionar el catálogo de proveedores comerciales (crear, editar, activar/inactivar) con sus datos de contacto, y como Barman, quiero seleccionar el proveedor de una lista desplegable con buscador al registrar un ingreso de mercadería en barra, para mantener la trazabilidad documental de quién entrega cada lote.

**Independent Test**:
1. En el Dashboard Admin, entrar a "GESTIÓN DE PROVEEDORES", crear un nuevo proveedor "Distribuidora San Juan" con teléfono y NIT.
2. Ingresar como Barman a "RECEPCIÓN DE MERCADERÍA": el proveedor "Distribuidora San Juan" debe figurar en la lista desplegable de selección rápida.
3. Registrar una recepción de mercadería seleccionando dicho proveedor: la compra y el movimiento de inventario quedan asociados formalmente a él.

**Acceptance Scenarios**:
1. **Given** el Administrador en `ProveedoresAdminScreen`, **When** registra un nuevo proveedor con nombre comercial y teléfono, **Then** el proveedor queda activo en la base de datos inmediatamente.
2. **Given** el Barman en `IngresoMercaderiaScreen`, **When** toca el selector de proveedor, **Then** se despliega la lista oficial de proveedores activos cargados desde la API con opción de búsqueda.
3. **Given** una compra registrada, **When** se consulta en el historial o reportes, **Then** se identifica con precisión el proveedor responsable del abastecimiento.

---

### User Story 20 - Monitoreo Operativo de Sucursal e Informe PDF en Vivo/Histórico para Administrador (Priority: P1)

Como Administrador general de Punto Frío, quiero acceder a un módulo de monitoreo por sucursales en mi Dashboard móvil, seleccionar cualquier casa (Casa22, Corona, Madan) y generar un informe completo en PDF en tiempo real de lo que está pasando en el turno en curso (o de turnos cerrados pasados), consolidando conteo inicial, ingresos, traspasos, bajas, rellenos y liquidación pagada, para auditar cualquier sede remotamente.

**Independent Test**:
1. En el Dashboard Admin, presionar "MONITOREO Y REPORTES DE SUCURSAL".
2. Seleccionar la sucursal "Casa22": el sistema muestra la tarjeta del turno en curso (si está abierto) o el historial de turnos recientes.
3. Presionar "GENERAR INFORME OFICIAL PDF": el sistema compila y visualiza un PDF integral con: Conteo Inicial, Ingresos de proveedores, Traspasos entrantes/salientes, Bajas justificadas, Rellenos/Comisiones y Estado de liquidación.
4. Presionar "COMPARTIR POR WHATSAPP": envía el PDF directamente a los supervisores.

**Acceptance Scenarios**:
1. **Given** el Administrador en la pantalla de monitoreo, **When** selecciona una sucursal, **Then** el sistema consulta y despliega la información del turno activo (Barman, hora inicio, estado) y el historial de turnos cerrados.
2. **Given** un turno seleccionado (activo o cerrado), **When** se pulsa generar informe PDF, **Then** el sistema descarga el consolidado y lo renderiza con formato ejecutivo corporativo y firmas de auditoría.
3. **Given** un turno cerrado, **When** se audita el informe, **Then** se desglosa el sueldo base asignado, comisiones brutas de relleno y el total efectivamente liquidado al barman.

---

### User Story 21 - Traspasos Inter-Sucursales Reales con Validación y Bloqueo de Stock en Origen (Priority: P1)

Como Barman o Administrador emisor de un traspaso, quiero que la pantalla de envío cargue las sucursales y productos reales de la base de datos, muestre el stock disponible en la barra origen y me bloquee si intento enviar más unidades de las que realmente tengo, y que al despachar se descuente inmediatamente del stock en custodia y se acredite en destino únicamente al ser recibido conforme.

**Independent Test**:
1. En "Despachar Traspaso", verificar que el selector de sucursal destino liste las sucursales reales de la base de datos (excluyendo la sucursal emisora).
2. Verificar que los productos correspondan al catálogo real y que muestren el stock disponible en barra.
3. Intentar traspasar 50 botellas teniendo solo 10: el sistema muestra advertencia roja y bloquea el botón "DESPACHAR TRASPASO".
4. Traspasar 5 botellas: el sistema aprueba la orden, descuenta 5 unidades del stock local (`traspaso_salida`) y deja el lote en estado "En Tránsito" hasta que la sede destino confirme la recepción (`traspaso_entrada`).

**Acceptance Scenarios**:
1. **Given** la pantalla `EnviarTraspasoScreen`, **When** se inicializa, **Then** carga dinámicamente las sucursales activas de `/sucursales` (omitiendo la sucursal propia) y el catálogo de `/productos`.
2. **Given** una cantidad digitada mayor al saldo físico disponible en la barra emisora, **When** el usuario intenta enviar, **Then** la app despliega un mensaje de error en rojo y el botón se desactiva.
3. **Given** un traspaso despachado exitosamente, **When** la sucursal receptora entra a "Recepcionar Traspaso", **Then** puede confirmar las unidades conformes y las mermas en tránsito, acreditando el inventario en destino conforme a la Constitución.

---

### Key Entities *(include if feature involves data)*

- **Sucursal**: Identificador de la casa (Casa22, Casa Coron, Madan, etc.), configuración local y umbrales de tolerancia de merma.
- **Proveedor**: Identificador único, nombre comercial de la empresa o distribuidora (ej. CBN, Embol), persona de contacto, teléfono/WhatsApp, NIT/CI, dirección y estado activo.
- **Informe Operativo de Turno**: Documento auditable consolidado que integra la fotografía completa de la jornada (conteo inicial, ingresos de compras con notas, traspasos entrantes y salientes, bajas y roturas justificadas, transformaciones con comisiones, stock de cierre y liquidación del personal).
- **Usuario / Empleado**: Nombre, rol (Barman, Garzón, Administrador/Auditor), código PIN de 4 dígitos (cifrado), sucursal activa asignada para la semana en curso, historial de rotaciones semanales, esquema de remuneración (Sueldo Semanal Base para Barman Noche / Jornal Diario para Garzón Día) y saldo acumulado de sanciones/deudas por faltantes.
- **Turno (Jornada 12h)**: Identificador único, barman responsable, sucursal, tipo de turno (Día / Noche), fecha/hora de apertura, fecha/hora de cierre, estado (Abierto, Cobrado, Cerrado, Auditado), código único de recibo de cobro y marca temporal de liquidación.
- **Receta de Transformación**: Identificador, insumo origen (materia prima), producto terminado (destino), tarifa de comisión por unidad (Bs), ratio de consumo de referencia, umbral de tolerancia de desviación y estado activo/inactivo.
- **Receta de Combo / Equivalencia**: Identificador, nombre del combo (ej. Balde 6 Coronas), producto terminado base asociado (Corona), unidades individuales equivalentes por combo (6) y estado activo.
- **Corte de Inventario**: Registro de apertura o cierre asociado a un turno, detallando cantidades por producto (enteros y fracciones de 0.25, 0.50, 0.75).
- **Registro de Transformación (Relleno)**: Turno asociado, receta referenciada, producto origen, cantidad consumida, producto destino, cantidad obtenida, unidades rotas, ratio resultante (`origen / destino`), comisión generada.
- **Ingreso de Mercadería**: Turno y sucursal, proveedor/origen, detalle de productos, fotografía adjunta, marca de tiempo y usuario receptor.
- **Traspaso**: Sucursal origen, sucursal destino, productos y cantidades enviadas, cantidades recibidas conformes, unidades clasificadas como merma en tránsito, estado (En Tránsito, Recibido Conforme, Recibido con Discrepancia), fotos o notas de remisión adjuntas, fecha/hora y firmas de emisor y receptor.
- **Cierre de Auditoría (Ticket Z)**: Turno auditado, ventas reportadas en Ticket Z, balance calculado por el sistema, diferencia resultante (cuadrado, sobrante en azul, faltante en rojo), estatus de sanción y observaciones del auditor.
- **Sanción / Deuda de Inventario**: Empleado afectado, turno generador, monto en Bs imputado por faltante, estado (Pendiente, Descontado, Anulado por Auditoría).
- **Informe de Liquidación Semanal**: Período semanal, empleado (Barman Noche), sueldo base semanal, total sanciones deducidas por faltantes de inventario de la semana, sueldo neto exacto a liquidar y estado de pago (Pendiente / Pagado).
- **Configuración de Conexión (App Flutter)**: URL base de la API (Hosting compartido predeterminado / IP y puerto local alternativo), estado de conectividad y cola de sincronización offline.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Los barmen pueden completar un registro de transformación (relleno) o corte de turno en menos de 45 segundos en barra.
- **SC-002**: Reducción al 0.0% del desembolso de comisiones sobre productos no transformados o sobre roturas reportadas.
- **SC-003**: 100% de los ingresos de mercadería externa cuentan con fotografía y marca de tiempo registradas antes de ser aceptados en inventario.
- **SC-004**: Los administradores pueden ejecutar la conciliación de un Ticket Z de 12 horas e identificar discrepancias en menos de 2 minutos por turno.
- **SC-005**: 100% de las discrepancias entre stock físico y ventas de caja quedan imputadas con nombre y apellido del barman responsable del turno correspondiente.
- **SC-006**: Visibilidad del ratio de transformación histórico alcanzable con 1 solo clic en el panel de auditoría, permitiendo detectar desviaciones superiores al 15% respecto a la media de la sucursal.
- **SC-007**: Generación del Informe de Liquidación Semanal consolidado para turnos nocturnos con cálculo automático de deducciones en menos de 3 clics para la administración.
- **SC-008**: Cero interrupciones operativas en barra ante caídas de internet: 100% de los registros offline son sincronizados exitosamente al servidor al restaurar la conectividad sin intervención manual del usuario.
- **SC-009**: Autenticación e ingreso a la app Flutter mediante PIN de 4 dígitos en menos de 3 segundos.
- **SC-010**: Selección del proveedor comercial en la recepción de mercadería en menos de 5 segundos mediante selector con búsqueda sin requerir tipeo manual repetitivo.
- **SC-011**: Generación y visualización del Informe Operativo Integral en PDF de cualquier sucursal y turno en menos de 3 segundos desde el panel móvil del Administrador.
- **SC-012**: 100% de los despachos de traspasos inter-sucursales validados estrictamente contra el stock físico disponible en origen, imposibilitando traspasos con saldos negativos.

## Assumptions

- **Tecnología Móvil Barman (Flutter)**: La aplicación de los barmen está desarrollada en Flutter para dispositivos móviles Android e iOS.
- **Infraestructura de Servidor (Hosting Compartido)**: La API backend está construida como un servicio REST/JSON modular optimizado para operar en hosting compartido convencional (PHP/MySQL en Apache), sin requerir servidores dedicados costosos.
- **Ciclo de Rotación Semanal**: Los barmen rotan de sucursal cada fin de semana, manteniendo su asignación fija de lunes a domingo para efectos de auditoría y liquidación de inventario.
- **Independencia de Caja**: La cajera no tiene cuenta de acceso al software; su interacción se limita a la verificación ocular de la pantalla del dispositivo móvil del barman en turnos de día.
- **Dispositivos Móviles en Barra**: Los barmen disponen de smartphones con cámara funcional y la app Flutter instalada.
- **Tarifas de Comisión**: La tarifa de comisión por unidad y los productos transformables se gestionan dinámicamente mediante el catálogo de recetas, inicializándose con la receta predeterminada "Cerveza en Lata → Botella Corona" a 1 Bs/unidad.
- **Carga Manual de Ticket Z**: En esta versión v1.2, las ventas del Ticket Z son introducidas manualmente por el administrador en el módulo de auditoría de la app Flutter, manteniendo independencia total del software POS.
- **Modalidades de Liquidación**: El sistema diferencia operativamente entre turnos de día (cobro diario en efectivo ante cajera con descuento de deudas) y turnos de noche (liquidación semanal centralizada en administración con reporte de descuentos).
