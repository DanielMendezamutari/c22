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

## Phase 21: User Story 17 - Persistencia de Turno por Barman, Auto-adopción de Turno y Cierre Seguro (Priority: P1)

**Goal**: Garantizar que el barman mantenga la sesión de su turno activo ininterrumpidamente aunque cierre la aplicación o vuelva a ingresar con su PIN, resolver la asignación real del `barman_id` en la apertura, auto-adoptar turnos huérfanos o con fallback default (1) en la sucursal, y corregir la propiedad de comisión bruta en el cierre de turno.

**Independent Test**:
1. Con un turno abierto en Casa22, cerrar sesión o salir de la aplicación.
2. Ingresar nuevamente con el PIN de Víctor Valverde (PIN 2222): el turno activo #1 debe ser reconocido inmediatamente (`turnoActivoId != null`).
3. En el Dashboard del Barman, la opción "Corte de Cierre" y "Registrar Relleno" deben estar habilitadas y operativas sin advertir que falta aperturar turno.
4. Al cerrar el turno con inventario final, el proceso finaliza sin error 500/400 y el estado local se limpia (`turnoActivoId == null`).

- [X] T129 [US17] En `CorteInventarioRequest.php`, agregar regla de validación `'barman_id' => 'sometimes|integer|exists:usuarios,id'` en backend/app/Infrastructure/Http/Requests/CorteInventarioRequest.php
- [X] T130 [US17] En `TurnoController.php` (`abrirTurno` y `turnoActivo`), resolver `$barmanId` priorizando el `barman_id` enviado por la app y auto-adoptar turnos huérfanos en la sucursal en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T131 [US17] En `EloquentTurnoRepository.php` (`buscarTurnoActivoPorBarman`), implementar fallback de auto-adopción de turnos huérfanos con `barman_id = 1` en la sucursal en backend/app/Infrastructure/Persistence/Eloquent/Repositories/EloquentTurnoRepository.php
- [X] T132 [US17] En `CerrarTurnoUseCase.php`, corregir propiedad `$turno->total_comision` a `$turno->total_comision_bruta` en backend/app/Application/UseCases/Turnos/CerrarTurnoUseCase.php
- [X] T133 [US17] En `corte_inventario_screen.dart`, enviar `'barman_id': auth.usuarioId` en la apertura y resetear `ref.read(authProvider.notifier).actualizarTurnoActivo(null)` al cerrar en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T134 [US17] Ejecutar pruebas unitarias de backend (`php artisan test`) y análisis de frontend (`flutter analyze`)
- [X] T135 Compilar APK Release oficial y preparar despliegue

---

## Phase 22: User Story 18 - Control Estricto de Stock en Rellenos y Columna de Ingresos en Conteo Activo PDF (Priority: P1)

**Goal**: Impedir que un barman registre rellenos sin contar con el insumo físico suficiente en su turno (`Stock Disponible = Apertura + Ingresos - Bajas - Consumos Previos`), mostrar en la app el stock disponible en barra y reflejar en el PDF de conteo activo la nueva columna acumulativa `Ingresos (+)` (`[Producto] | [Apertura] | [Ingresos (+)] | [Total Disponible]`).

**Independent Test**:
1. Con 4 Moemas en el conteo de apertura, intentar registrar un relleno que requiera 24 Moemas: el sistema rechaza la operación con error descriptivo en rojo: *"Stock insuficiente de 'moema lata' (Disponible en turno: 4, Requerido: 24)"*.
2. Registrar un ingreso de mercadería de 24 Moemas: el stock disponible pasa a 28 Moemas.
3. Al previsualizar el PDF de conteo, la tabla muestra: `[moema lata] | [4] | [+24] | [28]`.
4. Registrar el relleno de 24 Moemas: la operación se asienta exitosamente y el stock restante queda en 4 Moemas.

- [X] T136 [US18] En `RegistrarTransformacionUseCase.php`, calcular el stock disponible en turno (`Apertura + Ingresos - Bajas - Consumos Previos`) y lanzar `DomainException` si `cantidad_insumo > stockDisponible` en backend/app/Application/UseCases/Transformacion/RegistrarTransformacionUseCase.php
- [X] T137 [US18] En `TurnoController.php` (`corteInicial`), agregar el cálculo de ingresos acumulados del turno por producto y retornar `cantidad_inicial`, `ingresos` y `total_disponible` en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T138 [US18] En `transformacion_provider.dart`, capturar correctamente las excepciones HTTP 400/422 y propagar el mensaje de error de validación sin marcar la operación como éxito local en frontend/puntofrio_app/lib/presentation/providers/transformacion_provider.dart
- [X] T139 [US18] En `transformacion_screen.dart`, mostrar badge con el stock físico disponible en el turno para el insumo seleccionado y notificar visualmente el error en rojo si se excede en frontend/puntofrio_app/lib/presentation/screens/transformacion/transformacion_screen.dart
- [X] T140 [US18] En `conteo_pdf_service.dart`, actualizar la estructura tabular del acta en PDF agregando las columnas `INICIAL`, `INGRESOS (+)` y `TOTAL DISPONIBLE` en frontend/puntofrio_app/lib/presentation/services/conteo_pdf_service.dart
- [X] T141 [US18] Ejecutar pruebas unitarias de backend (`php artisan test`) y análisis de frontend (`flutter analyze`)
- [X] T142 Compilar APK Release oficial y desplegar backend en cPanel

---

## Phase 23: User Story 19 - Gestión Centralizada de Proveedores y Selección Rápida en Recepción (Priority: P1)

**Goal**: Proveer un catálogo comercial normalizado de proveedores gestionado por el Administrador y permitir al barman seleccionar al proveedor de un desplegable con búsqueda rápida en la recepción de mercadería, eliminando textos libres y errores de tipeo.

