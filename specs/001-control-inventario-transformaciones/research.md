# Phase 0: Research & Technical Decisions

**Feature**: Sistema de Inteligencia y Control de Inventario (Grupo Punto Frío) v1.2  
**Feature Branch**: `001-control-inventario-transformaciones`  
**Date**: 2026-09-05  

---

## 1. Patrón Arquitectónico del Backend: Arquitectura Hexagonal en Laravel

- **Decision**: Implementar Arquitectura Hexagonal (Puertos y Adaptadores) dentro del ecosistema Laravel dividida en tres capas estrictas:
  1. `Domain/`: Entidades de negocio puras en PHP 8.2+ (POPOs sin herencia de Eloquent ni acoplamiento al framework), Enums de dominio (`TipoMovimiento`, `TipoTurno`, `EstadoTurno`, `EstadoTraspaso`), Value Objects (`FraccionLicor`, `RatioConversion`) y Puertos/Interfaces (`TurnoRepositoryInterface`, `InventarioRepositoryInterface`, `MovimientoRepositoryInterface`, `AuditoriaRepositoryInterface`, `StorageServiceInterface`).
  2. `Application/`: Casos de Uso (Use Cases) autocontenidos y orquestadores (`AbrirTurnoUseCase`, `CerrarTurnoUseCase`, `RegistrarTransformacionUseCase`, `RegistrarBajaUseCase`, `RegistrarIngresoUseCase`, `EnviarTraspasoUseCase`, `RecibirTraspasoUseCase`, `CalcularAuditoriaUseCase`, `GenerarLiquidacionSemanalUseCase`).
  3. `Infrastructure/`: Adaptadores primarios (Controladores HTTP API, FormRequests de validación) y adaptadores secundarios (Modelos Eloquent, Implementaciones de Repositorios con transacciones atómicas `DB::transaction()`, Adaptador de almacenamiento de imágenes en disco local o S3).
- **Rationale**: Cumple rigurosamente con los principios SOLID y la Constitución del proyecto. Aísla las reglas financieras de comisiones y auditoría de la infraestructura de base de datos y framework, facilitando pruebas unitarias aisladas sin necesidad de base de datos activa.
- **Alternatives considered**:
  - *MVC tradicional de Laravel (Controlador → Modelo Eloquent)*: Rechazado porque acopla fuertemente las reglas contables y de auditoría al ORM y al ciclo de vida de Laravel, dificultando la prueba de algoritmos de balance y auditoría.
  - *Clean Architecture con Clean Controllers complejos*: Resulta sobre-complejo para un backend que opera en hosting compartido; la Arquitectura Hexagonal estructurada en 3 capas ofrece el balance óptimo entre desacoplamiento y sencillez de mantenimiento.

---

## 2. Precisión Decimal y Manejo de Fracciones de Licores

- **Decision**: Utilizar `DECIMAL(8,2)` a nivel de esquema de base de datos en MySQL/MariaDB y encapsular las operaciones en el Value Object `FraccionLicor` en el dominio.
- **Rationale**: Los tipos flotantes (`FLOAT` o `DOUBLE`) en computación sufren de imprecisiones de redondeo binario en aritmética de punto flotante (ej. `0.25 + 0.50 + 0.25` puede resultar en `0.99999998`). Con `DECIMAL(8,2)` el motor de base de datos almacena números exactos con representación en base 10.
- **Validaciones**: Los cortes de licores abiertos únicamente admiten múltiplos de 0.25 (`0.00`, `0.25`, `0.50`, `0.75`, `1.00`, etc.), validado tanto en FormRequests de Laravel como en el Value Object de Dominio.
- **Alternatives considered**:
  - *Almacenar todo como enteros (cuartos de botella, ej. 1 botella = 4 cuartos)*: Válido conceptualmente, pero dificulta la legibilidad directa en consultas SQL de reportes administrativos y cruces directos con Ticket Z donde las unidades son decimales estándar.

