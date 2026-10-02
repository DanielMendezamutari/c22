# Implementation Plan: Sistema de Inteligencia y Control de Inventario (Grupo Punto Frío) v1.2

**Branch**: `001-control-inventario-transformaciones` | **Date**: 2026-09-05 | **Spec**: [spec.md](file:///c:/xampp/htdocs/next22/specs/001-control-inventario-transformaciones/spec.md)

**Input**: Feature specification from `/specs/001-control-inventario-transformaciones/spec.md`

---

## Summary

Implementar una plataforma integral de control operativo, auditoría empírica de transformaciones (rellenos) y liquidación justa de comisiones para Grupo Punto Frío (Casa22, Casa Coron, Madan). 

El sistema consta de dos componentes principales:
1. **Backend API RESTful en Laravel** implementado con **Arquitectura Hexagonal (Domain, Application, Infrastructure)**, base de datos relacional MySQL Multi-Tenant a nivel lógico (`sucursal_id`), precisión decimal en fracciones de licor (`DECIMAL(8,2)`), transacciones atómicas `DB::transaction()` y motor de auditoría con desglose automático de combos.
2. **Aplicación Móvil en Flutter (Android/iOS)** para barmen, con arquitectura **Local-First / Offline-First** (Riverpod + SQLite `SyncQueue`), acceso ultra-rápido por PIN de 4 dígitos, selector táctil de fracciones de botella (4 niveles), captura obligatoria de fotografía en ingresos de mercadería y pantalla dinámica de cobro a cajera con segundero en vivo anti-fraude.

---

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 11), Dart 3.3+ (Flutter 3.19+)  
**Primary Dependencies**: 
- *Backend*: Laravel Framework, Laravel Sanctum (Tokens API), Intervention Image (manejo de imágenes).
- *Frontend*: `flutter_riverpod` (gestor de estado), `sqflite` (persistencia local offline), `connectivity_plus` (detector de red), `image_picker` / `camera` (evidencia fotográfica), `uuid` (idempotencia).  
**Storage**: MySQL 8.0+ / MariaDB en Hosting Compartido (tablas con `DECIMAL(8,2)` para precisión de fracciones), Almacenamiento local o S3 para fotos de notas, SQLite local en el dispositivo móvil.  
**Testing**: PHPUnit / Pest para pruebas unitarias de casos de uso y de dominio; `flutter_test` para widgets y coordinación de sincronización offline.  
**Target Platform**: Servidor Linux Apache con cPanel (Hosting Compartido estándar) + Dispositivos móviles Android e iOS.  
**Project Type**: Web Service API (Laravel) + Mobile Client Application (Flutter).  
**Performance Goals**: 
- Respuesta de API de barra en < 800ms.
- Interfaz táctil de Flutter reactiva en < 50ms (Local-First).
- Sincronización automática en segundo plano sin congelar la interfaz.  
**Constraints**: 
- Tolerancia total a cortes de conexión en barra (operación 100% funcional sin internet durante el servicio).
- 0% de comisión pagada sobre productos no transformados.
- Desacoplamiento total del sistema POS de caja.  
**Scale/Scope**: Múltiples sucursales (Casa22, Casa Coron, Madan y futuras), barmen rotativos semanalmente, jornadas de 12 horas.  

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio Constitucional | Estado | Evidencia en el Diseño |
| :--- | :---: | :--- |
| **I. Descubrimiento Empírico de Rendimiento** | **PASS** | El caso de uso `RegistrarTransformacionUseCase` registra entrada real (latas) y salida real (botellas Corona), computando el coeficiente real histórico sin fijar equivalencias teóricas rígidas. |
| **II. Justicia Financiera en Comisiones** | **PASS** | `RegistrarTransformacionUseCase` liquida comisiones exclusivamente sobre `unidades_terminadas - roturas_declaradas`. Prohibido pago sobre ventas de caja o stock de fábrica. |
| **III. Responsabilidad Estricta por Turno (12h)** | **PASS** | `AbrirTurnoUseCase` y `CerrarTurnoUseCase` exigen cortes inicial y final inmutables, soportando fracciones exactas (`0.25`, `0.50`, `0.75`). |
| **IV. Desacoplamiento del POS** | **PASS** | El módulo de auditoría es autónomo: `CalcularAuditoriaUseCase` cruza el balance físico contra el Ticket Z de caja ingresado por el administrador sin conexión al software de caja. |
| **V. Auditoría Centralizada Multi-Sucursal** | **PASS** | Multi-tenant lógico (`sucursal_id`), trazabilidad de traspasos en tránsito con endpoint `POST /traspasos/{id}/recibir` y registro de mermas en traslado. |
| **Inmutabilidad y Reconteo Auditado (US26)** | **PASS** | El reconteo solo es desbloqueable por el Administrador, es efímero (`permite_reconteo` auto-consumible), audita diferencias en `auditorias_reconteo` y re-sella el turno de inmediato. |
| **Ecuación de Balance de Masa** | **PASS** | Implementada estrictamente: `(Stock Inicial + Ingresos + Traspasos - Materia Prima + Prod. Terminado) - Bajas - Stock Final = Ventas Reales + Faltantes/Sobrantes`. |