**Independent Test**:
1. En el Dashboard Admin, ingresar a "GESTIÓN DE PROVEEDORES" y registrar "Distribuidora San Juan".
2. Como Barman, entrar a "RECEPCIÓN DE MERCADERÍA", abrir el selector de proveedores: debe figurar "Distribuidora San Juan".
3. Guardar la recepción de mercadería: la compra y el kardex quedan formalmente asignados al proveedor seleccionado.

- [X] T143 [P] [US19] Crear migración `create_proveedores_table` (`id`, `nombre`, `contacto_nombre`, `telefono`, `nit_o_ci`, `direccion`, `activo`, `timestamps`) con seed de proveedores iniciales y modelo Eloquent `Proveedor.php` en backend/database/migrations/ y backend/app/Infrastructure/Persistence/Eloquent/Models/Proveedor.php
- [X] T144 [US19] Crear `ProveedorController.php` con endpoints REST (`GET /proveedores`, `POST /proveedores`, `PUT /proveedores/{id}`, `DELETE /proveedores/{id}`) y registrar rutas en backend/routes/api.php y backend/app/Infrastructure/Http/Controllers/Api/ProveedorController.php
- [X] T145 [P] [US19] Crear pantalla `proveedores_admin_screen.dart` para altas, edición y suspensión de proveedores, e integrarla en `dashboard_admin_screen.dart` con la tarjeta 'GESTIÓN DE PROVEEDORES' en frontend/puntofrio_app/lib/presentation/screens/admin/proveedores_admin_screen.dart y frontend/puntofrio_app/lib/presentation/screens/admin/dashboard_admin_screen.dart
- [X] T146 [US19] En `ingreso_mercaderia_screen.dart`, sustituir el campo de texto libre por un selector desplegable con búsqueda dinámica de proveedores activos consumiendo `/proveedores?activo=1` en frontend/puntofrio_app/lib/presentation/screens/ingreso/ingreso_mercaderia_screen.dart

---

## Phase 24: User Story 20 - Monitoreo Operativo de Sucursal e Informe PDF en Vivo/Histórico para Administrador (Priority: P1)

**Goal**: Habilitar en la app móvil del Administrador la supervisión y auditoría en tiempo real de cualquier sucursal (Casa22, Corona, Madan), permitiendo consultar el turno activo en vivo o turnos cerrados pasados y exportar un Informe Operativo Integral en PDF con balance de masa y liquidación.

**Independent Test**:
1. En Dashboard Admin, pulsar "MONITOREO Y REPORTES DE SUCURSAL" y seleccionar "Casa22".
2. Visualizar la tarjeta del turno en curso (barman, hora de apertura, estado abierto).
3. Presionar "GENERAR INFORME OFICIAL PDF": debe renderizarse el PDF consolidado (conteo inicial, compras/proveedores, traspasos, bajas, transformaciones, balances y liquidación) con botón para compartir en WhatsApp.

- [X] T147 [US20] Crear endpoint `GET /auditoria/sucursal/{sucursal_id}/informe-turno` en `AuditoriaController.php` que compile metadatos del turno, corte inicial, compras de proveedores con notas, traspasos netos, bajas justificadas, transformaciones/comisiones y liquidación económica en backend/app/Infrastructure/Http/Controllers/Api/AuditoriaController.php
- [X] T148 [P] [US20] Crear servicio `informe_operativo_turno_pdf_service.dart` para renderizar el documento PDF oficial corporativo de auditoría con tablas de balance, firmas de custodios y función de compartir por WhatsApp en frontend/puntofrio_app/lib/presentation/screens/admin/informe_operativo_turno_pdf_service.dart
- [X] T149 [US20] Crear pantalla `informe_sucursales_screen.dart` para supervisar cualquier sucursal (turno en vivo o historial cerrado) y descargar el informe PDF, enlazándola en `dashboard_admin_screen.dart` con la tarjeta 'MONITOREO Y REPORTES DE SUCURSAL' en frontend/puntofrio_app/lib/presentation/screens/admin/informe_sucursales_screen.dart y frontend/puntofrio_app/lib/presentation/screens/admin/dashboard_admin_screen.dart

---

## Phase 25: User Story 21 - Traspasos Inter-Sucursales Reales con Validación y Bloqueo de Stock en Origen (Priority: P1)

**Goal**: Reemplazar listas estáticas de traspasos por sucursales y productos reales de la base de datos, validar existencias en la sucursal emisora bloqueando el despacho si no hay stock suficiente, y asentar movimientos `traspaso_salida` y `traspaso_entrada`.

**Independent Test**:
1. En "Despachar Traspaso", verificar que se listen las sucursales reales de la base de datos (excluyendo la propia) y productos del catálogo real con su saldo disponible.
2. Ingresar una cantidad superior al stock en barra: la app resalta en rojo y bloquea el botón "DESPACHAR TRASPASO".
3. Despachar una cantidad válida: se aprueba la orden, descuenta el stock en origen (`traspaso_salida`) y al confirmar recepción en destino se acredita (`traspaso_entrada`).

- [X] T150 [US21] En `EnviarTraspasoUseCase.php`, validar stock físico disponible en el turno/sucursal de origen antes de despachar (lanzando `DomainException` HTTP 400 si la cantidad supera el saldo) y registrar movimiento `traspaso_salida` en `movimientos_inventario` en backend/app/Application/UseCases/Inventario/EnviarTraspasoUseCase.php
- [X] T151 [US21] En `RecibirTraspasoUseCase.php`, al confirmar recepción conforme o con merma, registrar movimiento `traspaso_entrada` acreditando formalmente el stock recibido en la sucursal y turno de destino en backend/app/Application/UseCases/Inventario/RecibirTraspasoUseCase.php
- [X] T152 [US21] En `enviar_traspaso_screen.dart`, eliminar sucursales y productos estáticos, cargar dinámicamente `/sucursales` y `/productos`, consultar saldo disponible en barra y bloquear el botón con alerta roja en caso de stock insuficiente en frontend/puntofrio_app/lib/presentation/screens/traspasos/enviar_traspaso_screen.dart

