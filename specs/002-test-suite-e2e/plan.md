# Implementation Plan: Suite Automatizada de Pruebas de Integración End-to-End para Backend y Frontend

**Branch**: `002-test-suite-e2e` | **Date**: 2026-09-08 | **Spec**: [spec.md](file:///c:/xampp/htdocs/next22/specs/002-test-suite-e2e/spec.md)

**Input**: Feature specification from `/specs/002-test-suite-e2e/spec.md`

---

## Summary

Diseñar e implementar una suite automatizada integral de pruebas End-to-End (E2E) que certifique la robustez matemática, constitucional y operativa de toda la plataforma Punto Frío (Laravel 11 + Flutter 3.19).

La suite abarca:
1. **Backend E2E (PHPUnit / Laravel)**: Configuración con base de datos aislada en memoria (SQLite `:memory:`), ejecutando el ciclo de vida completo de un turno: Autenticación PIN → Apertura de Turno con Conteo Real → Recepción de Compras con Foto → Transformación con Comisión Neta → Traspaso Inter-Sucursales → Corte de Cierre Inmutable → Auditoría de Ticket Z con semáforo de faltantes/sobrantes.
2. **Frontend E2E & Widget Tests (Flutter)**: Verificación de la precedencia y exactitud del generador de PDF vectorial ([ConteoPdfService](file:///c:/xampp/htdocs/next22/frontend/puntofrio_app/lib/presentation/screens/turnos/conteo_pdf_service.dart)), tests de componentes de [BottleFractionSelector](file:///c:/xampp/htdocs/next22/frontend/puntofrio_app/lib/presentation/widgets/bottle_fraction_selector.dart) y test de integración móvil de flujo completo con `package:integration_test`.
3. **Orquestador Unificado**: Script PowerShell multiplataforma `scripts/run_all_tests.ps1` que encadena la validación de backend, análisis estático de frontend y ejecución de tests de interfaz, emitiendo un reporte visual con código de salida estricto.

---

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 11), Dart 3.3+ (Flutter 3.19+)  
**Primary Dependencies**: 
- *Backend*: PHPUnit 11, Orchestra Testbench / Laravel Testing Suite, `RefreshDatabase` trait, SQLite PDO extension.
- *Frontend*: `integration_test` (Flutter SDK), `flutter_test`, `flutter_riverpod`, `mockito` / mocks de red.  
**Storage**: SQLite en memoria (`:memory:`) para aislamiento total sin dependencias de MySQL en local, SQLite local móvil para cola offline.  
**Testing Frameworks**: PHPUnit (Backend), Flutter Test & Integration Test (Frontend).  
**Target Platform**: Windows 11 (estación de desarrollo local), Linux (servidor de integración continua y hosting cPanel), Emulador/Dispositivo Android/iOS.  
**Project Type**: Multi-project testing harness (Backend API + Mobile App + CLI Runner).  
**Performance Goals**: 
- Suite completa de backend en < 20 segundos.
- Suite unitaria y de widgets frontend en < 15 segundos.
- Orquestador global en < 45 segundos.  
**Constraints**: 
- No afectar bajo ningún motivo la base de datos operativa `puntofrio_inventario`.
- 100% independiente del software de caja POS.
- Compatibilidad para ejecutarse con un solo clic o comando en Windows PowerShell.  

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio Constitucional | Estado | Evidencia en el Diseño |
| :--- | :---: | :--- |
| **I. Descubrimiento Empírico de Rendimiento** | **PASS** | El test de integración `CicloCompletoTurnoTest` prueba la transformación real (latas consumidas vs botellas producidas) calculando coeficientes sin equivalencias teóricas rígidas. |
| **II. Justicia Financiera en Comisiones** | **PASS** | Los tests validan que las comisiones se paguen únicamente sobre unidades producidas netas (`unidades_producidas - roturas`) y asienta 0 Bs en productos no transformados. |
| **III. Responsabilidad Estricta por Turno (12h)** | **PASS** | Valida que los cortes de apertura y cierre persistan exactamente las cantidades contadas y certifique la inmutabilidad tras el cierre. |
| **IV. Desacoplamiento del POS** | **PASS** | La suite simula la carga del Ticket Z manual y concilia matemáticamente contra las salidas físicas sin integraciones directas a cajas registradoras. |
| **V. Auditoría Centralizada Multi-Sucursal** | **PASS** | Valida el ciclo de despacho y recepción de traspasos entre `Casa22` y otra sucursal con trazabilidad de mermas en tránsito. |

---

## Project Structure

### Documentation (this feature)

```text
specs/002-test-suite-e2e/
├── spec.md              # Requerimientos y Criterios de Aceptación
├── plan.md              # Este archivo (Diseño de arquitectura de testing)
├── quickstart.md        # Guía de ejecución paso a paso de cada suite
└── checklists/
    └── requirements.md  # Validación de calidad de la especificación
```

### Source Code

```text
backend/
├── phpunit.xml                                    # Configuración con SQLite en memoria activa
└── tests/
    ├── Feature/
    │   ├── AuditoriaTest.php                      # Test existente de auditoría
    │   └── CicloCompletoTurnoTest.php             # [NEW] Test E2E de ciclo de vida completo
    └── Unit/
        └── FraccionLicorTest.php                  # Test de Value Object FraccionLicor

frontend/puntofrio_app/
├── pubspec.yaml                                   # Inclusión de integration_test dev_dependency
├── test/
    ├── widgets/
    │   └── bottle_fraction_selector_test.dart     # [NEW] Test unitario de selectores de botellas
    └── services/
        └── conteo_pdf_service_test.dart           # [NEW] Test de resolución y precedencia numérica en PDF
└── integration_test/
    └── app_flujo_completo_test.dart               # [NEW] Test de integración de interfaz con Flutter Driver

scripts/
└── run_all_tests.ps1                              # [NEW] Orquestador PowerShell unificado
```

---

## Fases de Implementación Técnica

### FASE 1: Configuración de Entornos de Prueba Aislados
- **Backend (`phpunit.xml`)**:
  - Descomentar `<env name="DB_CONNECTION" value="sqlite"/>` y `<env name="DB_DATABASE" value=":memory:"/>`.
  - Configurar claves de aplicación de test y drivers de sesión/cola en memoria (`sync`/`array`).
- **Frontend (`pubspec.yaml`)**:
  - Asegurar la sección `dev_dependencies` con `integration_test` vinculada al SDK de Flutter.

### FASE 2: Suite Feature E2E de Backend (`CicloCompletoTurnoTest.php`)
- Crear `backend/tests/Feature/CicloCompletoTurnoTest.php` utilizando `use RefreshDatabase`.
- Implementar el método de test encadenado `test_flujo_completo_operativo_de_turno()`:
  1. Sembrar sucursales (`Casa22`, `Madan`), usuarios (`Barman`, `Admin`), recetas de transformación y catálogo de productos.
  2. Autenticar con PIN (`POST /api/v1/auth/login-pin`) obteniendo token Sanctum.
  3. Aperturar turno (`POST /api/v1/turnos/abrir`) enviando conteo inicial con fracciones (ej. 24 Moema, 10 Corona, 2.5 Fernet).
  4. Consultar `GET /api/v1/turnos/{id}/corte-inicial` y asertar que `cantidad_inicial` coincida con las cifras ingresadas.
  5. Registrar recepción de compra (`POST /api/v1/inventario/compras`) e incremento de stock.
  6. Registrar transformación de relleno (`POST /api/v1/transformaciones`) con botellas rotas y validar comisión neta generada.
  7. Registrar baja por rotura accidental (`POST /api/v1/bajas-roturas`).
  8. Cerrar turno (`POST /api/v1/turnos/{id}/cerrar`) y certificar inmutabilidad.
  9. Conciliar Ticket Z (`POST /api/v1/auditoria/conciliar`) evaluando faltante o sobrante según balance constitucional.

### FASE 3: Suite de Pruebas de Frontend (Unit & Widgets)
- Crear `frontend/puntofrio_app/test/services/conteo_pdf_service_test.dart`:
  - Validar que al procesar ítems con `cantidad_inicial: 0.0` y `cantidad: 24.0`, el generador de PDF compute `cantInicial = 24.0` y `totalDisponible = 24.0`.
  - Validar cálculo correcto de totales consolidados al pie.
- Crear `frontend/puntofrio_app/test/widgets/bottle_fraction_selector_test.dart`:
  - Renderizar [BottleFractionSelector](file:///c:/xampp/htdocs/next22/frontend/puntofrio_app/lib/presentation/widgets/bottle_fraction_selector.dart).
  - Interactuar con botones de cuarto (`1/4`, `1/2`, `3/4`, `Llena`) y verificar que se emita el valor decimal exacto.

### FASE 4: Suite de Integración Móvil E2E (`integration_test/app_flujo_completo_test.dart`)
- Implementar `app_flujo_completo_test.dart`:
  - `IntegrationTestWidgetsFlutterBinding.ensureInitialized()`.
  - Arrancar `main()`.
  - Buscar botones del teclado numérico y presionar PIN `1`, `2`, `3`, `4`.
  - Verificar visualización de `DashboardBarmanScreen`.
  - Simular toque en "INICIAR TURNO / CORTE DE APERTURA".
  - Verificar presencia de productos y botón de confirmación.

### FASE 5: Orquestador Unificado (`scripts/run_all_tests.ps1`)
- Script en PowerShell que:
  - Ejecuta `php artisan test` en `backend/`.
  - Ejecuta `flutter analyze` en `frontend/puntofrio_app/`.
  - Ejecuta `flutter test` en `frontend/puntofrio_app/`.
  - Imprime un panel formateado en consola con el estado de cada suite y tiempo transcurrido.

---

## Complexity Tracking

| Decisión de Diseño | Justificación Técnica | Alternativa Más Simple Rechazada y Motivo |
| :--- | :--- | :--- |
| **SQLite `:memory:` para PHPUnit** | Permite correr los tests en cualquier máquina o pipeline sin necesidad de instalar o arrancar MySQL. Pruebas ultrarrápidas (< 5s). | *MySQL de testing*: Rechazado porque requiere servidor MySQL activo y puede colisionar con esquemas existentes. |
| **Separación de Widget Tests e Integration Tests** | Los widget tests se ejecutan en milisegundos sin emulador, mientras que integration_test valida la experiencia visual en el dispositivo. | *Solo pruebas manuales*: Rechazado por riesgo de regresión inadvertida en pantallas críticas. |
| **Script PowerShell nativo** | Los desarrolladores en Windows pueden correr toda la suite con un solo comando sin instalar herramientas adicionales como Makefile o Bash. | *Scripts bash exclusivos*: Rechazado por fricción de ejecución en terminales Windows estándar. |
