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

---

## Complexity Tracking

| Decisión de Diseño | Justificación Técnica | Alternativa Más Simple Rechazada y Motivo |
| :--- | :--- | :--- |
| **Arquitectura Hexagonal en Laravel** | Aísla las complejas reglas de balance de masa, auditoría y comisiones del framework y de la base de datos, garantizando cumplimiento constitucional estricto y pruebas unitarias puras. | *MVC tradicional de Laravel*: Rechazado por acoplar la lógica financiera al ORM Eloquent y dificultar la portabilidad y auditoría. |
| **Arquitectura Local-First en Flutter** | Los bares presentan alta intermitencia o pérdida de internet; la app no puede bloquear la atención en barra. | *Solo llamadas HTTP en línea*: Rechazado porque cualquier caída de Wi-Fi paraliza el servicio en barra y genera pérdida de registros. |
| **`DECIMAL(8,2)` para Fracciones** | Garantiza exactitud matemática en las fracciones de botellas (0.25, 0.50, 0.75). | *FLOAT/DOUBLE*: Rechazado por errores de redondeo de punto flotante en cálculos de balance. |
| **Desglose Automático de Combos** | Permite que el Administrador transcriba el Ticket Z exactamente como se emitió sin cálculos manuales propensos a error. | *Carga manual desglosada*: Rechazado por trasladar trabajo contable manual al auditor. |