---

## Phase 26: Verificación, Build y Despliegue Oficial (US19, US20, US21)

**Goal**: Validar integralmente los cambios mediante pruebas automatizadas, compilar la APK Release oficial v1.5 y desplegar las migraciones y código en cPanel.

- [X] T153 [US19] [US20] [US21] Ejecutar suite de pruebas unitarias de backend (`php artisan test`) y análisis estático de frontend (`flutter analyze`)
- [X] T154 Compilar APK Release oficial v1.5, copiar a Desktop del usuario (`PuntoFrio_OFICIAL_v1.5.apk`) y desplegar backend en cPanel

---

## Phase 27: User Story 22 - Balance Integral en Conteo de Apertura PDF, Reconciliación Completa en PDF de Cierre y Visualización de Conteo Inicial en Pantalla de Cierre (Priority: P1)

**Goal**: Corregir de raíz la contabilización de ingresos acumulados en `corteInicial` (reemplazando `ingreso_compra` por `ingreso` y `traspaso_entrada`), reestructurar el Acta Oficial de Cierre en PDF eliminando la columna "Tipo" y mostrando la tabla de reconciliación completa (`INICIAL`, `INGRESOS (+)`, `RELLENOS (±)`, `BAJAS (-)`, `TOTAL CIERRE`) con nombres reales de productos, y dotar a la pantalla móvil de Corte de Cierre de indicadores visuales claros sobre la cantidad con la que inició el turno y los ingresos recibidos.

**Independent Test**:
1. Apertura y Recepción: Registrar recepción de mercadería durante el turno activo; pulsar "VER / RE-IMPRIMIR CONTEO DE APERTURA" y verificar que la columna `INGRESOS (+)` muestre `+X.XX` y sume al disponible en lugar de mostrar guiones (`-`).
2. Pantalla de Corte de Cierre: Al abrir la pantalla para entregar el turno, verificar que cada producto muestre debajo de su nombre el pill informativo: `Inició: X.XX | Ingresos: +Y.YY | Disp: Z.ZZ`.
3. Acta de Cierre en PDF: Confirmar el corte final: verificar que el PDF generado muestre los nombres descriptivos de los productos (eliminando `"Producto #1"`), no contenga la columna `"Tipo"` y deslice la conciliación completa requerida por FR-033.

- [X] T155 [US22] En `TurnoController.php`, corregir la consulta de ingresos acumulados en `corteInicial` sustituyendo `'ingreso_compra'` por `['ingreso', 'traspaso_entrada']`, incluir productos recepcionados durante la jornada aunque no estuvieran en la apertura inicial, y calcular balances de transformaciones y bajas en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T156 [P] [US22] En `cierre_turno_pdf_service.dart`, resolver nombres genéricos usando `it['nombre'] ?? it['producto_nombre']`, eliminar la columna "Tipo" y reformar la tabla oficial a `['#', 'PRODUCTO', 'INICIAL', 'INGRESOS (+)', 'RELLENOS (±)', 'BAJAS (-)', 'TOTAL CIERRE']` con resumen de totales al pie en frontend/puntofrio_app/lib/presentation/screens/turnos/cierre_turno_pdf_service.dart
- [X] T157 [P] [US22] En `bottle_fraction_selector.dart`, incorporar soporte para mostrar cintillo de balance (`cantidadInicial`, `ingresos`, `totalDisponible`) cuando `esCierre == true`, junto al cálculo informativo de salida estimada en frontend/puntofrio_app/lib/presentation/widgets/bottle_fraction_selector.dart
- [X] T158 [US22] En `corte_inventario_screen.dart`, al operar en modo `cierre`, consultar `GET /turnos/{id}/corte-inicial` para cargar los productos con su stock de apertura y movimientos del turno activo, suministrándolos a `BottleFractionSelector` y al servicio `CierreTurnoPdfService` en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T159 [US22] Ejecutar verificación end-to-end de recepción con reflejo en PDF de apertura, corte de cierre con referencia visual de inicio y PDF de cierre con reconciliación oficial completa en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 28: User Story 23 - Fiel Reflejo del Conteo Físico Inicial en Acta Oficial de Conteo en PDF (Apertura) y Persistencia Inmutable (Priority: P1)

**Goal**: Corregir de raíz la omisión y enmascaramiento por ceros en el Acta Oficial de Conteo Físico y Balance en Turno (`conteo_pdf_service.dart`), garantizando que la cantidad física real ingresada por el barman en `corte_inventario_screen.dart` se asiente y refleje fielmente en la columna `APERTURA` y `TOTAL DISP.` (eliminando el despliegue erróneo de 0.00), y asegurar que `POST /turnos/abrir` y `GET /turnos/{id}/corte-inicial` persistan y retornen íntegramente las cantidades contadas para auditoría y reimpresiones.

**Independent Test**:
1. Apertura con conteo real: Iniciar turno en `CorteInventarioScreen` ingresando cantidades en el catálogo (ej. 24 Moema, 10 Corona, 2 Fernet 750).
2. Diálogo modal inmediato: Presionar "Confirmar e Inmutabilizar"; en el diálogo "¡Turno de Barra Iniciado!", presionar "VER / IMPRIMIR PDF": verificar que el PDF muestre en `APERTURA` y `TOTAL DISP.` las cantidades exactas digitadas y el pie sume las unidades contadas.
3. Compartir por WhatsApp: Presionar "COMPARTIR EN WHATSAPP": constatar que el PDF adjunto contenga las cifras reales.
4. Reimpresión desde Dashboard: En `DashboardBarmanScreen`, presionar "📄 VER / RE-IMPRIMIR CONTEO DE APERTURA" y constatar que el backend retorne y compile las mismas cifras de apertura.