---

## Project Structure

### Documentation (this feature)

```text
specs/001-control-inventario-transformaciones/
├── plan.md              # Este plan de implementación
├── research.md          # Investigación técnica y decisiones arquitectónicas (Fase 0)
├── data-model.md        # Esquema relacional de base de datos y entidades (Fase 1)
├── contracts/           
│   └── api-contracts.md # Contratos RESTful de endpoints Laravel (Fase 1)
├── quickstart.md        # Guía de validación end-to-end de escenarios (Fase 1)
└── checklists/
    └── requirements.md  # Checklist de calidad de requisitos (16/16 aprobado)
```

### Source Code Architecture

```text
backend/ (Laravel 11 - Arquitectura Hexagonal)
├── app/
│   ├── Domain/                      # Capa 1: Núcleo puro de negocio
│   │   ├── Entities/                # Turno, Movimiento, Producto, RecetaTransformacion, RecetaCombo, Sancion
│   │   ├── ValueObjects/            # FraccionLicor, RatioConversion, DineroBs, CodigoRecibo
│   │   ├── Enums/                   # TipoMovimiento, TipoTurno, EstadoTurno, EstadoTraspaso, ModalidadCobro
│   │   └── Ports/                   # Interfaces de Repositorios y Servicios (TurnoRepoInterface, etc.)
│   ├── Application/                 # Capa 2: Casos de Uso (Use Cases)
│   │   ├── UseCases/Turnos/         # AbrirTurnoUseCase, CerrarTurnoUseCase, SolicitarCobroCajeraUseCase
│   │   ├── UseCases/Transformacion/ # RegistrarTransformacionUseCase, RegistrarBajaUseCase
│   │   ├── UseCases/Inventario/     # RegistrarIngresoUseCase, EnviarTraspasoUseCase, RecibirTraspasoUseCase
│   │   ├── UseCases/Auditoria/      # CalcularAuditoriaUseCase, GenerarLiquidacionSemanalUseCase, ObtenerReporteRatiosUseCase
│   │   └── UseCases/Recetas/        # GestionarRecetasUseCase, GestionarCombosUseCase
│   └── Infrastructure/              # Capa 3: Adaptadores de Entrada y Salida
│       ├── Http/
│       │   ├── Controllers/Api/     # AuthController, TurnoController, TransformacionController, TraspasoController, AuditoriaController, RecetaController
│       │   ├── Requests/            # Validaciones FormRequest (múltiplos 0.25, archivos fotos, recetas, combos)
│       │   └── Resources/           # API Resources JSON
│       ├── Persistence/
│       │   ├── Eloquent/Models/     # Modelos Eloquent con sucursal_id
│       │   └── Repositories/        # Implementaciones de puertos usando Eloquent y DB::transaction
│       └── Services/                # Almacenamiento de archivos (LocalStorageService / S3Service)
├── database/
│   └── migrations/                  # Migraciones con DECIMAL(8,2) y relaciones foráneas
└── tests/
    ├── Unit/Domain/                 # Pruebas unitarias de reglas de negocio y fracciones
    └── Feature/Api/                 # Pruebas de integración de endpoints REST

frontend/puntofrio_app/ (Flutter 3.19+ - Arquitectura Local-First)
├── lib/
│   ├── core/
│   │   ├── config/                  # ApiConfig (Hosting compartido por defecto + override IP local)
│   │   ├── database/                # SQLite Helper y esquema de sync_queue
│   │   ├── network/                 # Dio/Http Client con interceptor de token Bearer
│   │   └── sync/                    # SyncCoordinator (Worker en segundo plano para sincronización offline)
│   ├── domain/
│   │   └── models/                  # Modelos de datos locales (Turno, Movimiento, Fraccion, Producto, Receta)
│   ├── data/
│   │   └── repositories/            # Repositorios híbridos (Local SQLite First -> Remote Sync)
│   └── presentation/
│       ├── providers/               # Riverpod StateNotifiers / AsyncNotifiers (Auth, Turno, Auditoria, Recetas)
│       ├── screens/
│       │   ├── auth/                # LoginScreen (Selector Sucursal + PIN Pad numérico)
│       │   ├── dashboard/           # DashboardBarmanScreen (Botones gigantes "Tap & Go")
│       │   ├── transformacion/      # TransformacionScreen (Relleno Corona con cálculo en vivo)
│       │   ├── ingreso/             # IngresoMercaderiaScreen (Integración de cámara obligatoria)
│       │   ├── traspaso/            # EnviarTraspasoScreen, RecibirTraspasoScreen
│       │   ├── cobro/               # ResumenCajeraScreen (Pantalla completa, segundero dinámico y código 60s)
│       │   └── admin/               # Módulo Admin: AuditoriaTicketZScreen, LiquidacionSemanalScreen, AlertasMermaScreen, RecetasScreen
│       └── widgets/
│           ├── bottle_fraction_selector.dart # Widget visual de botella con 4 niveles (1/4, 1/2, 3/4, Llena)
│           └── numeric_pin_pad.dart          # Teclado táctil numérico de 4 dígitos
└── test/                            # Pruebas unitarias de providers y cola offline
```