---

## 3. Atomicidad y Concurrencia en Transformaciones e Inventario

- **Decision**: Todas las operaciones de mutación de stock físico (en particular la Transformación/Relleno, Bajas e Ingresos) DEBEN ejecutarse dentro de un bloque transaccional atómico `DB::transaction()`.
- **Rationale**: Una transformación involucra un movimiento compuesto: salida de materia prima (latas) y alta de producto terminado (botellas Corona), junto con el cálculo y asignación de comisión. Si la base de datos o el script falla a medio camino, la transacción aplica un `Rollback` completo, garantizando que jamás se acredite producto sin su correspondiente consumo de materia prima ni se pague comisión sin registro verificado.
- **Alternatives considered**:
  - *Actualización asíncrona por colas de eventos*: Descartado para el core contable porque introduce retrasos en la visualización inmediata de comisiones para la cajera.

---

## 4. Frontend Flutter: Arquitectura Local-First y Gestor de Estado

- **Decision**:
  1. **Gestor de Estado**: Riverpod (con StateNotifier/AsyncNotifier).
  2. **Persistencia Local**: SQLite (mediante `sqflite`) para estructurar la cola de sincronización transaccional (`SyncQueue`).
  3. **Mecanismo de Sincronización**: Cola de eventos local `sync_queue` con estados `pending`, `syncing`, `synced`, `failed`. Un `SyncCoordinator` en segundo plano monitorea el stream de conectividad (`connectivity_plus`) y procesa las peticiones pendientes con reintentos exponenciales y deduplicación basada en `uuid` idempotente generado por el cliente móvil.
- **Rationale**: Los bares y discotecas son entornos hostiles para la conectividad de red (sótanos, muros densos, congestión de Wi-Fi). La arquitectura Local-First garantiza que el barman complete conteos y rellenos en menos de 2 segundos sin esperar latencia de red. Riverpod ofrece inyección de dependencias declarativa, testabilidad sin widgets y manejo reactivo de estados asíncronos.
- **Alternatives considered**:
  - *BLoC*: Excelente alternativa corporativa, pero requiere significativamente más boilerplate de eventos/estados que Riverpod para pantallas directas tipo "Tap & Go".
  - *Hive*: Muy veloz en pares clave-valor, pero SQLite proporciona consultas relacionales y consistencia ACID más sólida para auditar la cola de movimientos locales pendientes de sincronización.

---

## 5. Estrategia de Conectividad y Compatibilidad con Hosting Compartido

- **Decision**:
  - API Laravel modular REST/JSON empaquetable y ejecutable en hosting compartido estándar (PHP 8.2+, Apache con `mod_rewrite`, MySQL/MariaDB).
  - La app Flutter incluye un `ApiConfigService` con URL de producción preconfigurada (dominio HTTPS en el hosting compartido) y un diálogo oculto de ajustes de red (accesible mediante pulsación larga en la versión de la app) para sobrescribir la IP/puerto de desarrollo o contingencia local (ej. `http://192.168.0.7:81/api`).
- **Rationale**: Resuelve la realidad operativa indicada por el usuario: transición fluida desde entornos desktop con IP fija local a una API pública en hosting compartido sin perder la capacidad de prueba o contingencia local en caso de corte total de internet del local.
- **Alternatives considered**:
  - *Sockets bidireccionales permanentes (WebSockets)*: Poco viable y con restricciones severas en hosting compartido económico. REST stateless con sincronización por polling/cola es la opción más resiliente y compatible.

---

## 6. Desglose Automático de Combos en Auditoría

- **Decision**: Creación de la tabla `recetas_combos` vinculada a productos terminados individuales.
- **Rationale**: El Administrador transcribe el Ticket Z emitido por el punto de venta donde los ítems se facturan como promociones ("Balde de 6 Coronas", "Combo 4 Cervezas"). El caso de uso `CalcularAuditoriaUseCase` descompone antes del cruce de inventario cada combo en sus unidades elementales multiplicando `cantidad_combos_vendidos * unidades_por_combo`, restándolas del consumo físico calculado.