- [X] T160 [US23] En `conteo_pdf_service.dart`, corregir la precedencia de resolución numérica sustituyendo la trampa de null-coalescing (`item['cantidad_inicial'] ?? item['cantidad']` que tomaba ceros por defecto) por la evaluación de cantidad positiva real (`cantInicial > 0 ? cantInicial : cantidad`), asegurando que `total_disponible` se calcule dinámicamente (`cantInicial + ingresos`) y la tabla y totales del PDF muestren el conteo físico fiel en frontend/puntofrio_app/lib/presentation/screens/turnos/conteo_pdf_service.dart
- [X] T161 [P] [US23] En `corte_inventario_screen.dart`, en modo `apertura`, sincronizar el cambio de `BottleFractionSelector` tanto en `'cantidad'` como en `'cantidad_inicial'`, y antes de desplegar el diálogo modal "¡Turno de Barra Iniciado!", recalcular `total_disponible = cantidad_inicial + ingresos` en `_items` garantizando que el PDF generado inmediatamente reciba datos válidos en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T162 [P] [US23] En `TurnoController.php` (`corteInicial`) y `AbrirTurnoUseCase.php`, verificar que cada corte de apertura se inserte en `cortes_inventario` con `tipo_corte = 'apertura'` y que el endpoint `GET /turnos/{id}/corte-inicial` mapee fielmente `cantidad_inicial` desde la base de datos sin alterar los decimales en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T163 [US23] Validar el flujo end-to-end de apertura de turno, diálogo inmediato con PDF de conteo físico real, compartir en WhatsApp y regeneración desde el Dashboard según Scenario 20 en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 29: User Story 24 - Rol Cajera: Suplencia Operativa de Conteo, Auditoría Visual y Liquidación en Barra (Priority: P1)

**Goal**: Permitir a las cajeras acceder a la aplicación móvil con su propio rol y PIN, auditar en tiempo real el stock de barra y el Acta PDF, realizar cortes de apertura y cierre en suplencia si el barman no asiste o se ausenta, y confirmar el desembolso de comisiones con fotografía obligatoria de comprobante.  
**Independent Test**: Login con PIN de cajera; realizar apertura por suplencia seleccionando al barman programado; verificar que el Acta PDF señale "Suplencia por Cajera"; acceder a la liquidación de comisiones y confirmar el desembolso capturando foto del dinero.

- [X] T164 [P] [US24] Agregar `case CAJERA = 'cajera'` en enum `RolUsuario.php` y habilitar autenticación por PIN con sucursal para el rol cajera en backend/app/Domain/Enums/RolUsuario.php y backend/app/Application/UseCases/Auth/LoginPinUseCase.php
- [X] T165 [P] [US24] Crear migración para agregar `realizado_por_usuario_id`, `cerrado_por_usuario_id` y `es_suplencia` a la tabla `turnos` en backend/database/migrations/2026_10_01_000001_add_suplencia_fields_to_turnos_table.php
- [X] T166 [US24] Modificar `AbrirTurnoUseCase` y `CerrarTurnoUseCase` para persistir la firma de suplencia y validar que la cajera solo pueda abrir a nombre de un barman asignado en backend/app/Application/UseCases/Turnos/AbrirTurnoUseCase.php y backend/app/Application/UseCases/Turnos/CerrarTurnoUseCase.php
- [X] T167 [US24] Implementar endpoint `POST /turnos/{id}/confirmar-pago-comision` con validación de imagen fotográfica y sellado de estado `cobrado` en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T168 [P] [US24] Crear pantalla `DashboardCajeraScreen` con tarjetas de estado de barra (abrir suplencia, ver conteo/PDF en tiempo real, auditar comisiones con cámara y corte de cierre) en frontend/puntofrio_app/lib/presentation/screens/cajera/dashboard_cajera_screen.dart
- [X] T169 [US24] En `login_screen.dart` y `auth_provider.dart`, añadir getter `esCajera` y enrutar automáticamente a `DashboardCajeraScreen` tras login con PIN en frontend/puntofrio_app/lib/presentation/screens/auth/login_screen.dart
- [X] T170 [US24] Actualizar `conteo_pdf_service.dart` y `cierre_turno_pdf_service.dart` para desplegar el membrete y firma de "Suplencia por Cajera: [Nombre]" cuando `es_suplencia` sea verdadero en frontend/puntofrio_app/lib/presentation/screens/turnos/conteo_pdf_service.dart
- [X] T171 [US24] Validar el flujo operativo completo del rol cajera según Scenario 21 en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 30: User Story 25 - Conteo Resiliente: Búsqueda Reactiva, Refresco de Catálogo sin Pérdida de Estado y Contabilización de Productos Provisionales (Priority: P1)

**Goal**: Dotar a la pantalla de conteo físico de búsqueda instantánea, refresco diferencial que no borre lo ya digitado, persistencia local de borrador en SharedPreferences y formulario rápido para contabilizar productos no listados con alerta al Administrador.  
**Independent Test**: Digitar cantidades de productos; filtrar por buscador y verificar que los productos ocultos preserven sus números; presionar recargar catálogo simulando producto nuevo del Admin y constatar que no se resetee el conteo; simular salida forzada de la app y verificar restauración automática de borrador; registrar "+ Producto no listado" y validar emisión de alerta en backend.