---

## Plan de Fases de Implementación (Tickets de Ingeniería)

### FASE 1: Core y Base de Datos (Laravel)
- **Ticket 1.1**: Estructura Hexagonal y Migraciones Base (`sucursales`, `usuarios` con PIN y modalidad de cobro, `productos`, `turnos` con `sucursal_id`).
- **Ticket 1.2**: Migraciones Core y Eloquent (`movimientos_inventario` con `DECIMAL(8,2)`, `recetas_transformacion`, `recetas_combos`, `traspasos`, `auditorias_ventas`, `sanciones_inventario`, `liquidaciones_semanales`).
- **Ticket 1.3**: Entidades de Dominio Puras (POPOs en PHP 8.2), Value Objects (`FraccionLicor`, `RatioConversion`), Enums de Dominio y Puertos/Interfaces de Repositorios.

### FASE 2: Casos de Uso (Application Layer - Laravel)
- **Ticket 2.1**: Gestión de Turnos (`AbrirTurnoUseCase`, `CerrarTurnoUseCase`, `SolicitarCobroCajeraUseCase` con código único de 60 segundos).
- **Ticket 2.2**: Transformaciones Atómicas y Bajas (`RegistrarTransformacionUseCase` con `DB::transaction()`, cálculo de ratios y asignación de comisión; `RegistrarBajaUseCase` con reversión de incentivos).
- **Ticket 2.3**: Ingresos y Traspasos (`RegistrarIngresoUseCase` con validación de imagen; `EnviarTraspasoUseCase` en estado 'En Tránsito'; `RecibirTraspasoUseCase` con asignación de conformes y mermas en tránsito).
- **Ticket 2.4**: Motor de Auditoría, Liquidación y Recetas (`CalcularAuditoriaUseCase` con desglose de combos y semáforos rojo/azul; `GenerarLiquidacionSemanalUseCase` para turno noche; `ObtenerReporteRatiosUseCase`; `GestionarRecetasUseCase` y `GestionarCombosUseCase` para CRUD dinámico).

### FASE 3: API REST (Infrastructure Layer - Laravel)
- **Ticket 3.1**: Controladores API (`AuthController`, `TurnoController`, `TransformacionController`, `TraspasoController`, `AuditoriaController`, `RecetaController`), FormRequests de validación (múltiplos de 0.25 para fracciones, archivos de fotos, esquemas de recetas y combos), Inyección de Dependencias y rutas en `routes/api.php`.

### FASE 4: Frontend Core (Flutter)
- **Ticket 4.1**: Setup de Flutter con Riverpod, configuración de persistencia SQLite local (`sync_queue`), implementación del `SyncCoordinator` para procesamiento en segundo plano con reintentos exponenciales y deduplicación por UUID.