---

## 7. Generación y Exportación de Reportes de Auditoría en PDF

- **Decision**: Renderizado del documento PDF en el frontend mediante las librerías `pdf` y `printing` de Flutter para Android y Web, con opción de almacenamiento en el backend.
- **Rationale**: Los hostings compartidos económicos limitan el tiempo de ejecución y la memoria RAM, impidiendo motores pesados como Puppeteer o Node. Flutter genera documentos PDF vectoriales nítidos en menos de 100ms de forma nativa en el dispositivo, ofreciendo previsualización inmediata, compartición directa (WhatsApp/Email) e impresión sin depender de la conexión al servidor.
- **Alternatives considered**:
  - *Generación server-side con DomPDF en PHP*: Válido para reportes simples, pero consume memoria en Apache y depende de la latencia de subida/bajada de red.

---

## 8. Abastecimiento de Inventario: Compras Multi-Producto

- **Decision**: Estructura relacional Cabecera-Detalle (`compras` y `compras_detalles`) con transacción atómica `DB::transaction()`.
- **Rationale**: Replica el patrón exitoso de `traspasos_detalles`. Permite registrar notas de proveedores con 10 o 20 productos distintos en una sola transacción garantizada, adjuntando la fotografía obligatoria de la nota física como evidencia auditable de la entrada de stock.

---

## 9. Evidencia Fotográfica en Cobro de Comisiones de Relleno

- **Decision**: Interceptor de cámara obligatorio al presionar "Cobro Recibido / Finalizar Turno" con diálogo de vista previa antes de sellar.
- **Rationale**: La entrega de comisiones en barra es un punto crítico de conflicto si el barman o la cajera disputan la entrega del dinero. Exigir la fotografía de los billetes o comprobante y mostrar la vista previa antes de registrar el código de recibo elimina el 100% de la ambigüedad en auditorías posteriores.

---

## 10. Normalización de Proveedores y Catálogo Comercial

- **Decision**: Crear la entidad `proveedores` en base de datos con nombre comercial, contacto, teléfono/WhatsApp, NIT/CI y estado activo, en lugar de cadenas de texto libre en `compras`.
- **Rationale**: Mantiene el catálogo comercial ordenado y auditado por el Administrador. Evita duplicaciones y variaciones tipográficas (ej. "CBN", "C.B.N.", "Cervecería Boliviana"). En la interfaz del barman, un selector con búsqueda rápida permite elegir al proveedor en 1 toque.
- **Alternatives considered**:
  - *Mantener solo texto libre*: Provoca descontrol contable, dificulta saber cuánto se compró a cada proveedor real a lo largo del mes y confunde a los barmen nuevos.

---

## 11. Auditoría Operativa de Sucursal en Vivo y Generación de Informe PDF

- **Decision**: Endpoint consolidado de auditoría `GET /auditoria/sucursal/{id}/informe-turno` que compila en una sola llamada el balance de masa de la jornada (apertura, compras, traspasos, bajas, transformaciones, balances y liquidación) y renderizado de PDF corporativo en el cliente mediante `InformeOperativoTurnoPdfService`.
- **Rationale**: El Administrador necesita supervisar cualquier local remotamente en tiempo real (ej. ver cómo va Casa22 a medianoche). Generar el informe en formato PDF corporativo permite documentar la jornada, enviarlo por WhatsApp a socios o supervisores y respaldar la contabilidad de la empresa.
- **Alternatives considered**:
  - *Múltiples consultas separadas en la app*: Saturaría la conexión con 6 o 7 peticiones HTTP distintas (una para cortes, una para compras, una para bajas, etc.). El endpoint unificado garantiza atomicidad y rapidez.

---

## 12. Consistencia Contable y Control de Stock en Traspasos