- [X] T172 [P] [US25] Crear migración para agregar `es_provisional` (boolean) y `nombre_provisional` (varchar nullable) a la tabla `cortes_inventario` en backend/database/migrations/2026_10_01_000002_add_provisional_fields_to_cortes_inventario_table.php
- [X] T173 [US25] Actualizar `TurnoController.php` y `AbrirTurnoUseCase.php` para admitir ítems provisionales en el array de conteo y emitir alerta de auditoría `producto_provisional` en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T174 [P] [US25] Implementar endpoints `POST /alertas/{id}/aprobar-producto` y `POST /alertas/{id}/unificar-producto` en backend/app/Infrastructure/Http/Controllers/Api/AlertaController.php
- [X] T175 [US25] En `corte_inventario_screen.dart`, integrar barra de búsqueda reactiva superior que filtre en memoria `_itemsFiltrados` preservando el estado de las cantidades en `_items` en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T176 [US25] En `corte_inventario_screen.dart`, implementar botón de refresco (`Icons.refresh`) en el AppBar con recarga de catálogo y merge inteligente con `Map<int, double> _cantidadesDigitadas` en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T177 [US25] En `corte_inventario_screen.dart`, implementar persistencia local del borrador de conteo con `SharedPreferences` (`corte_draft_{sucursalId}_{tipoOperacion}`) con auto-guardado en cada cambio, restauración automática y purga al confirmar en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T178 [US25] En `corte_inventario_screen.dart`, implementar modal rápido "+ Contabilizar Producto no Listado" (Nombre, Cantidad y Switch "Es Licor Fraccionable") marcando el ítem como provisional en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T179 [US25] Validar el flujo de búsqueda, recarga sin pérdida de datos, persistencia de borrador y alerta de auditoría de producto provisional según Scenario 22 en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 31: User Story 26 - Desbloqueo de Reconteo de Inventario Autorizado por Administrador con Memoria de Conteo Previo y Corrección Selectiva (Priority: P1)

**Goal**: Permitir al Administrador desbloquear temporalmente el corte de un turno sellado para que el barman/garzón corrija errores de conteo en la app móvil, precargando el 100% de los valores previamente contados (memoria) para modificar únicamente el ítem erróneo, auto-consumiendo el permiso, auditando las diferencias y regenerando el acta oficial.  
**Independent Test**: Autorizar reconteo de apertura desde el teléfono del Admin con motivo justificado; verificar que en el teléfono del barman aparezca el aviso dorado; abrir la pantalla de conteo y constatar que todos los valores previamente digitados aparezcan intactos; cambiar solo 1 producto (ej. 2 a 24); confirmar la corrección; validar que el servidor registre el diff en `auditorias_reconteo`, recalcule el inventario, revoque el permiso y el PDF oficial refleje la leyenda de corrección.

- [X] T180 [P] [US26] Crear migración para campos de reconteo en `turnos` (`permite_reconteo`, `reconteo_tipo`, `reconteo_autorizado_por_id`, `reconteo_autorizado_at`, `reconteo_motivo`) y tabla `auditorias_reconteo` (`turno_id`, `usuario_id`, `admin_id`, `tipo_corte`, `motivo`, `detalles_json`) en backend/database/migrations/2026_10_01_000003_add_reconteo_fields_and_table.php
- [X] T181 [P] [US26] Crear modelo Eloquent `AuditoriaReconteo` con casts a JSON y relaciones con `Turno` y `Usuario` en backend/app/Infrastructure/Persistence/Eloquent/Models/AuditoriaReconteo.php
- [X] T182 [US26] Implementar endpoint `POST /turnos/{id}/autorizar-reconteo` en `TurnoController.php` validando rol admin, guardando `permite_reconteo = true`, `reconteo_tipo` y `reconteo_motivo` en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T183 [US26] Implementar endpoint `POST /turnos/{id}/aplicar-reconteo` con `DB::transaction()`, comparación de conteo anterior vs nuevo, inserción en `auditorias_reconteo`, actualización en `cortes_inventario`, recálculo de stock y auto-consumo `permite_reconteo = false` en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T184 [P] [US26] Registrar rutas de reconteo (`/turnos/{id}/autorizar-reconteo` y `/turnos/{id}/aplicar-reconteo`) en backend/routes/api.php
- [X] T185 [US26] En `dashboard_admin_screen.dart`, añadir botón `[ 🔓 Habilitar Reconteo ]` en la tarjeta de turno y modal de diálogo con selector de tipo de corte ('apertura'/'cierre') y campo motivo en frontend/puntofrio_app/lib/presentation/screens/dashboard/dashboard_admin_screen.dart
- [X] T186 [US26] En `dashboard_barman_screen.dart`, evaluar `turno.permite_reconteo == true` y renderizar Card destacada dorada/amarilla de aviso que redirija directamente a la corrección del corte en frontend/puntofrio_app/lib/presentation/screens/dashboard/dashboard_barman_screen.dart
- [X] T187 [US26] En `corte_inventario_screen.dart`, implementar modo reconteo con precarga automática del 100% de los valores anteriores desde `GET /turnos/{id}/corte-inicial` en `_cantidadesDigitadas`, edición selectiva y envío a `POST /turnos/{id}/aplicar-reconteo` en frontend/puntofrio_app/lib/presentation/screens/turnos/corte_inventario_screen.dart
- [X] T188 [US26] En `conteo_pdf_service.dart` y `cierre_turno_pdf_service.dart`, incorporar rótulo oficial "Versión Corregida con Autorización del Administrador [Nombre] - Motivo: [Motivo]" en frontend/puntofrio_app/lib/presentation/screens/turnos/conteo_pdf_service.dart y frontend/puntofrio_app/lib/presentation/screens/turnos/cierre_turno_pdf_service.dart
- [X] T189 [US26] Validar el flujo operativo completo de reconteo con autorización, memoria previa y re-sellado según Scenario 23 en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 32: User Story 27 - Plataforma Web en Tiempo Real (Sync 1 min) y Consola de Control Super Usuario (Priority: P1)