### FASE 5: Frontend UI (Flutter - Barman y Administrador)
- **Ticket 5.1**: Pantalla de Login con PIN de 4 dígitos (con selector de sucursal para rotación semanal) y enrutamiento por rol (`barman` → Dashboard de barra, `admin` → Dashboard de administración).
- **Ticket 5.2**: Widget visual de botella con selector de fracciones en 4 niveles (1/4, 1/2, 3/4, Llena) retornando decimales exactos.
- **Ticket 5.3**: Pantalla de "Nuevo Ingreso" con captura fotográfica forzosa de la nota de entrega mediante cámara.
- **Ticket 5.4**: Pantalla "Resumen a Cajera" en pantalla completa, tipografía extra grande, reloj con segundero en vivo anti-captura y temporizador regresivo de 60 segundos con código de recibo.
- **Ticket 5.5**: Módulo Administrativo en Flutter: `AuditoriaTicketZScreen` (carga de Ticket Z con desglose de combos y visualización en rojo/azul), `LiquidacionSemanalScreen` (reporte y liquidación neta de barmen nocturnos), `AlertasMermaScreen` (ratios y desviaciones) y `RecetasScreen` (CRUD de recetas y tarifas de comisión).

### FASE 6: Módulos Avanzados (Compras Multi-Producto, Auditoría 3 Pasos + PDF y Foto de Cobro)
- **Ticket 6.1 (Compras Multi-Producto)**:
  - Backend: Migración de `compras` y `compras_detalles`, endpoint `POST /inventario/compras` con transacción atómica `DB::transaction()`.
  - Frontend: Pantalla `RegistrarCompraScreen` con lista dinámica de productos (`+ Añadir Producto`), captura fotográfica de factura y enlace en dashboard.
- **Ticket 6.2 (Auditoría en 3 Pasos y Reporte PDF)**:
  - Backend: Endpoint `GET /auditoria/turnos-pendientes?sucursal_id={id}` para filtrar turnos cerrados listos para conciliar.
  - Frontend: Rediseño guiado de la pantalla de auditoría en 3 pasos (1: Selección de turno, 2: Ventas Ticket Z, 3: Semáforo y balance) con generador de PDF vectorial oficial e impresión mediante `pdf` y `printing`.
- **Ticket 6.3 (Foto de Respaldo en Cobro de Relleno)**:
  - Backend: Campo `foto_comprobante_cobro` en tabla `turnos` y soporte en `POST /turnos/{id}/cobro-cajera`.
  - Frontend: Activación obligatoria de cámara con diálogo de vista previa en `ResumenCajeraScreen` al presionar "Cobro Recibido / Finalizar Turno".

### FASE 7: Gestión de Proveedores y Selección Rápida en Recepción (US19)
- **Ticket 7.1 (Backend Proveedores)**:
  - Migración `create_proveedores_table` (`id`, `nombre`, `contacto_nombre`, `telefono`, `nit_o_ci`, `direccion`, `activo`, `timestamps`).
  - Modelo `Proveedor` y relación con `Compra`.
  - Controlador `ProveedorController` con CRUD completo (`GET /proveedores`, `POST /proveedores`, `PUT /proveedores/{id}`, `DELETE /proveedores/{id}`).
- **Ticket 7.2 (Frontend Admin y Barman)**:
  - Pantalla `ProveedoresAdminScreen` con CRUD (nombre, teléfono, NIT, switch activo) accesible desde tarjeta "GESTIÓN DE PROVEEDORES" en `DashboardAdminScreen`.
  - En `IngresoMercaderiaScreen`: Selector desplegable con buscador rápido alimentado por `GET /proveedores?activo=1` para vincular automáticamente el proveedor.

### FASE 8: Monitoreo Operativo por Sucursal e Informe PDF para Administrador (US20)
- **Ticket 8.1 (Backend Auditoría Turno Sucursal)**:
  - Endpoint `GET /auditoria/sucursal/{sucursal_id}/informe-turno`: compila metadatos de jornada, conteo inicial, compras recepcionadas (con proveedor y notas), traspasos entrantes/salientes, bajas con motivos, transformaciones/comisiones, balance en custodia y liquidación financiera.