- **Decision**: Validar existencias físicas en la sucursal emisora antes de despachar un traspaso. Descontar inmediatamente en origen mediante el movimiento `traspaso_salida` y acreditar en destino mediante `traspaso_entrada` únicamente cuando el receptor confirme la entrega conforme.
- **Rationale**: Cumple el Principio V de la Constitución ("Trazabilidad de Traspasos"): la mercadería despachada sale de la custodia del emisor pero no existe en el destino hasta que sea recibida. Además, validar stock en origen impide que un empleado transfiera mercadería que no tiene físicamente, evitando inventarios negativos.

---

## 13. Modelo de Roles, Autenticación y Suplencia Operativa de Cajeras (US24)

- **Decision**: Incorporar el rol `cajera` como rol nativo en `RolUsuario` asignado a sucursal, con autenticación por PIN ciego de 4 dígitos y redirección a `DashboardCajeraScreen`. Habilitar la figura de suplencia operativa en apertura y cierre con firma inmutable `realizado_por_usuario_id = cajera_id` y `es_suplencia = true`.
- **Rationale**: Permite que el negocio no se detenga si un barman o garzón se ausenta, manteniendo estricta la responsabilidad individual (Principio III de la Constitución) al registrar quién contó físicamente la mercadería en barra. Asimismo, otorga a la cajera autonomía para auditar stock y comisiones de relleno sin pedir prestado el teléfono del barman.
- **Alternatives considered**:
  - *Usar credenciales genéricas de barra o del barman ausente*: Rechazado tajantemente porque diluye la responsabilidad e incumple el Principio Constitucional III.

---

## 14. Resiliencia de Conteo en Barra: Búsqueda Reactiva, Refresco Diferencial y Persistencia Local (US25)

- **Decision**:
  1. Filtrado reactivo en memoria: El buscador filtra una copia de la lista de productos (`_itemsFiltrados`), garantizando que los productos ocultos por el filtro conserven intactas sus cantidades en `_items`.
  2. Refresco diferencial con merge inteligente: Al pulsar refrescar (`Icons.refresh`), se consultan los productos a la API y se incorporan los nuevos con cantidad `0.0`, conservando intactas las cantidades ya introducidas en los ítems existentes (`Map<int, double> _cantidadesDigitadas`).
  3. Persistencia local del borrador: Auto-guardado en `SharedPreferences` con clave compuesta `corte_draft_{sucursalId}_{tipoOperacion}`. El borrador sobrevive a cierres de app o apagado de batería y se purga automáticamente tras la confirmación exitosa del corte en el servidor.
- **Rationale**: El cambio de turno en barra es un proceso de alta presión operativa. Perder un conteo a medio camino por agregar un producto nuevo o por un reinicio del teléfono genera frustración y pérdidas de 15 a 30 minutos por turno.
- **Alternatives considered**:
  - *Guardar borradores continuos en el servidor*: Rechazado por requerir conexión a internet activa permanente y sobrecargar el backend con peticiones concurrentes por cada pulsación en pantalla.

---

## 15. Contabilización Provisional de Productos no Listados y Control Anti-Fraude (US25)

- **Decision**: Permitir al barman o cajera registrar productos no listados con nombre comercial, cantidad y switch de licor fraccionable, asentándolos en el turno como `es_provisional = true` y disparando simultáneamente una Alerta de Auditoría en `alertas_discrepancias` para resolución por el Administrador ("Aprobar como Oficial" o "Unificar con Existente").
- **Rationale**: Resuelve la contingencia de botellas imprevistas en barra sin paralizar la apertura de turno, pero evita que el catálogo maestro se contamine con productos falsos o duplicados, manteniendo el principio de auditoría centralizada (Principio V).
- **Alternatives considered**:
  - *Bloquear la apertura hasta que el Admin cree el producto*: Rechazado por paralizar la operación comercial del local si el Administrador no está disponible al momento del cambio de turno.

---