**Goal**: Implementar la plataforma web de control para Super Usuario (Daniel) y Dueño en `frontend_web/` (Vue 3 / Vite), con autenticación JWT, sondeo reactivo cada 60 segundos del estado de todas las sucursales (Casa22, Coron, Madan), visor de logs en tiempo real y consola para forzar resincronización de turnos.  
**Independent Test**: Iniciar sesión en la web como Super Usuario; verificar que el dashboard cargue métricas en vivo de todas las casas; simular una venta/corte y verificar actualización automática del reloj y contadores a los 60s sin refrescar; presionar "Forzar Resincronización" y constatar respuesta de API y log en pantalla.

- [X] T190 [P] [US27] Crear migración para agregar roles web (`super_admin`, `dueno`, `contadora`, `auxiliar_contable`) y campos de usuario (`email`, `password_hash`) en `usuarios` en backend/database/migrations/2026_10_02_000001_add_web_auth_roles_to_usuarios_table.php
- [X] T191 [P] [US27] Implementar `LoginWebUseCase` y endpoint `POST /auth/web/login` con emisión de JWT / Sanctum token y discriminación de permisos en backend/app/Infrastructure/Http/Controllers/Api/AuthController.php
- [X] T192 [US27] Implementar endpoint `GET /dashboard/metricas-tiempo-real` que consolide estado de turnos activos, ventas en efectivo/QR, stock en barra y alertas rojas de las 3 sucursales en backend/app/Infrastructure/Http/Controllers/Api/DashboardController.php
- [X] T193 [US27] Implementar endpoint `POST /turnos/{id}/forzar-resincronizacion` y `GET /sistema/logs-en-vivo` exclusivo para Super Admin en backend/app/Infrastructure/Http/Controllers/Api/TurnoController.php
- [X] T194 [P] [US27] En `frontend_web/`, configurar cliente Axios/Fetch con interceptores JWT, manejo de token en localStorage y redirección a login en frontend_web/src/plugins/axios.js
- [X] T195 [US27] Crear pantalla de Login Web con validación de credenciales y enrutamiento por rol en frontend_web/src/pages/login.vue
- [X] T196 [US27] Crear vista `DashboardSuperAdmin.vue` con tarjetas de las 3 sucursales, reloj dinámico con timer de polling de 60 segundos y tabla de turnos en servicio en frontend_web/src/pages/dashboard/super-admin.vue
- [X] T197 [US27] Crear componente `ConsolaHerramientasTecnicas.vue` para Daniel con visor de logs de API en vivo y botón "Forzar Resincronización de Turno" en frontend_web/src/views/dashboard/ConsolaHerramientasTecnicas.vue
- [X] T198 [US27] Validar el flujo de login web, actualización en vivo a los 60s y ejecución de resincronización técnica según Scenario 24 en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 33: User Story 28 - Auditoría con Gemini Vision de Planillas Físicas vs Vouchers de Depósito (Priority: P1)

**Goal**: Implementar el motor de OCR y conciliación automática con Google Gemini 1.5 Flash Vision para procesar fotos de planillas físicas manuscritas y vouchers de depósito bancario subidos al Grupo de WhatsApp de Recaudaciones o a la web, detectando faltantes y alertando al grupo de recaudación.  
**Independent Test**: Subir foto de planilla manuscrita (5,000 Bs - 300 Bs gastos = 4,700 Bs sobre) y foto de voucher de banco (4,200 Bs); constatar que Gemini extraiga los montos, calcule la brecha de 500 Bs y emita la alerta de discrepancia en el grupo de WhatsApp.

- [X] T199 [P] [US28] Crear migración para tablas `planillas_caja`, `vouchers_deposito` y `recaudaciones_diarias` con estados de auditoría en backend/database/migrations/2026_10_02_000002_create_recaudaciones_and_vouchers_tables.php
- [X] T200 [P] [US28] Crear servicio `GeminiVisionService.php` para conexión con Google Gemini API y prompts estructurados para lectura de vouchers bancarios y planillas manuscritas en backend/app/Infrastructure/AI/GeminiVisionService.php
- [X] T201 [US28] Implementar `ConciliarRecaudacionUseCase` que compara el monto del sobre declarado contra el depósito bancario y calcula la discrepancia en backend/app/Application/UseCases/Auditoria/ConciliarRecaudacionUseCase.php
- [X] T202 [US28] Implementar endpoints `POST /recaudaciones/procesar-planilla`, `POST /recaudaciones/procesar-voucher` y `GET /recaudaciones/diarias` en backend/app/Infrastructure/Http/Controllers/Api/RecaudacionController.php
- [X] T203 [P] [US28] Implementar servicio de notificación a WhatsApp Bot para despachar alertas al Grupo Exclusivo de Recaudaciones en backend/app/Infrastructure/Services/WhatsAppNotificationService.php
- [X] T204 [US28] Crear vista `AuditoriaRecaudaciones.vue` en la plataforma web con comparador visual lado a lado (Foto Planilla vs Foto Voucher vs Ventas de Sistema) en frontend_web/src/pages/auditoria/recaudaciones.vue
- [X] T205 [US28] Añadir modal para carga manual de fotos de planillas y vouchers con previsualización inmediata en frontend_web/src/views/auditoria/ModalCargaRecaudacion.vue
- [X] T206 [US28] Validar extracción OCR con Gemini Vision de voucher y planilla con cálculo de discrepancia según Scenario 25 en specs/001-control-inventario-transformaciones/quickstart.md
- [X] T207 [US28] Validar emisión de alerta automática al grupo de WhatsApp de recaudaciones cuando existe faltante de efectivo