- **Ticket 8.2 (Frontend Pantalla y Servicio PDF)**:
  - Pantalla `InformeSucursalesScreen` con selector de sucursal (Casa22, Corona, Madan), visualización del turno activo en vivo o historial de turnos pasados.
  - Servicio `InformeOperativoTurnoPdfService`: generación de documento PDF oficial corporativo de auditoría con tablas de balance, firmas de custodios y botón de compartir en WhatsApp.

### FASE 9: Traspasos Reales con Validación y Bloqueo de Stock en Origen (US21)
- **Ticket 9.1 (Backend Control de Stock en Traspasos)**:
  - En `EnviarTraspasoUseCase`: Validar stock disponible en la sucursal emisora para cada producto (`Apertura + Ingresos + Traspasos Entrantes - Traspasos Salientes Previos - Bajas - Consumos`). Lanzar `DomainException` HTTP 400 si la cantidad solicitada excede el stock físico en barra.
  - Al despachar: Registrar movimiento `traspaso_salida` descontando stock de origen.
  - En `RecibirTraspasoUseCase`: Al confirmar recepción, registrar movimiento `traspaso_entrada` acreditando stock en destino.
- **Ticket 9.2 (Frontend Despacho Dinámico y Alerta)**:
  - En `EnviarTraspasoScreen`: Poblar dinámicamente sucursales destino reales desde `GET /sucursales` (excluyendo la propia) y productos reales desde `GET /productos`.
  - Consultar y mostrar el saldo en barra para cada producto (`Disponible en barra: X u.`).
  - Validar en tiempo real: Si la cantidad excede las existencias físicas, resaltar en rojo y desactivar el botón "DESPACHAR TRASPASO".

### FASE 10: Balance Integral en Conteo de Apertura PDF y Reconciliación en Cierre (US22)
- **Ticket 10.1 (Backend Balance Acumulado)**:
  - En `TurnoController.php`: Corregir consulta de ingresos (`['ingreso', 'traspaso_entrada']`), incluir productos recibidos en la jornada aunque no estuvieran en apertura, y calcular balances de transformaciones y bajas.
- **Ticket 10.2 (Frontend PDFs y Selectores)**:
  - En `cierre_turno_pdf_service.dart`: Reemplazar nombres genéricos por nombres descriptivos, suprimir columna "Tipo" y reformar tabla a 7 columnas de balance oficial.
  - En `bottle_fraction_selector.dart` y `corte_inventario_screen.dart`: Visualizar pill informativo con stock de inicio, ingresos y salida estimada en corte final.

### FASE 11: Fiel Reflejo del Conteo Físico Inicial en Acta Oficial de Conteo en PDF (Apertura) y Persistencia Inmutable (US23)
- **Ticket 11.1 (Frontend Corrección de Precedencia y Estado en Conteo)**:
  - En `corte_inventario_screen.dart`: En modo apertura, al modificar valores en `BottleFractionSelector`, sincronizar tanto `'cantidad'` como `'cantidad_inicial'` en el mapa de `_items`. Antes de desplegar el diálogo modal post-apertura, garantizar que `_items` contenga las cantidades reales contadas y `total_disponible = cantidad_inicial + ingresos`.
  - En `conteo_pdf_service.dart`: Corregir la precedencia de extracción numérica de cantidades para evitar que un valor `0.0` en `'cantidad_inicial'` enmascare el conteo ingresado en `'cantidad'`:
    ```dart
    final cantInicialRaw = (item['cantidad_inicial'] as num?)?.toDouble();
    final cantDirectaRaw = (item['cantidad'] as num?)?.toDouble();
    final double cantInicial = (cantInicialRaw != null && cantInicialRaw > 0)
        ? cantInicialRaw
        : (cantDirectaRaw ?? cantInicialRaw ?? 0.0);
    final double totalDisp = (item['total_disponible'] != null && (item['total_disponible'] as num).toDouble() > 0)
        ? (item['total_disponible'] as num).toDouble()
        : (cantInicial + ingresos);
    ```
    Garantizar que la tabla y el resumen de totales sumen con exactitud las unidades físicas ingresadas, sin ceros fantasmas.
- **Ticket 11.2 (Backend Persistencia y Consistencia en API)**:
  - En `TurnoController.php` (`corteInicial`): Asegurar que los cortes asentados con `tipo_corte = 'apertura'` o `'inicial'` se devuelvan íntegramente en `cantidad_inicial`, respetando los decimales exactos.
  - En `AbrirTurnoUseCase.php`: Certificar que cada elemento de `corte_inicial` se inserte en `cortes_inventario` con `tipo_corte = 'apertura'` y su fracción decimal estricta (`FraccionLicor`).

