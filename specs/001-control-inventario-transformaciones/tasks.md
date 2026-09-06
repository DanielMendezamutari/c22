# Tasks: Sistema de Inteligencia y Control de Inventario (Grupo Punto Frío) v1.2

**Branch**: `001-control-inventario-transformaciones`  
**Input**: [spec.md](file:///c:/xampp/htdocs/next22/specs/001-control-inventario-transformaciones/spec.md), [plan.md](file:///c:/xampp/htdocs/next22/specs/001-control-inventario-transformaciones/plan.md), [data-model.md](file:///c:/xampp/htdocs/next22/specs/001-control-inventario-transformaciones/data-model.md), [contracts/api-contracts.md](file:///c:/xampp/htdocs/next22/specs/001-control-inventario-transformaciones/contracts/api-contracts.md)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Inicialización del proyecto Laravel y proyecto Flutter con dependencias base.

- [X] T001 Configurar estructura de carpetas de Arquitectura Hexagonal en backend/app (Domain, Application, Infrastructure)
- [X] T002 [P] Inicializar dependencias de Laravel 11 y Sanctum en backend/composer.json
- [X] T003 [P] Inicializar proyecto Flutter con paquetes riverpod, sqflite, connectivity_plus e image_picker en frontend/puntofrio_app/pubspec.yaml
- [X] T004 [P] Configurar variables de entorno y conexión a base de datos MySQL en backend/.env.example

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Esquema de base de datos relacional, modelos Eloquent, entidades de dominio puro y servicios transversales.  
**⚠️ CRITICAL**: Ninguna historia de usuario puede comenzar sin completar esta fase fundacional.

- [X] T005 Crear migración base para tablas sucursales, usuarios, productos y turnos con sucursal_id en backend/database/migrations/2026_09_05_000001_create_base_tables.php
- [X] T006 [P] Crear migración core para movimientos_inventario con DECIMAL(8,2) en backend/database/migrations/2026_09_05_000002_create_movimientos_inventario_table.php
- [X] T007 [P] Crear migración para recetas_transformacion y recetas_combos en backend/database/migrations/2026_09_05_000003_create_recetas_tables.php
- [X] T008 [P] Crear migración para traspasos, auditorias_ventas, sanciones_inventario y liquidaciones_semanales en backend/database/migrations/2026_09_05_000004_create_auditoria_y_traspasos_tables.php
- [X] T009 [P] Crear modelos Eloquent para sucursales, usuarios, productos y turnos en backend/app/Infrastructure/Persistence/Eloquent/Models/
- [X] T010 [P] Crear modelos Eloquent para movimientos, recetas, traspasos, auditorías y sanciones en backend/app/Infrastructure/Persistence/Eloquent/Models/
- [X] T011 [P] Crear Enums de dominio (TipoMovimiento, TipoTurno, EstadoTurno, EstadoTraspaso) en backend/app/Domain/Enums/
- [X] T012 [P] Crear Value Objects FraccionLicor y RatioConversion en backend/app/Domain/ValueObjects/
- [X] T013 [P] Crear interfaces de puertos de repositorios en backend/app/Domain/Ports/
- [X] T014 Implementar repositorios de persistencia con Eloquent en backend/app/Infrastructure/Persistence/Eloquent/Repositories/
- [X] T015 [P] Configurar servicio de almacenamiento de imágenes (Local/S3) en backend/app/Infrastructure/Services/StorageService.php
- [X] T016 Configurar cliente HTTP base con soporte de URL dinámica (hosting o local) en frontend/puntofrio_app/lib/core/network/api_client.dart
- [X] T017 Configurar base de datos SQLite local para cola de sincronización en frontend/puntofrio_app/lib/core/database/sqlite_helper.dart
- [X] T018 Implementar SyncCoordinator para sincronización offline en segundo plano en frontend/puntofrio_app/lib/core/sync/sync_coordinator.dart

**Checkpoint**: Base de datos, puertos de dominio y núcleo offline de Flutter listos.

---

## Phase 3: User Story 1 - Registro de Rellenos y Liquidación Visual a Cajera (Priority: P1) 🎯 MVP

**Goal**: Permitir al barman registrar transformaciones (rellenos), descontar roturas, calcular comisiones netas y desplegar la pantalla de cobro a cajera con segundero dinámico y código temporal de 60 segundos.  
**Independent Test**: Registrar 12 botellas producidas con 1 rotura; verificar cálculo de 11 Bs, mostrar pantalla de cobro con segundero en vivo, presionar "Cobro Recibido" y validar que se genere código único de 60s y congele el turno.

- [X] T019 [P] [US1] Crear entidad de dominio puro Turno y TransaccionRelleno en backend/app/Domain/Entities/Turno.php
- [X] T020 [US1] Implementar RegistrarTransformacionUseCase con DB::transaction() y cálculo de comisión en backend/app/Application/UseCases/Transformacion/RegistrarTransformacionUseCase.php
- [X] T021 [US1] Implementar RegistrarBajaUseCase para reversión de comisiones por rotura en backend/app/Application/UseCases/Transformacion/RegistrarBajaUseCase.php
- [X] T022 [US1] Implementar SolicitarCobroCajeraUseCase con generación de código temporal de 60s en backend/app/Application/UseCases/Turnos/SolicitarCobroCajeraUseCase.php
- [X] T023 [US1] Crear TransformacionController y TurnoController para endpoints de cobro y relleno en backend/app/Infrastructure/Http/Controllers/Api/
- [X] T024 [P] [US1] Registrar rutas de transformaciones y cobro en backend/routes/api.php
- [X] T025 [P] [US1] Crear TransformacionProvider y CobroProvider con Riverpod en frontend/puntofrio_app/lib/presentation/providers/
- [X] T026 [US1] Crear pantalla TransformacionScreen con botones de acción rápida en frontend/puntofrio_app/lib/presentation/screens/transformacion/transformacion_screen.dart
- [X] T027 [US1] Crear pantalla ResumenCajeraScreen a pantalla completa con números grandes, segundero en vivo y temporizador de 60s en frontend/puntofrio_app/lib/presentation/screens/cobro/resumen_cajera_screen.dart

**Checkpoint MVP**: Flujo central de relleno y cobro a cajera 100% funcional e independiente.

---

## Phase 4: User Story 7 - Acceso Rápido con PIN y Rotación Semanal en App Flutter (Priority: P1)

**Goal**: Permitir que el barman inicie sesión en menos de 3 segundos mediante un teclado táctil numérico de 4 dígitos y seleccione su sucursal de rotación semanal.  
**Independent Test**: Abrir la app, seleccionar "Casa Coron", ingresar PIN "1234", validar autenticación contra la API, verificar que la app recuerde la sucursal asignada para la semana y permita modificar la URL del servidor en el menú de ajustes protegidos.

- [X] T028 [P] [US7] Implementar LoginPinUseCase con verificación de hash bcrypt en backend/app/Application/UseCases/Auth/LoginPinUseCase.php
- [X] T029 [US7] Implementar AuthController para endpoint POST /auth/login-pin en backend/app/Infrastructure/Http/Controllers/Api/AuthController.php
- [X] T030 [P] [US7] Crear Widget de teclado numérico táctil NumericPinPad en frontend/puntofrio_app/lib/presentation/widgets/numeric_pin_pad.dart
- [X] T031 [US7] Crear pantalla LoginScreen con selector de sucursal y PIN pad en frontend/puntofrio_app/lib/presentation/screens/auth/login_screen.dart
- [X] T032 [US7] Implementar menú de configuración técnica para personalizar URL/IP de la API en frontend/puntofrio_app/lib/presentation/screens/settings/network_settings_dialog.dart
- [X] T033 [US7] Crear DashboardBarmanScreen con botones gigantes "Tap & Go" en frontend/puntofrio_app/lib/presentation/screens/dashboard/dashboard_barman_screen.dart
- [X] T033a [US7] Implementar enrutamiento por rol en LoginScreen (barman a DashboardBarmanScreen, admin a DashboardAdminScreen) en frontend/puntofrio_app/lib/presentation/screens/auth/login_screen.dart

**Checkpoint**: Autenticación móvil nativa, control de roles y rotación semanal operativas.

---

## Phase 5: User Story 5 - Módulo de Auditoría de Ventas, Desglose de Combos, Faltantes y Sobrantes (Priority: P1)

**Goal**: Permitir al Administrador ingresar ventas del Ticket Z (individuales y combos desglosados), calcular la ecuación de balance, marcar faltantes en rojo con sanción y sobrantes en azul sin penalización, e imprimir la liquidación semanal nocturna.  
**Independent Test**: Ingresar Ticket Z con 5 baldes de 6 y 10 unidades individuales; comprobar desglose a 40 unidades, verificar cálculo de balance físico vs ventas y validar reporte de liquidación semanal.

- [X] T034 [P] [US5] Crear entidad de dominio puro AuditoriaVenta y RecetaCombo en backend/app/Domain/Entities/AuditoriaVenta.php
- [X] T035 [US5] Implementar CalcularAuditoriaUseCase con desglose automático de combos y balance constitucional en backend/app/Application/UseCases/Auditoria/CalcularAuditoriaUseCase.php
- [X] T036 [US5] Implementar GenerarLiquidacionSemanalUseCase para turnos de noche en backend/app/Application/UseCases/Auditoria/GenerarLiquidacionSemanalUseCase.php
- [X] T037 [US5] Implementar AuditoriaController con endpoints de cálculo y liquidación en backend/app/Infrastructure/Http/Controllers/Api/AuditoriaController.php
- [X] T038 [P] [US5] Crear FormRequest para validación de carga de ventas en backend/app/Infrastructure/Http/Requests/CalcularAuditoriaRequest.php
- [X] T039 [US5] Registrar rutas administrativas de auditoría y liquidaciones en backend/routes/api.php
- [X] T039a [US5] Crear pantalla AuditoriaTicketZScreen con carga de combos/unidades y visualización en rojo/azul en frontend/puntofrio_app/lib/presentation/screens/admin/auditoria_ticket_z_screen.dart
- [X] T039b [US5] Crear pantalla LiquidacionSemanalScreen para consolidación semanal de barmen nocturnos en frontend/puntofrio_app/lib/presentation/screens/admin/liquidacion_semanal_screen.dart

**Checkpoint**: Motor de auditoría y pantallas de conciliación administrativa en Flutter completados.

---

## Phase 6: User Story 2 - Recepción de Mercadería con Evidencia Fotográfica (Priority: P2)

**Goal**: Exigir al barman la toma de una fotografía obligatoria al registrar ingresos de mercadería externa para blindar la responsabilidad del inventario inicial.  
**Independent Test**: Intentar registrar un ingreso sin foto (debe bloquear); capturar foto de nota de entrega y verificar guardado en backend y aumento de stock en inventario.

- [X] T040 [P] [US2] Implementar RegistrarIngresoUseCase con validación de archivo de imagen en backend/app/Application/UseCases/Inventario/RegistrarIngresoUseCase.php
- [X] T041 [US2] Implementar IngresoController para recepción multipart/form-data en backend/app/Infrastructure/Http/Controllers/Api/IngresoController.php
- [X] T042 [P] [US2] Crear IngresoMercaderiaRequest con validación obligatoria de imagen en backend/app/Infrastructure/Http/Requests/IngresoMercaderiaRequest.php
- [X] T043 [US2] Integrar plugin de cámara en pantalla IngresoMercaderiaScreen en frontend/puntofrio_app/lib/presentation/screens/ingreso/ingreso_mercaderia_screen.dart

**Checkpoint**: Recepción con evidencia fotográfica 100% funcional.

---

## Phase 7: User Story 3 - Gestión de Turnos de 12 Horas, Cortes y Fracciones de Licores (Priority: P2)

**Goal**: Permitir el corte inicial y final de inventario con selección visual interactiva de fracciones de botella (0.25, 0.50, 0.75, Llena).  
**Independent Test**: Abrir turno con 3.50 botellas y cerrar con 2.75 usando el widget de botella; validar cálculo de consumo exacto de 0.75 botellas sin errores de redondeo.

- [X] T044 [P] [US3] Implementar AbrirTurnoUseCase con corte inicial de fracciones en backend/app/Application/UseCases/Turnos/AbrirTurnoUseCase.php
- [X] T045 [US3] Implementar CerrarTurnoUseCase con corte final y bloqueo de inmutabilidad en backend/app/Application/UseCases/Turnos/CerrarTurnoUseCase.php
- [X] T046 [P] [US3] Crear FormRequest para validar que las cantidades de licores sean múltiplos de 0.25 en backend/app/Infrastructure/Http/Requests/CorteInventarioRequest.php
- [X] T047 [P] [US3] Crear Widget visual de botella con 4 niveles BottleFractionSelector en frontend/puntofrio_app/lib/presentation/widgets/bottle_fraction_selector.dart
- [X] T048 [US3] Crear pantalla CorteInventarioScreen para apertura y cierre de jornada en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart

**Checkpoint**: Cortes de inventario inmutables con fracciones precisas operativos.

---

## Phase 8: User Story 6 - Análisis de Ratio de Conversión y Detección de Mermas Anómalas (Priority: P2)

**Goal**: Proveer al Administrador la visualización del ratio de conversión empírico (latas vs botellas) por barman y por sucursal, alertando mermas fuera de tolerancia.  
**Independent Test**: Registrar transformaciones con ratios de 1.10 y 1.50; verificar que el backend calcule los promedios y resalte el ratio de 1.50 con alerta de desviación.

- [X] T049 [P] [US6] Implementar ObtenerReporteRatiosUseCase con promedios históricos en backend/app/Application/UseCases/Auditoria/ObtenerReporteRatiosUseCase.php
- [X] T050 [US6] Implementar endpoint GET /api/v1/auditoria/ratios en backend/app/Infrastructure/Http/Controllers/Api/AuditoriaController.php
- [X] T051 [US6] Crear pantalla AlertasMermaScreen para visualización de ratios y mermas anómalas en frontend/puntofrio_app/lib/presentation/screens/admin/alertas_merma_screen.dart
- [X] T051a [US6] Implementar GestionarRecetasUseCase y GestionarCombosUseCase para CRUD dinámico en backend/app/Application/UseCases/Recetas/GestionarRecetasUseCase.php
- [X] T051b [US6] Implementar RecetaController para endpoints CRUD /recetas/transformacion y /recetas/combos en backend/app/Infrastructure/Http/Controllers/Api/RecetaController.php
- [X] T051c [US6] Crear pantalla RecetasScreen para administración de tarifas de comisión y combos en frontend/puntofrio_app/lib/presentation/screens/admin/recetas_screen.dart

**Checkpoint**: Descubrimiento empírico, alertas de merma y gestión dinámica de recetas completadas.

---

## Phase 9: User Story 4 - Traspasos Inter-Sucursales con Custodia en Tránsito y Discrepancias (Priority: P3)

**Goal**: Gestionar el envío y recepción de mercadería entre sucursales con estado intermedio "En Tránsito" y registro de mermas en traslado.  
**Independent Test**: Enviar 24 botellas de Casa22 a Casa Coron; recibir 22 conformes y 2 dañadas; verificar acreditación de 22 en destino y 2 como "Merma en Tránsito".

- [X] T052 [P] [US4] Implementar EnviarTraspasoUseCase con cambio a estado en_transito en backend/app/Application/UseCases/Inventario/EnviarTraspasoUseCase.php
- [X] T053 [US4] Implementar RecibirTraspasoUseCase con acreditación parcial y merma en tránsito en backend/app/Application/UseCases/Inventario/RecibirTraspasoUseCase.php
- [X] T054 [US4] Implementar TraspasoController para endpoints /traspasos/enviar y /traspasos/{id}/recibir en backend/app/Infrastructure/Http/Controllers/Api/TraspasoController.php
- [X] T055 [US4] Crear pantallas EnviarTraspasoScreen y RecibirTraspasoScreen en frontend/puntofrio_app/lib/presentation/screens/traspasos/

**Checkpoint**: Trazabilidad de traspasos inter-sucursales cerrada.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Pruebas de integración, verificación de la guía quickstart.md y aseguramiento de rendimiento.

- [X] T056 [P] Ejecutar seeders de prueba con datos reales de Casa22, Casa Coron y Madan en backend/database/seeders/DatabaseSeeder.php
- [X] T057 [P] Crear pruebas de integración para el motor de auditoría en backend/tests/Feature/AuditoriaTest.php
- [X] T058 [P] Crear pruebas unitarias del Value Object FraccionLicor en backend/tests/Unit/FraccionLicorTest.php
- [X] T059 Ejecutar validación end-to-end de los 6 escenarios descritos en specs/001-control-inventario-transformaciones/quickstart.md
- [X] T060 Optimizar índices de base de datos para consultas multi-tenant por sucursal_id y turno_id en backend/database/migrations/2026_09_05_000005_add_indexes.php

---

## Phase 11: User Story 8 - Gestión del Catálogo Maestro de Productos por el Administrador (Priority: P2)

**Goal**: Permitir al Administrador dar de alta nuevos productos (insumos, terminados, licores con cuartos), editar datos de productos existentes y cambiar su estado activo/inactivo directamente desde la app Flutter y mediante la API de Laravel, de modo que cuando llegue un producto 21, se sume al catálogo oficial sin tocar la base de datos.  
**Independent Test**: Crear un nuevo producto desde la app del Administrador (ej. "Cerveza Paceña Lata 355ml", tipo: insumo, unidad: unidad); verificar que se guarde vía `POST /api/v1/productos` y que aparezca disponible de inmediato en el selector de productos de apertura/cierre de turno y en el catálogo de recetas de transformación.

- [X] T061 [P] [US8] Crear ProductoController y FormRequests (CrearProductoRequest, ActualizarProductoRequest) en backend/app/Infrastructure/Http/Controllers/Api/ProductoController.php
- [X] T062 [US8] Registrar rutas RESTful para productos (GET /productos, POST /productos, PUT /productos/{id}) en backend/routes/api.php
- [X] T063 [P] [US8] Crear ProductosAdminProvider con Riverpod para gestión de estado del catálogo en frontend/puntofrio_app/lib/presentation/providers/productos_admin_provider.dart
- [X] T064 [US8] Crear pantalla CatalogoProductosScreen con lista, filtros por tipo y formulario modal de nuevo producto en frontend/puntofrio_app/lib/presentation/screens/admin/catalogo_productos_screen.dart
- [X] T065 [US8] Integrar acceso a CatalogoProductosScreen en el panel principal DashboardAdminScreen en frontend/puntofrio_app/lib/presentation/screens/admin/dashboard_admin_screen.dart

---

## Phase 12: Refinamiento Operativo - Login Ciego, Gestión de Usuarios, Bajas Directas, Traspasos Multi-Producto y Liquidación Semanal (US4, US5, US7, US9, US10)

**Goal**: Implementar el login ciego por PIN implícito (Opción B) con alcance global para Administrador, módulo administrativo de usuarios y cambio de PIN, pantalla directa de bajas y roturas para barmen, traspasos multi-producto y rediseño intuitivo de la liquidación semanal de sueldos (sueldo base menos faltantes).  
**Independent Test**:
1. Login: Ingresar PIN 9999 sin seleccionar usuario y acceder a DashboardAdminScreen; ingresar PIN 1234 con sucursal y acceder a DashboardBarmanScreen sin listas públicas de usuarios.
2. Usuarios: Crear nuevo barman con PIN 5555 desde la app admin y cambiar PIN a usuario existente.
3. Bajas: Pulsar "BAJAS / ROTURAS" en dashboard barman, declarar 2 botellas rotas y verificar descuento de stock.
4. Traspasos: Enviar y recibir un lote con 2 productos distintos (Corona y Paceña) en una sola orden.
5. Liquidación Semanal: Visualizar barmen nocturnos con sueldo base semanal, descontar faltantes de inventario y registrar pago sin requerir códigos ISO.

- [X] T066 [P] [US7] Modificar LoginPinUseCase y AuthController en backend para autenticación implícita por PIN (sin requerir usuario_id), con bypass de sucursal para Administrador global en backend/app/Infrastructure/Http/Controllers/Api/AuthController.php
- [X] T067 [US7] Refactorizar LoginScreen en Flutter eliminando selector público de usuarios y dejando únicamente selector de sucursal y teclado numérico ciego en frontend/puntofrio_app/lib/presentation/screens/auth/login_screen.dart
- [X] T068 [P] [US9] Implementar UserController en backend con endpoints RESTful (GET /usuarios, POST /usuarios, PUT /usuarios/{id}, PUT /usuarios/{id}/pin) y registrar rutas en backend/routes/api.php
- [X] T069 [US9] Crear pantalla UsuariosAdminScreen en Flutter para administración de personal y cambio de PIN, e integrarla en frontend/puntofrio_app/lib/presentation/screens/admin/dashboard_admin_screen.dart
- [X] T070 [P] [US10] Implementar endpoint POST /inventario/bajas en backend para registro directo de mermas y roturas en turno en backend/app/Infrastructure/Http/Controllers/Api/TransformacionController.php
- [X] T071 [US10] Crear pantalla BajasRoturasScreen en Flutter y enlazarla a la tarjeta "BAJAS / ROTURAS" en frontend/puntofrio_app/lib/presentation/screens/dashboard/dashboard_barman_screen.dart
- [X] T072 [P] [US4] Actualizar endpoints y casos de uso de traspasos en backend para admitir múltiples productos por envío y recepción en backend/app/Infrastructure/Http/Controllers/Api/TraspasoController.php
- [X] T073 [US4] Actualizar pantallas EnviarTraspasoScreen y RecibirTraspasoScreen en Flutter para seleccionar y validar múltiples líneas de productos en frontend/puntofrio_app/lib/presentation/screens/traspasos/
- [X] T074 [US5] Rediseñar LiquidacionSemanalScreen en Flutter y GenerarLiquidacionSemanalUseCase en backend para liquidación intuitiva de sueldos semanales (Sueldo Base - Faltantes de Inventario = Sueldo Neto) con botones de fecha amigables ("Esta Semana", "Semana Pasada") y acción de registro de pago en frontend/puntofrio_app/lib/presentation/screens/admin/liquidacion_semanal_screen.dart

---

---

## Phase 13: Módulos Avanzados - Compras Multi-Producto, Asistente de Auditoría en 3 Pasos + Reporte PDF, y Respaldo Fotográfico en Cobro (US2, US5, US1)

**Goal**: Implementar la gestión de compras multi-producto con factura fotográfica, el asistente interactivo de auditoría en 3 pasos con exportación a PDF oficial, y la captura fotográfica obligatoria con vista previa en el cobro de comisiones de relleno.  
**Independent Test**:
1. Compras Multi-Producto: Crear una compra con 2 productos distintos (Corona y Paceña Lata) y foto obligatoria; verificar que `POST /api/v1/inventario/compras` actualice el inventario de ambos productos en la sucursal.
2. Auditoría en 3 Pasos y PDF: Seleccionar turno cerrado de la lista de pendientes, digitar ventas de Ticket Z, visualizar semáforo comparativo y generar/descargar el archivo PDF oficial con membrete y firmas.
3. Respaldo en Cobro: En la pantalla "Resumen a Cajera", presionar "Cobro Recibido / Finalizar Turno", verificar que se active la cámara para fotografiar el efectivo o comprobante, validar la vista previa y confirmar el sellado del turno.

- [X] T075 [P] [US2] Crear migración create_compras_tables.php con tablas compras y compras_detalles en backend/database/migrations/2026_09_05_000007_create_compras_tables.php
- [X] T076 [P] [US2] Implementar CompraController y caso de uso de compras multi-producto con guardado de foto de factura y actualización atómica de inventario en backend/app/Infrastructure/Http/Controllers/Api/CompraController.php y registrar ruta POST /inventario/compras en backend/routes/api.php
- [X] T077 [US2] Crear pantalla RegistrarCompraScreen en Flutter con formulario dinámico de múltiples productos (+ Añadir Producto), captura de foto de factura obligatoria con cámara y selector de sucursal en frontend/puntofrio_app/lib/presentation/screens/inventario/registrar_compra_screen.dart y agregar acceso en frontend/puntofrio_app/lib/presentation/screens/admin/dashboard_admin_screen.dart
- [X] T078 [P] [US5] Implementar endpoint GET /auditoria/turnos-pendientes en AuditoriaController para consultar turnos cerrados pendientes de conciliar con el Ticket Z en backend/app/Infrastructure/Http/Controllers/Api/AuditoriaController.php
- [X] T079 [US5] Rediseñar la pantalla de auditoría en Flutter mediante un Asistente/Stepper en 3 pasos (1: Selector visual de turno pendiente, 2: Carga de ventas del Ticket Z desglosando combos, 3: Semáforo de faltantes/sobrantes) en frontend/puntofrio_app/lib/presentation/screens/admin/auditoria_ticket_z_screen.dart
- [X] T080 [US5] Implementar generador y visor de Reporte de Auditoría en PDF vectorial con membrete oficial del Grupo Punto Frío, detalle de diferencias y espacio para firma usando paquetes pdf y printing en frontend/puntofrio_app/lib/presentation/screens/admin/auditoria_pdf_service.dart
- [X] T081 [P] [US1] Añadir columna foto_comprobante_cobro a tabla turnos mediante migración y actualizar TurnoController para recibir y almacenar la evidencia fotográfica en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T082 [US1] Modificar pantalla ResumenCajeraScreen en Flutter para que al presionar "Cobro Recibido / Finalizar Turno" se active obligatoriamente la cámara para fotografiar el efectivo o comprobante de transferencia con vista previa antes de sellar el turno en frontend/puntofrio_app/lib/presentation/screens/cobro/resumen_cajera_screen.dart
- [X] T083 Ejecutar validación end-to-end de los escenarios 7, 8 y 9 en backend Laravel y emulador Android documentando resultados en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 14: Corrección de Corte de Apertura, PDF para WhatsApp, Recepción Multi-Producto en Barra y Recetas Dinámicas / Compuestas (US3, US2, US6, US1)

**Goal**: Resolver definitivamente el error 400 en el Corte de Apertura, generar y compartir el PDF del conteo inicial en WhatsApp, actualizar la recepción del barman a formato multi-producto y habilitar recetas con insumo intercambiable (Moema / Paceña / Orureña) y mezclas compuestas (Blackstone + Chancellor → Johnnie Walker Red Label).  
**Independent Test**:
1. Apertura de Turno y PDF: Realizar corte de apertura con unidades y fracciones; verificar que `POST /api/v1/turnos/abrir` responda `201 Created` sin error de ENUM, que no aparezca `(null)` en la lista de productos y que se genere el PDF de inventario inicial listo para compartir en WhatsApp.
2. Recepción Multi-Producto en Barra: Desde la tarjeta "NUEVO INGRESO" del barman, registrar una nota de entrega de "Licorería Punto Frío" con 2 productos distintos y foto obligatoria; verificar incremento de stock atómico.
3. Insumo Intercambiable y Mezcla Compuesta: Alternar la receta de Corona a Paceña en `RecetasScreen`, registrar relleno con la cerveza seleccionada y registrar una mezcla de destilados (Blackstone + Chancellor) descontando las fracciones de ambas botellas e incrementando Johnnie Walker.

- [X] T084 [P] [US3] Normalizar tipo_corte en backend/app/Infrastructure/Persistence/Eloquent/Repositories/EloquentTurnoRepository.php ('apertura' -> 'inicial', 'cierre' -> 'final') y crear migración backend/database/migrations/2026_09_06_000009_expand_tipo_corte_enum.php para ampliar ENUM de cortes_inventario a ('inicial', 'final', 'apertura', 'cierre')
- [X] T085 [P] [US3] Actualizar TurnoController::productosParaCorte en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php para enviar tipo_producto y corregir en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart el mapeo de producto y la extracción del mensaje de error real de la API
- [X] T086 [US3] Implementar en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart la generación de PDF vectorial "Acta de Conteo Físico Inicial" con membrete oficial y acción para compartir directamente en WhatsApp vía Printing.sharePdf
- [X] T087 [P] [US2] Rediseñar la pantalla de recepción del barman en frontend/puntofrio_app/lib/presentation/screens/ingreso/ingreso_mercaderia_screen.dart con selector de múltiples productos, foto obligatoria y guardado atómico en POST /api/v1/inventario/compras
- [X] T088 [P] [US6] Actualizar RegistrarRellenoUseCase y TransformacionController en backend/app/Infrastructure/Http/Controllers/Api/TransformacionController.php para admitir insumos alternativos y fórmulas compuestas (insumos_origen: [{insumo_id, cantidad}]) descontando stock de N botellas
- [X] T089 [US6] Agregar en frontend/puntofrio_app/lib/presentation/screens/admin/recetas_screen.dart el cambio rápido de insumo base (Moema / Paceña / Orureña) y en frontend/puntofrio_app/lib/presentation/screens/transformacion/transformacion_screen.dart la selección de cerveza física y el modo de destilados compuestos (Blackstone + Chancellor)
- [X] T090 Ejecutar validación end-to-end en backend Laravel y emulador Android documentando resultados de corte, PDF WhatsApp, recepción multi-producto e insumos dinámicos en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 15: User Story 11 - Gestión Integral de Sucursales por el Administrador (Priority: P2)

**Goal**: Permitir al Administrador dar de alta nuevas sucursales (nombre, código único, dirección), editar datos existentes y suspenderlas/reactivarlas con un toggle de estado (`activo`), garantizando que las sucursales suspendidas queden excluidas de inmediato del login de turnos, traspasos y recepciones.  
**Independent Test**: Crear una nueva sucursal "Sucursal Norte" (`NORTE`), editar su dirección, suspenderla (verificar que no figure en `GET /api/v1/auth/sucursales`) y reactivarla.

- [X] T091 [P] [US11] Crear SucursalController con FormRequests (CrearSucursalRequest, ActualizarSucursalRequest) en backend/app/Infrastructure/Http/Controllers/Api/SucursalController.php
- [X] T092 [US11] Registrar rutas RESTful para sucursales (GET /sucursales, POST /sucursales, PUT /sucursales/{id}, PATCH /sucursales/{id}/toggle-activo) en backend/routes/api.php
- [X] T093 [P] [US11] Crear SucursalesAdminProvider con Riverpod para gestión de estado de sucursales en frontend/puntofrio_app/lib/presentation/providers/sucursales_admin_provider.dart
- [X] T094 [US11] Crear pantalla SucursalesAdminScreen con listado, badges de estado (Activa / Suspendida), modal de creación/edición y switch de suspensión en frontend/puntofrio_app/lib/presentation/screens/admin/sucursales_admin_screen.dart
- [X] T095 [US11] Integrar tarjeta "GESTIÓN DE SUCURSALES" en DashboardAdminScreen en frontend/puntofrio_app/lib/presentation/screens/admin/dashboard_admin_screen.dart
- [X] T096 [US11] Validar end-to-end la creación, edición y suspensión de sucursales en backend y emulador Android

---

## Phase 16: User Story 12 - Gestión y Registro Dinámico de Transformaciones (Priority: P1)

**Goal**: Eliminar cualquier dato estático ("quemado") en la app, permitiendo al Administrador configurar recetas de transformación arbitrarias (simples de 1 insumo como Cervezas o compuestas de 2 insumos como Tequila/Whisky con sus respectivas tarifas de comisión por unidad), y habilitar al Barman para registrar cualquier transformación seleccionando exclusivamente productos reales de su base de datos, asegurando el cuadre matemático exacto entre conteo inicial, consumo de relleno y conteo final.  
**Independent Test**: Configurar en Admin una receta de Tequila (Jarana + Chancellor ➔ Tequila Botella con 2 Bs de comisión); abrir como Barman y validar que figure en el selector dinámico con insumos reales de la BD; registrar la producción y confirmar que se descuente el inventario real y cuadre el corte a 0.

- [X] T097 [P] [US12] Crear migración para agregar `insumo_secundario_id` nullable a `recetas_transformacion` y actualizar modelo `RecetaTransformacion.php` con relaciones `insumoOrigen`, `insumoSecundario` y `productoDestino` en backend/database/migrations/ y backend/app/Infrastructure/Persistence/Eloquent/Models/RecetaTransformacion.php
- [X] T098 [US12] Actualizar `GestionarRecetasUseCase.php` y `RecetaController.php` para validar, guardar y retornar `insumo_secundario_id` y listar productos reales vinculados en backend/app/Application/UseCases/Recetas/GestionarRecetasUseCase.php y backend/app/Infrastructure/Http/Controllers/Api/RecetaController.php
- [X] T099 [P] [US12] En `recetas_screen.dart` (Admin), agregar `floatingActionButton (+)` e implementar diálogo `_mostrarDialogoNuevaTransformacion()` para crear cualquier receta (Simple o Compuesta de 2 insumos) cargando productos reales desde `GET /productos` y asignando tarifa de comisión y ratio en frontend/puntofrio_app/lib/presentation/screens/admin/recetas_screen.dart
- [X] T100 [P] [US12] Reingeniería integral de `transformacion_screen.dart` (Barman): eliminar listas estáticas/hardcoded (`_cervezasDisponibles`, Blackstone, Chancellor, etc.), cargar dinámicamente las recetas activas desde `GET /recetas/transformacion` y los insumos reales desde `GET /productos?tipo=insumo`, permitiendo elegir la receta y la materia prima física real con cálculo dinámico de comisión en frontend/puntofrio_app/lib/presentation/screens/transformacion/transformacion_screen.dart
- [X] T101 [US12] Verificar y asegurar en `RegistrarTransformacionUseCase.php` y `corte_inventario_screen.dart` que los movimientos de consumo descuenten con precisión los insumos físicos elegidos para garantizar la fórmula de cuadre perfecto en el cierre de turno en backend/app/Application/UseCases/Transformacion/RegistrarTransformacionUseCase.php y frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T102 [US12] Validar end-to-end la creación de recetas y registro de transformación con productos reales de la base de datos sin datos quemados

---

## Phase 17: User Story 13 - Entrada Numérica Directa y Atajos por Caja en Conteo y Recepción (Priority: P1)

**Goal**: Dotar al personal de barra (barmen y garzones) y a la administración de un método de entrada ultrarrápido y amigable para el conteo de botellas y la recepción de pedidos masivos: permitir escribir directamente el número mediante teclado táctil en vez de forzar cientos de pulsaciones en el botón `+`, e incorporar botones de incremento rápido por cajas (`+12`, `+24`, `-12`) para que ingresar 10 o 30 cajas (120 o 360 botellas) tome 2 segundos.  
**Independent Test**:
1. En Corte de Inventario (`corte_inventario_screen.dart`), pulsar sobre el número entero en `BottleFractionSelector`, digitar `360` y verificar que el contador suba directamente a 360 sin tener que tocar el `+` 360 veces.
2. Presionar los chips de atajo rápido `+12` y `+24` en `BottleFractionSelector` y verificar el incremento instantáneo.
3. En Recepción de Mercadería (`ingreso_mercaderia_screen.dart`), editar el campo de cantidad con teclado numérico para poner `120` botellas con atajos por caja.
4. En Registrar Compra Admin (`registrar_compra_screen.dart`), confirmar entrada directa por teclado numérico.

- [X] T103 [P] [US13] En `bottle_fraction_selector.dart`, habilitar toque sobre el número de unidades enteras para editarlo directamente mediante diálogo o entrada numérica táctil y agregar chips de atajo por caja (`+12`, `+24`, `+6`) para agilizar conteos de cientos de botellas en frontend/puntofrio_app/lib/presentation/widgets/bottle_fraction_selector.dart
- [X] T104 [P] [US13] En `ingreso_mercaderia_screen.dart`, reemplazar el texto estático de cantidad por un campo editable directamente con teclado numérico (`TextFormField` con `TextInputType.number`) y chips de atajo rápido por caja (`+12`, `+24`, `+6`) en cada fila de producto en frontend/puntofrio_app/lib/presentation/screens/ingreso/ingreso_mercaderia_screen.dart
- [X] T105 [P] [US13] En `registrar_compra_screen.dart`, asegurar que el campo de cantidad permita escritura directa numérica y botones de multiplicación/cajas (`+12`, `+24`) en frontend/puntofrio_app/lib/presentation/screens/inventario/registrar_compra_screen.dart
- [X] T106 [US13] Validar en emulador Android la agilidad y fluidez de conteo y recepción masiva de mercadería (ej. 30 cajas = 360 botellas) sin retrasos para los garzones

---

## Phase 18: User Story 14 - Gestión Dinámica de Motivos de Baja y PDF de Cierre por WhatsApp (Priority: P1)

**Goal**: Permitir al Administrador gestionar dinámicamente los motivos de mermas y roturas (crear, editar, eliminar) eliminando cualquier opción quemada en el código, y dotar al barman de la generación automática del "Acta Oficial de Cierre de Turno y Balance" en PDF vectorial con botón directo para compartirlo en el grupo de WhatsApp de la empresa.  
**Independent Test**:
1. Crear en el panel de Administrador el motivo "Botella Defectuosa en Fábrica"; abrir la pantalla de Bajas del Barman y verificar que aparezca en el menú desplegable dinámico.
2. Realizar un Corte de Cierre de Turno, verificar que se genere el PDF del Acta de Cierre con el resumen de inventario y que el botón verde "COMPARTIR EN WHATSAPP" despache el documento.

- [X] T107 [P] [US14] Crear migración `create_motivos_bajas_table` con campos (`id`, `descripcion`, `activo`) y modelo `MotivoBaja.php` en backend/database/migrations/ y backend/app/Infrastructure/Persistence/Eloquent/Models/MotivoBaja.php
- [X] T108 [P] [US14] Crear `MotivoBajaController.php` con endpoints RESTful (GET /motivos-baja, POST /motivos-baja, PUT /motivos-baja/{id}, DELETE /motivos-baja/{id}) y registrar rutas en backend/routes/api.php
- [X] T109 [US14] Crear pantalla `MotivosBajaAdminScreen.dart` para administración de motivos por el Admin y enlazarla a `DashboardAdminScreen` en frontend/puntofrio_app/lib/presentation/screens/admin/motivos_baja_admin_screen.dart
- [X] T110 [P] [US14] En `bajas_roturas_screen.dart`, eliminar el array estático de motivos y cargar dinámicamente los motivos activos desde `GET /motivos-baja` en frontend/puntofrio_app/lib/presentation/screens/turnos/bajas_roturas_screen.dart
- [X] T111 [US14] En `corte_inventario_screen.dart`, al finalizar el Corte de Cierre, generar el "Acta Oficial de Cierre de Turno y Balance de Inventario" en PDF vectorial (`CierreTurnoPdfService.dart`) y desplegar diálogo con botón "COMPARTIR EN WHATSAPP" vía `Printing.sharePdf` en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart

---

## Phase 19: User Story 15 - Detección Inteligente de Discrepancias entre Turnos y Alerta al Celular del Administrador (Priority: P1)

**Goal**: Detectar fugas o pérdidas silenciosas de mercadería en el cambio de turno: comparar automáticamente en el servidor el conteo de apertura del turno entrante contra el corte final del turno saliente previo en la misma sucursal, advertir al barman en pantalla y notificar al Administrador en su teléfono celular mediante un banner rojo flotante con sonido, detalle de diferencias y botón para contactar a los responsables por WhatsApp.  
**Independent Test**:
1. Cerrar un turno en Casa22 con 20 Coronas.
2. Abrir el siguiente turno declarando 19 Coronas en el conteo de apertura.
3. El barman entrante visualiza la advertencia de discrepancia (-1 botella).
4. El Administrador al abrir la aplicación en su teléfono móvil recibe la alerta destacada en rojo con el detalle del producto, cantidades declaradas y botón directo de WhatsApp para pedir explicaciones.

- [X] T112 [P] [US15] Crear migración `create_alertas_discrepancias_table` (`sucursal_id`, `turno_saliente_id`, `turno_entrante_id`, `producto_id`, `stock_esperado`, `stock_declarado`, `diferencia`, `resuelto`) y agregar columnas `fcm_token` y `device_id` a la tabla `usuarios` para el control de dispositivo maestro del Administrador en backend/database/migrations/ y backend/app/Infrastructure/Persistence/Eloquent/Models/AlertaDiscrepancia.php
- [X] T113 [US15] En `TurnoController.php` (`abrirTurno`), comparar el `corte_inicial` entrante contra el último `corte_final` cerrado en la misma sucursal; si existe discrepancia, persistir la alerta en `alertas_discrepancias` y retornar los datos del faltante para aviso inmediato en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T114 [P] [US15] Crear `AlertaController.php` con endpoints (`GET /alertas/discrepancias`, `POST /alertas/{id}/resolver`, `POST /dispositivos/registrar-maestro`, `POST /dispositivos/desvincular`) asegurando revocación automática de token al cerrar sesión en celulares ajenos de barmen en backend/routes/api.php y backend/app/Infrastructure/Http/Controllers/Api/AlertaController.php
- [X] T115 [US15] En `corte_inventario_screen.dart`, al detectar discrepancia en la apertura, desplegar advertencia modal obligatoria al barman con botón verde destacado **`"📱 NOTIFICAR A DANIEL POR WHATSAPP"`** que abra `https://wa.me/59167369293` con el mensaje pre-llenado (sucursal, turno saliente, turno entrante y botellas faltantes) en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T116 [US15] En `dashboard_admin_screen.dart`, agregar switch **"📱 Este es mi celular personal (Recibir Alertas)"** para enlazar únicamente el teléfono de Daniel, y desplegar el banner flotante rojo neón 🚨 con sonido, contador de alertas y botón de WhatsApp directo en frontend/puntofrio_app/lib/presentation/screens/admin/dashboard_admin_screen.dart
- [X] T117 [US15] Validar end-to-end la detección de fuga inter-turnos, la desvinculación al salir de teléfonos de barmen, el despacho directo a WhatsApp `67369293` y la alerta en el teléfono del Administrador

---

## Phase 20: User Story 16 - Estabilización Operativa, Rellenos Cero (0) y Conteo Activo Re-imprimible hasta Cierre (Priority: P1)

**Goal**: Corregir de raíz los 3 fallos operativos detectados (pantalla gris en Rellenos, error 422 en Recepción y error 422 en Bajas), soportar formalmente turnos y jornadas con cero (0) unidades de relleno sin bloqueos ni excepciones, y habilitar un botón dinámico para que el barman visualice y re-imprima su conteo físico de apertura en PDF tantas veces como requiera durante el turno activo, el cual desaparece al efectuar el corte de cierre para pasar a modo historial.

**Independent Test**:
1. Abrir la pantalla de Rellenos/Transformaciones: no debe mostrar pantalla gris, debe cargar las recetas y permitir registrar con 0 unidades producidas o insumos sin arrojar excepción.
2. Ingresar a Recepción de Mercadería, agregar productos y confirmar: debe guardar exitosamente sin error 422.
3. Declarar una baja/rotura en barra: debe asentarse correctamente vinculada al turno activo de la sucursal sin error 422.
4. Con un turno abierto, ingresar al Dashboard: debe verse el botón "📄 VER / RE-IMPRIMIR CONTEO DE APERTURA", que permite ver y compartir el PDF en WhatsApp las veces que se desee.
5. Al ejecutar el Corte de Cierre de turno, el botón del conteo activo desaparece y queda disponible el "📜 HISTORIAL DE CORTES".

- [X] T118 [US1] Corregir tipado en `transformacion_screen.dart` (líneas 90 y 421) usando `double.tryParse(val.toString()) ?? 1.0` en lugar de `.toDouble()` directo para evitar la pantalla gris de excepción en frontend/puntofrio_app/lib/presentation/screens/transformacion/transformacion_screen.dart
- [X] T119 [US1] Permitir explícitamente 0 unidades producidas / insumos en `transformacion_screen.dart`, flexibilizando las validaciones locales para jornadas sin transformaciones con 0 Bs de comisión en frontend/puntofrio_app/lib/presentation/screens/transformacion/transformacion_screen.dart
- [X] T120 [US1] En backend `RegistrarTransformacionUseCase.php` y `TransformacionController.php`, admitir cantidad 0 en transformaciones (`min:0`), permitiendo liquidar turnos con 0 rellenos en backend/app/Application/UseCases/Transformacion/RegistrarTransformacionUseCase.php y backend/app/Infrastructure/Http/Controllers/Api/TransformacionController.php
- [X] T121 [US2] Sincronizar contrato de recepción en `ingreso_mercaderia_screen.dart`, enviando las claves `foto_comprobante` y `numero_nota_factura` para eliminar el error 422 en frontend/puntofrio_app/lib/presentation/screens/ingreso/ingreso_mercaderia_screen.dart
- [X] T122 [P] [US2] En backend `CompraController.php` (`store`), aceptar indistintamente `foto_comprobante` o `foto_factura`, y `numero_nota_factura` o `numero_factura_nota` en backend/app/Infrastructure/Http/Controllers/Api/CompraController.php
- [X] T123 [US10] Auto-resolver el turno activo abierto en `TransformacionController::registrarBaja` si `turno_id` llega nulo, evitando rechazo con error 422 en backend/app/Infrastructure/Http/Controllers/Api/TransformacionController.php
- [X] T124 [US10] En `bajas_roturas_screen.dart`, asegurar envío seguro de `sucursal_id` y `turno_id`, pre-validando existencia de turno o informando amigablemente en frontend/puntofrio_app/lib/presentation/screens/turnos/bajas_roturas_screen.dart
- [X] T125 [US3] Crear endpoint backend `GET /turnos/{id}/corte-inicial` en `TurnoController.php` y registrar ruta en `backend/routes/api.php` para consultar los ítems y cantidades físicas del corte de apertura en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T126 [US3] En `dashboard_barman_screen.dart`, incorporar el botón condicional **"📄 VER / RE-IMPRIMIR CONTEO DE APERTURA"** mientras `auth.turnoActivoId != null`, que invoque `GET /turnos/{id}/corte-inicial` y abra el diálogo de `ConteoPdfService` para imprimir y compartir en WhatsApp en frontend/puntofrio_app/lib/presentation/screens/dashboard/dashboard_barman_screen.dart
- [X] T127 [US3] En `dashboard_barman_screen.dart` y en el modal de corte, ocultar el botón de conteo activo cuando el turno se cierre (`turnoActivoId == null`), desplegando en su lugar la opción **"📜 HISTORIAL DE CORTES"** en frontend/puntofrio_app/lib/presentation/screens/dashboard/dashboard_barman_screen.dart
- [X] T128 Compilar nueva versión Release de la APK Android con las correcciones operativas y colocarla en el Escritorio del usuario

---

## Dependencies & Execution Order

### Phase Dependencies
- **Phase 1 (Setup)**: Sin dependencias, inicio inmediato.
- **Phase 2 (Foundational)**: Depende de Phase 1. Bloquea todas las historias de usuario.
- **Phase 3 (US1 - MVP)**: Depende de Phase 2. Puede entregarse de forma autónoma.
- **Phase 4 (US7 - Auth/PIN)**: Depende de Phase 2. Se integra con US1.
- **Phase 5 (US5 - Auditoría)**: Depende de Phase 2 y los modelos de turnos de US1.
- **Phases 6 a 9 (US2, US3, US6, US4)**: Dependen de Phase 2 y pueden desarrollarse en paralelo.
- **Phase 10 (Polish)**: Depende de la finalización de las historias implementadas.
- **Phase 11 (US8 - Catálogo)**: Depende de Phase 2.
- **Phase 12 (Refinamiento)**: Refactorizaciones operativas previas (US4, US5, US7, US9, US10).
- **Phase 13 (Módulos Avanzados)**: Compras multi-producto, auditoría 3 pasos con PDF y foto en cobro.
- **Phase 14 (Operatividad Barra & Fórmulas)**: Corrección de error 400 en corte, PDF de conteo para WhatsApp, recepción multi-producto en barra y recetas dinámicas/compuestas.
- **Phase 15 (US11 - Sucursales)**: Gestión integral de sucursales por el Administrador.
- **Phase 16 (US12 - Recetas Dinámicas)**: Creación de transformaciones simples y compuestas desde la app móvil.
- **Phase 17 (US13 - Entrada Numérica Rápida y Atajos por Caja)**: Entrada directa por teclado táctil y botones rápidos `+12`, `+24` en conteos y recepciones.
- **Phase 18 (US14 - Motivos de Baja Dinámicos & PDF Cierre)**: CRUD de motivos y acta oficial de cierre con envío a WhatsApp.
- **Phase 19 (US15 - Alerta Inteligente de Fuga entre Turnos)**: Comparador automático cierre vs apertura y alerta roja con WhatsApp en móvil de Admin.
- **Phase 20 (US16 - Estabilización Operativa, Rellenos 0 y Conteo Activo Re-imprimible)**: Corrige los 3 fallos críticos, habilita conteo activo en PDF hasta el cierre y soporte de rellenos cero.

---

## Parallel Opportunities

```bash
# Backend Endpoints Independientes (Phase 20):
Task T120: "Soporte de transformaciones cero en RegistrarTransformacionUseCase.php y TransformacionController.php"
Task T122: "Tolerancia de claves foto y factura en CompraController.php"
Task T123: "Auto-resolución de turno activo en TransformacionController.php"
Task T125: "Endpoint GET /turnos/{id}/corte-inicial en TurnoController.php"

# Frontend Pantallas Independientes (Phase 20):
Task T118: "Corrección de tipado toDouble() en transformacion_screen.dart"
Task T119: "Soporte de 0 unidades producidas en transformacion_screen.dart"
Task T121: "Claves contractuales en ingreso_mercaderia_screen.dart"
Task T124: "Validación segura de turno en bajas_roturas_screen.dart"
Task T126: "Botón dinámico de ver/re-imprimir conteo de apertura en dashboard_barman_screen.dart"
Task T127: "Ocultamiento post-cierre y switch a historial en dashboard_barman_screen.dart"
```

---

## Notes
- Cada tarea sigue estrictamente el formato `- [ ] [TaskID] [P?] [Story?] Descripción con ruta de archivo`.
- Los endpoints y esquemas respetan con exactitud `data-model.md` y `contracts/api-contracts.md`.
- El núcleo MVP queda delimitado en las Fases 1, 2 y 3 (User Story 1).