---

## Phase 34: User Story 29 - Auditoría Híbrida de Caja Chica y Reposiciones desde Ventas (Priority: P1)

**Goal**: Implementar el control estricto de Caja Chica y gastos operativos deducidos de las ventas en efectivo, cruzando lo anotado en la planilla física contra las fotos de comprobantes/recibos enviadas al grupo de WhatsApp.  
**Independent Test**: Declarar en planilla "Gasto hielo 80 Bs" y subir recibo; verificar que el sistema empareje el gasto y apruebe el descuento. Declarar "Gasto 50 Bs" sin foto; verificar que la web marque el gasto en naranja como `OBSERVADO_SIN_COMPROBANTE`.

- [X] T208 [P] [US29] Crear migración para tabla `gastos_caja_chica` (`sucursal_id`, `turno_id`, `concepto`, `monto_bs`, `foto_comprobante_url`, `estado_comprobante`, `aprobado_por_id`) en backend/database/migrations/2026_10_02_000003_create_gastos_caja_chica_table.php
- [X] T209 [US29] Implementar servicio de cruce OCR que empareja recibos fotográficos de WhatsApp con los ítems manuscritos de la planilla en backend/app/Infrastructure/AI/AuditoriaGastosService.php
- [X] T210 [US29] Implementar endpoints `POST /gastos-caja-chica`, `POST /gastos-caja-chica/{id}/aprobar` y `GET /gastos-caja-chica/pendientes` en backend/app/Infrastructure/Http/Controllers/Api/GastoCajaChicaController.php
- [X] T211 [US29] Crear vista `ControlCajaChica.vue` en la web con listado de gastos del día, semáforo de respaldo fotográfico y botón de aprobación para Daniel/Contadora en frontend_web/src/pages/caja-chica/index.vue
- [X] T212 [US29] Validar el cruce automático de recibos contra planilla física según Scenario 26 en specs/001-control-inventario-transformaciones/quickstart.md
- [X] T213 [US29] Validar retención de deducciones para gastos sin comprobante fotográfico en el arqueo diario
- [X] T214 [US29] Validar flujo de aprobación manual por Daniel o Contadora desde la web

---

## Phase 35: User Story 30 - Control Inteligente de Taxis, Rotación de Chicas y Comisiones con IA (Priority: P2)

**Goal**: Implementar la supervisión con IA de los mensajes del grupo de WhatsApp de rotación de chicas y comisiones de taxis, verificando montos cobrados contra tabla de tarifas estándar entre sucursales y alertando sobre traslados compartidos cobrados doble o sobreprecios.  
**Independent Test**: Enviar mensaje de taxi con tarifa normal (15 Bs entre Casa22 y Madan); verificar registro conforme. Enviar mensaje con tarifa inflada (40 Bs); verificar que la IA lo marque en amarillo en el consolidado diario.

- [X] T215 [P] [US30] Crear migración para `tarifas_rutas_taxis` y `registros_traslados_taxis` en backend/database/migrations/2026_10_02_000004_create_taxis_and_rotaciones_tables.php
- [X] T216 [US30] Implementar servicio con Gemini NLP para parsear mensajes de texto de traslados (`{origen, destino, personal, monto_bs}`) en backend/app/Infrastructure/AI/TaxiParserService.php
- [X] T217 [US30] Implementar `ValidarTarifaTaxiUseCase` que contrasta el monto contra el rango de la ruta y verifica duplicidad horaria de traslados en backend/app/Application/UseCases/Auditoria/ValidarTarifaTaxiUseCase.php
- [X] T218 [US30] Implementar endpoints `POST /taxis/procesar-mensaje` y `GET /taxis/reporte-diario` en backend/app/Infrastructure/Http/Controllers/Api/TaxiController.php
- [X] T219 [US30] Crear vista `ReporteTaxisRotacion.vue` en la plataforma web con gráfico de gasto diario de movilidad por casa y alertas de sobreprecio en frontend_web/src/pages/movilidad/taxis.vue
- [X] T220 [US30] Configurar tabla paramétrica de tarifas base entre sucursales (Casa22 ↔ Coron, Casa22 ↔ Madan, Coron ↔ Madan) en base de datos
- [X] T221 [US30] Validar detección de tarifas infladas y cobros duplicados según Scenario 27 en specs/001-control-inventario-transformaciones/quickstart.md

---

## Phase 36: User Story 31 - Despliegue y Resiliencia Web en Hosting cPanel (Priority: P1)

**Goal**: Garantizar que el frontend web SPA y la API de Laravel funcionen de forma resiliente en el hosting cPanel (`c22.ribersoft.com`) sin dependencias de sesiones en base de datos, con fallback a driver `file`, desvinculación de `StartSession` en rutas web SPA estáticas, migración de `sessions` en caso de requerirse, y script de despliegue automatizado `deploy.sh` testeado y verificado.  
**Independent Test**: Visitar `https://c22.ribersoft.com/` y `/login` sin que se invoque la tabla de sesiones en MySQL; ejecutar `deploy.sh` en cPanel y verificar código 200 HTTP.

- [X] T222 [P] [US31] Crear migración para tabla `sessions` en `backend/database/migrations/2026_10_02_000005_create_sessions_table.php`
- [X] T223 [US31] Configurar resiliencia de driver de sesión con fallback seguro a `file` en `backend/config/session.php`
- [X] T224 [US31] Desacoplar middleware `StartSession` de las rutas web SPA en `backend/bootstrap/app.php` para entrega inmediata sin DB
- [X] T225 [US31] Empaquetar y sincronizar bundle compilado de producción en `backend/public/` (`index.html`, `assets/`, `images/`)
- [X] T226 [US31] Actualizar script automatizado `backend/deploy.sh` con pull, migración forzada y limpieza de caché