### FASE 12: Rol Cajera: Suplencia Operativa de Conteo, Auditoría Visual y Liquidación en Barra (US24)
- **Ticket 12.1 (Backend Rol Cajera y Endpoints de Suplencia)**:
  - Agregar `case CAJERA = 'cajera'` en `RolUsuario.php`.
  - Actualizar `LoginPinUseCase.php` para soportar autenticación por PIN con sucursal para el rol `cajera`.
  - Modificar `AbrirTurnoUseCase` y `CerrarTurnoUseCase` para registrar `realizado_por_usuario_id`, `cerrado_por_usuario_id` y bandera `es_suplencia = true`.
  - Endpoint `POST /turnos/{id}/confirmar-pago-comision`: recepción de fotografía obligatoria de comprobante, sellado del turno como `cobrado` y retorno de confirmación inmutable.
  - Reflejar en `ConteoPdfService` y `CierreTurnoPdfService` la leyenda explícita de "Suplencia por Cajera: [Nombre]".
- **Ticket 12.2 (Frontend Flutter Dashboard Cajera y Enrutamiento)**:
  - En `login_screen.dart`: Evaluar `auth.esCajera` y redirigir a `DashboardCajeraScreen`.
  - Crear pantalla `DashboardCajeraScreen`:
    - Estado A (Sin turno): Información de barra + botón "Abrir Turno (Suplencia)" seleccionando al barman programado en `CorteInventarioScreen`.
    - Estado B (Turno activo): Card con custodio actual + botón "Ver / Previsualizar Conteo de Barra (PDF)" + botón "Auditar / Liquidar Comisiones de Barra".
    - Estado C (Turno pendiente de cierre): Botón "Realizar Corte de Cierre (Suplencia)".
  - Diálogo de Confirmación de Desembolso: Captura de fotografía de respaldo con cámara del celular y confirmación directa desde la cuenta de la cajera.

### FASE 13: Conteo Resiliente: Búsqueda Reactiva, Refresco de Catálogo sin Pérdida de Estado y Contabilización de Productos Provisionales (US25)
- **Ticket 13.1 (Frontend Buscador, Refresco Inteligente y Persistencia de Borrador)**:
  - En `corte_inventario_screen.dart`:
    - Barra de búsqueda reactiva en la cabecera del conteo con filtrado instantáneo en memoria (`_filtroBusqueda`) que no altere ni limpie las cantidades de los productos ocultos.
    - Botón de refresco (`Icons.refresh`): consulta `GET /productos` y realiza merge inteligente con `Map<int, double> _cantidadesDigitadas`, incorporando productos recién creados por el Admin con cantidad 0.0 y preservando las cantidades ya digitadas en los demás ítems.
    - Persistencia local del borrador: Guardar automáticamente en `SharedPreferences` en cada modificación de cantidad (`corte_draft_{sucursalId}_{tipoOperacion}`). Al ingresar a la pantalla, si existe un borrador no confirmado, restaurarlo automáticamente notificando al usuario con opción de "Descartar Borrador". Al confirmar el corte con éxito, purgar el borrador local.
    - Botón "+ Contabilizar Producto no Listado": Modal rápido con campos: Nombre comercial, Cantidad física contada y Switch "Es Licor / Botella Fraccionable" (para cuartos 0.25, 0.50, 0.75). Se incorpora a la lista de conteo con `es_provisional = true`.
- **Ticket 13.2 (Backend Registro de Productos Provisionales y Alerta al Administrador)**:
  - Migración en `cortes_inventario`: agregar columnas `es_provisional` (boolean default false) y `nombre_provisional` (string nullable).
  - Al procesar `POST /turnos/abrir` o `POST /turnos/{id}/corte-cierre`: Si contiene ítems provisionales, asentarlos y disparar evento `AlertaDiscrepancia` de tipo `producto_provisional` con datos de sucursal, producto temporal y cantidad contada.
  - Endpoints en `AlertaController`:
    - `POST /alertas/{id}/aprobar-producto`: Permite al Admin transformar el ítem provisional en producto oficial del catálogo.
    - `POST /alertas/{id}/unificar-producto`: Permite al Admin asociarlo a un producto oficial ya existente (`producto_id_oficial`), transfiriendo las cantidades de forma transparente.