## 16. Desbloqueo de Reconteo de Inventario Autorizado por Administrador con Memoria de Conteo Previo (US26)

- **Decision**:
  1. **Permiso Efímero Controlado por Admin**: El Administrador habilita temporalmente el reconteo (`POST /turnos/{id}/autorizar-reconteo`) especificando tipo (`apertura` o `cierre`) y motivo. La bandera `permite_reconteo = true` se desactiva de forma inmediata y automática (`permite_reconteo = false`) tan pronto como el empleado aplica la corrección.
  2. **Memoria de Conteo Previo (Zero-Friction UX)**: Al ingresar a la pantalla de corte en modo reconteo, la aplicación consulta `GET /turnos/{id}/corte-inicial` (o corte de cierre) y precarga el 100% de los valores digitados con anterioridad en `_cantidadesDigitadas`. El empleado no tiene que volver a contar decenas de productos; únicamente localiza el ítem donde se equivocó (ej. anota 24 en lugar de 2), ajusta la cantidad y confirma.
  3. **Trazabilidad Inmutable en Base de Datos**: En el backend, la aplicación del reconteo (`POST /turnos/{id}/aplicar-reconteo`) se ejecuta dentro de un bloque transaccional atómico `DB::transaction()`. Compara los valores previos contra los nuevos, inserta un registro en `auditorias_reconteo` con el JSON de diferencias (`detalles_json`), actualiza `cortes_inventario`, recalcula el stock del turno y auto-consume el permiso de reconteo.
  4. **Emisión de Acta Corregida**: Toda reimpresión o generación de PDF posterior incorpora un rótulo distintivo indicando "Versión Corregida con Autorización del Administrador [Nombre]" junto con la fecha y hora de la enmienda.
- **Rationale**: Errores humanos de digitación rápida ocurren con frecuencia en barra. Sin este mecanismo, un turno quedaba bloqueado con datos erróneos de apertura o cierre, corrompiendo la auditoría de ventas y obligando a manipulaciones directas en la base de datos. Exigir autorización del Administrador previene fraudes, mientras que la precarga de memoria evita el agotamiento y resistencia del personal operativo.
- **Alternatives considered**:
  - *Permitir edición libre sin autorización previa*: Rechazado tajantemente porque permitiría a barmen inescrupulosos alterar conteos pasados para encubrir faltantes o hurtos.
  - *Reiniciar el conteo desde cero (pantalla vacía)*: Rechazado por ineficiencia operativa extrema; un barman que contó 80 productos no debe verse forzado a recontar 79 ítems correctos por un solo error.

---

## 17. Microservicio Bot de WhatsApp (Baileys en Node.js) y Webhook Inbound en Laravel

- **Decision**: Implementar un microservicio en Node.js (`scripts/whatsapp_bot/`) basado en la librería `@whiskeysockets/baileys` que se autentica vía código QR como cliente multi-dispositivo de WhatsApp Web. El bot opera en modo 100% silencioso (sin responder en los grupos), monitorea el grupo unificado de la empresa y retransmite cada mensaje entrante (imagen, texto o documento) vía HTTPS POST a `POST /api/v1/webhook/whatsapp` de Laravel utilizando la cabecera `X-Webhook-Secret`.
- **Rationale**: Baileys es una solución autónoma y de costo cero por mensaje, sin depender de intermediarios cloud costosos ni de la API oficial de WhatsApp Business (Meta Cloud API), la cual impone restricciones severas en grupos informales de mensajería interna.
- **Alternatives considered**:
  - *Meta WhatsApp Cloud API oficial*: Rechazado por no permitir escucha pasiva de grupos informales y cobrar por cada sesión/conversación.
  - *Evolution API / Z-API externo*: Rechazado por añadir dependencias de servidores externos cuando Baileys puede correr como servicio systemd o pm2 ligero en el mismo VPS/servidor.

---

## 18. Clasificación Visual 100% Autónoma con Gemini 1.5 Flash Vision