---

## Phase 37: User Story 32 - Sincronización POS RestoTech y Conciliación Triangulada Unificada (Priority: P1)

**Goal**: Implementar la extracción automática de transacciones desde la computadora de caja de la sucursal (SQL Server `ControlConsumoCasa22`), la ingesta segura en Laravel mediante `X-Branch-Token`, el mapeo de productos/combos a recetas de c22 y la matriz de conciliación triangulada unificada en 3 columnas ([POS SQL Server] vs [Planilla Manual de Caja] vs [Conteo de Barra]).  
**Independent Test**: Extraer transacciones desde la máquina de caja con el script local; verificar ingesta en `pos_transacciones`; asociar un combo a receta en la web; ejecutar `GET /api/v1/auditoria/conciliacion-triangulada/{turno_id}` y validar que los faltantes de efectivo se imputen a Cajera y los faltantes de botellas a Barman según Scenario 15 en `quickstart.md`.

- [X] T227 [P] [US32] Crear migración para tablas `pos_producto_mapeo`, `pos_transacciones` y `auditorias_conciliacion_triangulada` en `backend/database/migrations/2026_10_02_000006_create_pos_integration_tables.php`
- [X] T228 [P] [US32] Crear modelos Eloquent `PosProductoMapeo`, `PosTransaccion` y `AuditoriaConciliacionTriangulada` en `backend/app/Models/`
- [X] T229 [P] [US32] Implementar middleware `VerifyBranchToken` en `backend/app/Infrastructure/Http/Middleware/VerifyBranchToken.php` para autenticar peticiones de sucursal mediante encabezado `X-Branch-Token`
- [X] T230 [US32] Implementar `PosSyncController@ingestarTransacciones` para `POST /api/v1/sync/pos-transacciones` con inserción idempotente y auto-desglose de recetas en `backend/app/Infrastructure/Http/Controllers/Api/PosSyncController.php`
- [X] T231 [US32] Implementar `PosMapeoController` para gestión de mapeo de productos POS `GET / POST /api/v1/pos/mapeo-productos` en `backend/app/Infrastructure/Http/Controllers/Api/PosMapeoController.php`
- [X] T232 [US32] Implementar caso de uso `AuditoriaConciliacionTrianguladaUseCase` y endpoint `GET /api/v1/auditoria/conciliacion-triangulada/{turno_id}` en `backend/app/Application/UseCases/Auditoria/AuditoriaConciliacionTrianguladaUseCase.php`
- [X] T233 [P] [US32] Desarrollar el Agente Local de Windows en `scripts/sync_agent/sync_casa22.ps1` y script en Python con buffer local offline en `scripts/sync_agent/agent.py`
- [X] T234 [P] [US32] Crear instalador por lotes `scripts/sync_agent/instalar.bat` para registrar la tarea en el Programador de Tareas de Windows (arranque silencioso en segundo plano)
- [X] T235 [US32] Registrar las nuevas rutas de sincronización POS y conciliación triangulada en `backend/routes/api.php`
- [X] T236 [P] [US32] Crear la vista de Mapeo de Productos POS en la plataforma web en `frontend_web/src/pages/pos/mapeo.vue`
- [X] T237 [US32] Crear la vista de Matriz de Conciliación Triangulada en 3 Columnas ([POS] vs [Planilla Manual OCR] vs [Barra]) en `frontend_web/src/pages/auditoria/conciliacion-triangulada.vue`
- [X] T238 [US32] Validar el flujo de sincronización y cruce triangulado según Scenario 15 en `specs/001-control-inventario-transformaciones/quickstart.md`


---

## Dependencies & Execution Order

### Phase Dependencies
- **Phases 1 a 28**: Completadas y verificadas [X].
- **Phase 29 (US24 - Rol Cajera y Suplencia en Barra)**: Completada y verificada [X].
- **Phase 30 (US25 - Conteo Resiliente, Búsqueda, Refresco y Producto Provisional)**: Completada y verificada [X].
- **Phase 31 (US26 - Desbloqueo de Reconteo de Inventario Autorizado por Administrador)**:
  - T180, T181 y T184 preparan la base de datos, modelo y enrutamiento en Laravel [P].
  - T182 y T183 implementan la lógica de autorización y aplicación atómica de reconteo en `TurnoController.php`.
  - T185 dota al Administrador de la capacidad de desbloqueo remoto en su APK.
  - T186 y T187 implementan en el barman la detección del permiso, la precarga de memoria (100% de valores anteriores) y la confirmación selectiva en `corte_inventario_screen.dart`.
  - T188 estampa la leyenda de versión corregida en los PDFs oficiales.
  - T189 valida el escenario end-to-end (Scenario 23).

---

## Parallel Opportunities

```bash
# Tareas Backend y Base de Datos Paralelas:
Task T180: "Migración de reconteo en turnos y tabla auditorias_reconteo"
Task T181: "Modelo Eloquent AuditoriaReconteo"
Task T184: "Rutas de reconteo en api.php"

# Tareas Frontend Paralelas:
Task T185: "Botón y modal de habilitación de reconteo en dashboard_admin_screen.dart"
Task T186: "Aviso de reconteo autorizado en dashboard_barman_screen.dart"
Task T188: "Rótulo de versión corregida en conteo_pdf_service.dart y cierre_turno_pdf_service.dart"
```

---

## Notes
- Cada tarea sigue estrictamente el formato `- [ ] [TaskID] [P?] [Story?] Descripción con ruta de archivo`.
- Los endpoints y esquemas respetan con exactitud `data-model.md` y `contracts/api-contracts.md`.