### FASE 14: Desbloqueo de Reconteo de Inventario Autorizado por Administrador con Memoria de Conteo Previo (US26)
- **Ticket 14.1 (Backend: Migración de Turnos, Auditoría de Diferencias y Endpoints Atómicos)**:
  - Migración en `turnos`: agregar `permite_reconteo` (boolean default false), `reconteo_tipo` (enum: 'apertura', 'cierre', nullable), `reconteo_autorizado_por_id` (foreignId nullable), `reconteo_autorizado_at` (timestamp nullable), `reconteo_motivo` (string nullable).
  - Tabla `auditorias_reconteo`: `id`, `turno_id`, `usuario_id`, `admin_id`, `tipo_corte`, `motivo`, `detalles_json` (producto_id, valor_anterior, valor_nuevo), `timestamps`.
  - En `TurnoController.php`:
    - `POST /turnos/{id}/autorizar-reconteo`: Valida rol admin, asienta `permite_reconteo = true`, registra `reconteo_tipo` y `motivo`.
    - `POST /turnos/{id}/aplicar-reconteo`:
      - Valida que `permite_reconteo === true`.
      - Dentro de `DB::transaction()`: Compara cantidades anteriores vs. nuevas, guarda el log en `auditorias_reconteo`, actualiza `cortes_inventario`, recalcula stock de turno y auto-consume el permiso (`permite_reconteo = false`).
- **Ticket 14.2 (Frontend Admin APK: Diálogo y Acción de Autorización)**:
  - En `dashboard_admin_screen.dart` (y detalle de turnos en vivo):
    - Botón `[ 🔓 Habilitar Reconteo ]` en la tarjeta de turno.
    - Modal interactivo con selección de tipo de corte ('Apertura' o 'Cierre') y campo de texto para motivo.
    - Invoca `POST /turnos/{id}/autorizar-reconteo` y notifica éxito con SnackBar.
- **Ticket 14.3 (Frontend Barman APK: Banner de Alerta, Memoria Precargada y Re-Sellado)**:
  - En `dashboard_barman_screen.dart`:
    - Evaluar `turno.permite_reconteo == true` y desplegar Card dorada en AppBar/Body: *"⚠️ Reconteo Autorizado por Administración. Toca para corregir"*.
  - En `corte_inventario_screen.dart`:
    - Al abrir en modo reconteo, consultar automáticamente `GET /turnos/{id}/corte-inicial` (o cierre) y precargar el mapa de cantidades (`_cantidadesDigitadas`) con el 100% de los valores anteriores.
    - El barman visualiza sus cantidades previas intactas, edita únicamente el ítem erróneo y presiona `Confirmar Corrección de Conteo`.
    - Envía a `POST /turnos/{id}/aplicar-reconteo`. Al completar, regenera el PDF con rótulo de "Versión Corregida con Autorización" y regresa al Dashboard con el turno re-sellado.

---

## Complexity Tracking

| Decisión de Diseño | Justificación Técnica | Alternativa Más Simple Rechazada y Motivo |
| :--- | :--- | :--- |
| **Arquitectura Hexagonal en Laravel** | Aísla las complejas reglas de balance de masa, auditoría y comisiones del framework y de la base de datos, garantizando cumplimiento constitucional estricto y pruebas unitarias puras. | *MVC tradicional de Laravel*: Rechazado por acoplar la lógica financiera al ORM Eloquent y dificultar la portabilidad y auditoría. |
| **Arquitectura Local-First en Flutter** | Los bares presentan alta intermitencia o pérdida de internet; la app no puede bloquear la atención en barra. | *Solo llamadas HTTP en línea*: Rechazado porque cualquier caída de Wi-Fi paraliza el servicio en barra y genera pérdida de registros. |
| **`DECIMAL(8,2)` para Fracciones** | Garantiza exactitud matemática en las fracciones de botellas (0.25, 0.50, 0.75). | *FLOAT/DOUBLE*: Rechazado por errores de redondeo de punto flotante en cálculos de balance. |
| **Desglose Automático de Combos** | Permite que el Administrador transcriba el Ticket Z exactamente como se emitió sin cálculos manuales propensos a error. | *Carga manual desglosada*: Rechazado por trasladar trabajo contable manual al auditor. |
