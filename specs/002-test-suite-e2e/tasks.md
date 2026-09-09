# Tasks: Suite Automatizada de Pruebas de Integración End-to-End para Backend y Frontend

**Branch**: `002-test-suite-e2e`  
**Input**: [spec.md](file:///c:/xampp/htdocs/next22/specs/002-test-suite-e2e/spec.md), [plan.md](file:///c:/xampp/htdocs/next22/specs/002-test-suite-e2e/plan.md), [quickstart.md](file:///c:/xampp/htdocs/next22/specs/002-test-suite-e2e/quickstart.md)

---

## Phase 1: Setup & Environment Configuration (Shared Infrastructure)

**Purpose**: Preparar la configuración aislada de SQLite en memoria para el backend y las dependencias de testing para Flutter.

- [x] T001 Configurar `backend/phpunit.xml` habilitando SQLite en memoria (`<env name="DB_CONNECTION" value="sqlite"/>` y `<env name="DB_DATABASE" value=":memory:"/>`) con drivers de sesión/cola en array/sync en backend/phpunit.xml
- [x] T002 [P] Verificar y configurar `integration_test` como dev_dependency en `frontend/puntofrio_app/pubspec.yaml`
- [x] T003 [P] Crear estructura de carpetas de tests (`frontend/puntofrio_app/test/services/`, `frontend/puntofrio_app/test/widgets/`, `frontend/puntofrio_app/integration_test/`, `scripts/`)

---

## Phase 2: User Story 1 - Suite E2E de API Backend (Laravel / PHPUnit) (Priority: P1) 🎯 MVP

**Goal**: Validar encadenadamente el ciclo operativo completo de la API en base de datos en memoria (Login, Apertura con Conteo Real, Compras con Proveedor, Transformación con Comisión Neta, Traspasos, Cierre Inmutable y Conciliación de Ticket Z).  
**Independent Test**: Ejecutar `php artisan test tests/Feature/CicloCompletoTurnoTest.php` desde `backend/` y constatar 100% de aserciones aprobadas sin requerir MySQL activo.

- [x] T004 [US1] Crear la clase de test base con seeder y helpers de prueba en backend/tests/Feature/CicloCompletoTurnoTest.php
- [x] T005 [US1] Implementar caso de prueba de Login con PIN de Barman (`POST /api/v1/auth/login-pin`) con validación de token Sanctum en backend/tests/Feature/CicloCompletoTurnoTest.php
- [x] T006 [US1] Implementar caso de prueba de Apertura de Turno (`POST /api/v1/turnos/abrir`) con conteo físico fraccionado y asertar que `GET /api/v1/turnos/{id}/corte-inicial` retorne las cantidades reales no nulas en backend/tests/Feature/CicloCompletoTurnoTest.php
- [x] T007 [US1] Implementar caso de prueba de Recepción de Compras (`POST /api/v1/inventario/compras`) y validar incremento de stock disponible en backend/tests/Feature/CicloCompletoTurnoTest.php
- [x] T008 [US1] Implementar caso de prueba de Transformación de Relleno (`POST /api/v1/transformaciones`) con botellas rotas y certificar el cálculo neto de comisión según la Constitución en backend/tests/Feature/CicloCompletoTurnoTest.php
- [x] T009 [US1] Implementar caso de prueba de Despacho y Recepción de Traspaso (`POST /api/v1/traspasos` y `POST /api/v1/traspasos/{id}/recibir`) en backend/tests/Feature/CicloCompletoTurnoTest.php
- [x] T010 [US1] Implementar caso de prueba de Corte de Cierre de Turno (`POST /api/v1/turnos/{id}/cerrar`) y certificar inmutabilidad en backend/tests/Feature/CicloCompletoTurnoTest.php
- [x] T011 [US1] Implementar caso de prueba de Conciliación de Ticket Z (`POST /api/v1/auditoria/conciliar`) evaluando fórmulas constitucionales de faltantes/sobrantes en backend/tests/Feature/CicloCompletoTurnoTest.php

---

## Phase 3: User Story 2 - Suite de Pruebas Móvil E2E (Flutter) (Priority: P1)

**Goal**: Verificar la lógica de generación del PDF de conteo, selectores de fracciones de botella y flujo automatizado de usuario en la app móvil.  
**Independent Test**: Ejecutar `flutter test` y `flutter test integration_test/app_flujo_completo_test.dart` en `frontend/puntofrio_app/`.

- [x] T012 [P] [US2] Implementar prueba unitaria de formateo y precedencia numérica en frontend/puntofrio_app/test/services/conteo_pdf_service_test.dart comprobando que nunca se enmascaren con ceros las cantidades físicas ingresadas
- [x] T013 [P] [US2] Implementar prueba de widgets para `BottleFractionSelector` en frontend/puntofrio_app/test/widgets/bottle_fraction_selector_test.dart validando emisión exacta de fracciones decimales (`0.25`, `0.50`, `0.75`, `1.00`)
- [x] T014 [US2] Implementar test de integración en emulador/dispositivo en frontend/puntofrio_app/integration_test/app_flujo_completo_test.dart automatizando PIN login, navegación a corte de apertura y verificación de diálogo de éxito

---

## Phase 4: User Story 3 - Runner Unificado y Reporte de Estado Multi-Plataforma (Priority: P2)

**Goal**: Proveer un script PowerShell único para orquestar la suite de pruebas completa y reportar el estado de salud integral del sistema.  
**Independent Test**: Ejecutar `.\scripts\run_all_tests.ps1` desde la raíz y validar que ejecute backend, análisis estático y frontend emitiendo resumen PASS/FAIL.

- [x] T015 [US3] Crear script orquestador scripts/run_all_tests.ps1 con captura de código de salida (`$LASTEXITCODE`), formato con colores en consola y temporizador de ejecución
- [x] T016 [US3] Agregar soporte de flags en scripts/run_all_tests.ps1 (`-BackendOnly`, `-FrontendOnly`, `-WithIntegration`) para agilidad en desarrollo
- [x] T017 [US3] Ejecutar la suite completa mediante el orquestador unificado y registrar los resultados de validación en specs/002-test-suite-e2e/quickstart.md

---

## Dependencies & Execution Order

### Phase Dependencies
- **Phase 1 (Setup)**: Debe completarse primero para habilitar SQLite en memoria y dependencias de testing.
- **Phase 2 (US1 Backend)** y **Phase 3 (US2 Frontend)**: Pueden desarrollarse de forma paralela e independiente una vez concluida la Phase 1.
- **Phase 4 (US3 Runner)**: Requiere que las suites de Phase 2 y Phase 3 estén disponibles para orquestarlas y emitir el reporte consolidado.

---

## Parallel Opportunities

```bash
# Tareas Paralelas Frontend y Backend:
Task T002: "Configuración de integration_test en pubspec.yaml"
Task T003: "Estructura de carpetas de tests en frontend y scripts"
Task T012: "Prueba unitaria de conteo_pdf_service_test.dart"
Task T013: "Prueba de widgets de bottle_fraction_selector_test.dart"
```

---

## Notes
- Todas las pruebas de backend utilizan `RefreshDatabase` sobre SQLite `:memory:`, imposibilitando la alteración de datos de producción.
- Las tareas siguen el formato estándar `- [ ] [TaskID] [P?] [Story?] Descripción con ruta de archivo`.
