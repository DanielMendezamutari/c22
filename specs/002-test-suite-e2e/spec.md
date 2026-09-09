# Feature Specification: Suite Automatizada de Pruebas de Integración End-to-End para Backend y Frontend

**Feature Branch**: `002-test-suite-e2e`

**Created**: 2026-09-08

**Status**: Draft

**Input**: User description: "Crear suite automatizada de pruebas de integración End-to-End para Backend y Frontend"

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Suite E2E de API Backend (Laravel / PHPUnit) con Base de Datos Aislada (Priority: P1)

Como Desarrollador y Auditor del Sistema Punto Frío, quiero un comando automatizado de pruebas integradas de backend que configure un entorno de datos aislado (SQLite en memoria o base de pruebas), ejecute las migraciones y seeders requeridos, y verifique de forma encadenada todos los endpoints del ciclo operativo de barra (autenticación por PIN, apertura de turno, registro de transformaciones, recepción de compras, despacho/recepción de traspasos, cierre de turno y conciliación de Ticket Z), para tener la certeza matemática de que las reglas constitucionales de balance y comisiones se cumplen y no existen regresiones.

**Why this priority**: Garantiza la integridad financiera, inmutabilidad de turnos y consistencia de los datos del inventario antes de cualquier despliegue al servidor de producción en cPanel.

**Independent Test**:
- Ejecutar `php artisan test --testsuite=Feature` desde la carpeta `backend`.
- El sistema levanta el entorno de test sin tocar la base de datos de producción, simula las peticiones HTTP a la API y verifica que el 100% de los endpoints respondan con códigos de estado conformes (200/201), estructuras JSON esperadas y cálculos exactos de comisiones y stock.

**Acceptance Scenarios**:
1. **Given** un entorno de pruebas configurado en `phpunit.xml` con conexión SQLite en memoria, **When** se ejecuta la suite de Feature tests, **Then** se crean todas las tablas y seeders iniciales sin errores.
2. **Given** un turno de apertura mediante `POST /api/v1/turnos/abrir` con cantidades físicas, **When** se consulta `GET /api/v1/turnos/{id}/corte-inicial`, **Then** la respuesta retorna fielmente en `cantidad_inicial` las cifras ingresadas y no valores en 0.00.
3. **Given** un turno abierto, **When** se registran consecutivamente una recepción de compra, una transformación con merma y un traspaso de salida, **Then** el endpoint de balance y cierre calcula con precisión milimétrica el disponible remanente respetando la ecuación de balance constitucional.

---

### User Story 2 - Suite de Pruebas de Integración Móvil E2E (Flutter `integration_test`) (Priority: P1)

Como Desarrollador y Supervisor de Operaciones, quiero una suite automatizada de pruebas de integración en Flutter (`integration_test`) que simule interacciones reales de usuario en la pantalla del dispositivo (pulsar botones del teclado PIN, ajustar selectores de botellas en fracciones de cuarto, presionar botones de apertura y cierre, y abrir diálogos de PDF y WhatsApp), para certificar que las pantallas y la lógica de estado de Riverpod operan fluidamente sin bloqueos ni errores de renderizado.

**Why this priority**: Evita que los barmen o administradores experimenten cuelgues de interfaz, pantallas congeladas o errores de ciclo de vida (como fallas de contexto asíncrono o ceros por defecto) en plena atención de clientes en la barra.

**Independent Test**:
- Ejecutar `flutter test integration_test/app_flow_test.dart` en el proyecto Flutter.
- El ejecutor arranca la app en emulador o dispositivo, ingresa automáticamente el PIN `1234`, verifica la entrada al Dashboard, navega a la pantalla de Corte de Apertura, ingresa cantidades, confirma e inspecciona que el diálogo de confirmación despliegue el botón de PDF con datos válidos.

**Acceptance Scenarios**:
1. **Given** la pantalla de login con teclado ciego de PIN, **When** el test automatizado teclea el PIN de un Barman, **Then** la app transiciona directamente a `DashboardBarmanScreen` en menos de 2 segundos.
2. **Given** la pantalla de `CorteInventarioScreen` en modo apertura, **When** el robot interactúa con `BottleFractionSelector` asignando unidades enteras y fracciones, **Then** el estado reactivo actualiza la tarjeta visualmente y habilita el botón de confirmación.
3. **Given** la confirmación del turno, **When** se levanta el diálogo de éxito, **Then** el test comprueba la existencia de las opciones "COMPARTIR EN WHATSAPP" y "VER / IMPRIMIR PDF" y constata que los argumentos pasados no contengan cantidades en cero.

---

### User Story 3 - Runner Unificado y Reporte de Estado E2E Multi-Plataforma (Priority: P2)

Como Administrador Técnico de Punto Frío, quiero un comando de ejecución unificado (`run_all_tests.ps1` o `composer test:e2e`) que orqueste secuencialmente la ejecución de las pruebas de backend (PHPUnit) y las pruebas de frontend (Flutter tests y análisis estático), emitiendo al final un resumen consolidado con semáforo de aprobación (PASS / FAIL) y métricas de cobertura, para disponer de un sello de certificación de calidad en un solo paso.

**Why this priority**: Reduce el tiempo de verificación manual antes de publicar actualizaciones de APK o subir parches al servidor, eliminando olvidos u omisiones en el proceso de QA.

**Independent Test**:
- Ejecutar el script orquestador desde la raíz del repositorio.
- El script ejecuta la suite de backend, luego el análisis de Flutter y los tests de frontend, imprimiendo un reporte tabular con los tiempos de ejecución y resultado global.