- **Decision**: Emplear Google Gemini 1.5 Flash Vision a través de la API oficial (`gemini-1.5-flash`), enviando la imagen con un prompt multimodal estricto con `response_schema` JSON que clasifica de manera no instructiva entre:
  1. `planilla_caja`: Extrae efectivo en caja, pagos digitales (tarjetas, QR), detalle de gastos operativos y total neto recaudado.
  2. `voucher_deposito`: Extrae entidad bancaria, número de operación/comprobante, monto depositado y fecha/hora.
  3. `recibo_gasto`: Extrae proveedor o concepto, importe cancelado y fecha.
- **Rationale**: Cero fricción operativa para las encargadas. Exigirles escribir comandos o etiquetas específicas ("#planilla", "#voucher") falla en un 40% de los casos debido al cansancio o velocidad del cierre de jornada. Gemini 1.5 Flash tiene latencia baja (< 2s) y un coste por token extremadamente económico, siendo ideal para OCR manuscrito en español boliviano.
- **Alternatives considered**:
  - *Tesseract OCR tradicional*: Rechazado por incapacidad casi total para leer planillas manuscritas en lápiz/bolígrafo o vouchers con iluminación deficiente.
  - *Extracción basada en texto escrito por el usuario*: Rechazado porque las encargadas a menudo solo envían la foto sin texto acompañante.

---

## 19. Algoritmo de Resolución Multi-Factor de Sucursal para Grupo Único

- **Decision**: Debido a que todas las sucursales de la empresa envían sus comprobantes a un **único grupo compartido de WhatsApp**, se implementa un modelo de ponderación multi-señal:
  $$\text{Score} = 0.40 \times \text{OCR\_Nombre\_Casa} + 0.35 \times \text{Match\_Telefono\_Encargada} + 0.25 \times \text{Ventana\_Turno\_POS}$$
  - Si $\text{Score} \ge 0.85$: Se enlaza automáticamente a la sucursal y a su turno correspondiente.
  - Si $\text{Score} < 0.85$: Se registra con bandera `[SUCURSAL_POR_CONFIRMAR]` en la base de datos y se muestra un selector de 1 clic en la web sugiriendo la opción más probable para que la contadora la valide sin fricción.
- **Rationale**: Elimina la confusión que se generaría si dos casas envían sus planillas al mismo tiempo, aprovechando que el número remitente (`sender_phone`) y el encabezado manuscrito de la planilla aportan casi un 90% de certeza combinada.
- **Alternatives considered**:
  - *Obligar a crear 1 grupo de WhatsApp por sucursal*: Rechazado porque los dueños y encargadas prefieren monitorear un solo canal unificado corporativo.

---

## 20. Resiliencia de Conciliación [PENDIENTE_PLANILLA] y Conteo Offline-First de Garzones

- **Decision**:
  1. **Semáforo y Estado de Vértice 2**: Cuando el POS registra ventas y cierre pero la encargada aún no envió la foto de la planilla, Vértice 2 se evalúa como `[PENDIENTE_PLANILLA]` con estado ámbar interactivo, en lugar de asumir $0.00$ Bs y detonar una falsa alerta crítica de robo.
  2. **Conteo en Barra para Garzones (Turno Día)**: En la app móvil Flutter, el conteo opera con memoria reactiva indexada y persistencia instantánea en `SharedPreferences`. Al confirmar el cierre, la app liquida inmediatamente su jornal diario descontando el costo de las botellas faltantes:
     $$\text{Total Efectivo a Cobrar} = \text{Jornal Diario} - \sum (\text{Botellas Faltantes} \times \text{Costo})$$
     Desplegando el comprobante sellado anti-fraude para presentar en caja.
- **Rationale**: Protege la armonía laboral al evitar falsas acusaciones de robo mientras la planilla está en tránsito, y garantiza que los garzones de turno día cobren su jornal exacto y justo sin demoras ni lag en la barra.