**Acceptance Scenarios**:
1. **Given** el repositorio con cambios en código, **When** el operador ejecuta el script unificado de pruebas, **Then** se ejecutan primero los tests de backend y luego los de frontend de manera automatizada.
2. **Given** que todas las pruebas pasen satisfactoriamente, **When** finaliza el script, **Then** devuelve código de salida `0` y muestra en verde "TODO EL SISTEMA CONFORME PARA PRODUCCIÓN".
3. **Given** que algún test falle, **When** el script detecta la falla, **Then** detiene la ejecución, muestra la traza exacta del fallo en color rojo y devuelve código de salida `1`.

---

### Edge Cases

- **Base de Datos Local MySQL no disponible**: La suite de tests del backend DEBE configurarse con SQLite en memoria (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) en `phpunit.xml` para que cualquier desarrollador o auditor pueda correr las pruebas sin requerir un servidor MySQL activo en su máquina.
- **Dispositivo físico o emulador ausente para Flutter**: La suite de frontend debe separar claramente las pruebas unitarias/widgets (que corren sin emulador con `flutter test`) de las pruebas de integración con UI real (`integration_test`), permitiendo validación inmediata incluso en servidores headless sin pantalla.
- **Intermitencia de red y persistencia offline**: Las pruebas de Flutter deben simular la caída de red para certificar que las operaciones se encolen en SQLite local (`sync_queue`) y no arrojen excepciones no capturadas al usuario.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El backend DEBE incluir una configuración de pruebas aislada en `backend/phpunit.xml` que habilite el uso de SQLite en memoria (`:memory:`) con migraciones automáticas mediante el trait `RefreshDatabase` de Laravel.
- **FR-002**: El backend DEBE proveer un caso de prueba integral de ciclo de vida (`backend/tests/Feature/CicloCompletoTurnoTest.php`) que ejecute de forma encadenada:
  1. Login de usuario barman con PIN de 4 dígitos.
  2. Apertura de turno con corte inicial y validación de retorno de cantidades.
  3. Recepción de compras con proveedor y validación de ingresos de stock.
  4. Transformación de latas a botellas Corona con cálculo de comisiones netas descontando roturas.
  5. Despacho y recepción de traspaso inter-sucursales.
  6. Corte de cierre de turno con inmutabilidad y cálculo de balance.
  7. Conciliación y auditoría de Ticket Z con semáforo de faltantes y sobrantes.
- **FR-003**: El proyecto Flutter DEBE incorporar la dependencia `integration_test` provista por el SDK de Flutter en `frontend/puntofrio_app/pubspec.yaml` bajo `dev_dependencies`.
- **FR-004**: El proyecto Flutter DEBE proveer un archivo de prueba de integración (`frontend/puntofrio_app/integration_test/app_flujo_completo_test.dart`) que utilice `IntegrationTestWidgetsFlutterBinding.ensureInitialized()` para automatizar la interacción de usuario con el teclado táctil de PIN, selección de botellas en `CorteInventarioScreen` y comprobación de datos en diálogos de PDF.
- **FR-005**: El sistema DEBE contar con pruebas unitarias y de widgets en `frontend/puntofrio_app/test/` que certifiquen el formateo y precedencia numérica de `conteo_pdf_service.dart` y `cierre_turno_pdf_service.dart`, validando que nunca se rendericen cantidades `0.00` cuando existen recuentos físicos positivos.
- **FR-006**: El sistema DEBE proveer un script PowerShell multiplataforma (`scripts/run_all_tests.ps1`) en la raíz del proyecto que ejecute secuencialmente:
  1. Pruebas de integración del backend (`php artisan test`).
  2. Análisis estático de código frontend (`flutter analyze`).
  3. Pruebas unitarias y de widgets de frontend (`flutter test`).
- **FR-007**: Toda prueba de integración DEBE validar las restricciones de la Constitución de Grupo Punto Frío: 0% de comisión en productos no transformados, responsabilidad estricta por turno e inmutabilidad de cortes.

---

### Key Entities

- **Suite de Pruebas Backend (PHPUnit TestSuite)**: Configuración XML y clases de prueba de integración HTTP (`tests/Feature/`) y de dominio (`tests/Unit/`).
- **Suite de Pruebas Frontend (Flutter Test Runner)**: Pruebas unitarias de proveedores y widgets (`test/`) y pruebas de integración de dispositivo (`integration_test/`).
- **Orquestador de Pruebas (E2E Runner Script)**: Script ejecutable que automatiza la ejecución encadenada, captura códigos de retorno y genera el informe consolidado.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: La suite de pruebas de integración de backend (`php artisan test`) ejecuta todos los casos en menos de 25 segundos y aprueba con un 100% de éxito.
- **SC-002**: Las pruebas de frontend (`flutter test`) y el análisis estático (`flutter analyze`) se completan sin advertencias críticas ni errores de sintaxis en menos de 45 segundos.
- **SC-003**: 100% de los flujos operativos clave de barra (Apertura con Conteo Real, Relleno con Comisión, Traspasos y Conciliación) quedan blindados contra regresiones de código.
- **SC-004**: Los desarrolladores y administradores pueden certificar la salud total de la plataforma ejecutando un único comando (`.\scripts\run_all_tests.ps1`).

---

## Assumptions

- **Aislamiento de Pruebas**: Las pruebas automatizadas utilizan bases de datos en memoria o esquemas de prueba temporales, garantizando que nunca se modifiquen los datos reales de las sucursales ni de los barmen.
- **Entorno de Ejecución**: El entorno cuenta con PHP 8.2+ y Flutter SDK 3.19+ instalados en el PATH del sistema.
- **Ejecución Local y CI**: Las pruebas están diseñadas para funcionar tanto en la estación de trabajo local en Windows como en entornos de integración continua (CI/CD) basados en Linux.
