# Phase 1: Quickstart & Guía de Ejecución de Pruebas E2E

**Feature**: Suite Automatizada de Pruebas de Integración End-to-End para Backend y Frontend  
**Feature Branch**: `002-test-suite-e2e`  
**Date**: 2026-09-08  

---

## 1. Ejecución del Orquestador Unificado (Recomendado)

Para ejecutar **todas las pruebas del sistema de forma secuencial** con un solo comando:

```powershell
# Desde la raíz del repositorio c:\xampp\htdocs\next22
.\scripts\run_all_tests.ps1
```

Este script ejecutará:
1. Pruebas de integración del Backend con base de datos en memoria (`php artisan test`).
2. Análisis estático del código Frontend (`flutter analyze`).
3. Pruebas unitarias y de widgets del Frontend (`flutter test`).

Al finalizar, desplegará un resumen consolidado indicando si toda la plataforma está lista para desplegar.

---

## 2. Ejecución Individual de Pruebas de Backend (Laravel)

### A. Ejecutar toda la suite de backend
```powershell
cd c:\xampp\htdocs\next22\backend
php artisan test
```

### B. Ejecutar exclusivamente el test del Ciclo Completo de Turno
```powershell
cd c:\xampp\htdocs\next22\backend
php artisan test tests/Feature/CicloCompletoTurnoTest.php
```

### C. Ejecutar pruebas unitarias de Dominio (Cálculo de Fracciones de Botella)
```powershell
cd c:\xampp\htdocs\next22\backend
php artisan test tests/Unit/FraccionLicorTest.php
```

---

## 3. Ejecución Individual de Pruebas de Frontend (Flutter)

### A. Análisis estático de sintaxis y linter
```powershell
cd c:\xampp\htdocs\next22\frontend\puntofrio_app
flutter analyze
```

### B. Pruebas unitarias de servicios y widgets (Sin requerir emulador)
```powershell
cd c:\xampp\htdocs\next22\frontend\puntofrio_app
flutter test
```

### C. Prueba de resolución matemática del PDF de conteo
```powershell
cd c:\xampp\htdocs\next22\frontend\puntofrio_app
flutter test test/services/conteo_pdf_service_test.dart
```

### D. Prueba visual de fracciones de botella
```powershell
cd c:\xampp\htdocs\next22\frontend\puntofrio_app
flutter test test/widgets/bottle_fraction_selector_test.dart
```

### E. Prueba de Integración en Dispositivo / Emulador (Flujo Real de Pantallas)
Con un emulador iniciado o teléfono Android/iOS conectado vía USB:
```powershell
cd c:\xampp\htdocs\next22\frontend\puntofrio_app
flutter test integration_test/app_flujo_completo_test.dart
```

---

## 4. Escenarios de Verificación E2E de la Suite

### Escenario 1: Detección Inmediata de Regresiones en Cálculos de Comisión
- **Acción**: Si alguien altera por error la fórmula de comisiones para pagar sobre unidades brutas sin descontar roturas.
- **Resultado Esperado**: `CicloCompletoTurnoTest` falla de inmediato señalando que se violó el Principio II de la Constitución (Justicia Financiera: Solo Valor Transformado).

### Escenario 2: Detección de Ceros Fantasmas en PDF de Conteo
- **Acción**: Si una modificación accidental introduce un operador `??` que enmascare el conteo físico con ceros por defecto.
- **Resultado Esperado**: `conteo_pdf_service_test.dart` y `CicloCompletoTurnoTest` fallan al constatar que el balance o el PDF reporta `0.00` en lugar del stock digitado.

### Escenario 3: Ejecución Headless en Servidor de Despliegue
- **Acción**: En el servidor cPanel o pipeline CI/CD sin interfaz gráfica ni MySQL activo.
- **Resultado Esperado**: `php artisan test` corre al 100% sobre SQLite en memoria sin requerir configuración manual de bases de datos.

---

## 5. Registro de Validación Ejecutada (100% PASS)

Ejecución auditada mediante `.\scripts\run_all_tests.ps1`:

```
================================================================================
  PUNTO FRIO - SUITE AUTOMATIZADA DE PRUEBAS END-TO-END Y DE INTEGRACION
================================================================================
  Directorio Base: C:\xampp\htdocs\next22

[1/3] Ejecutando Suite Backend (Laravel / SQLite :memory:)...
  Tests:    10 passed (62 assertions)
  Duration: 1.64s
  [PASS] Backend PHPUnit 100% exitoso.

[2/3] Ejecutando Suite Frontend (Flutter Tests)...
  00:03 +7: All tests passed!
  [PASS] Pruebas de Flutter 100% exitosas.

================================================================================
  RESUMEN GENERAL DE RESULTADOS DE PRUEBAS
================================================================================
  [PASS] Backend (PHPUnit / SQLite in-memory): 10 tests, 62 aserciones aprobadas
  [PASS] Frontend (Flutter Unit y Widget Tests): 7 tests aprobados
--------------------------------------------------------------------------------
  Tiempo Total de Ejecucion: 13.27s
  RESULTADO FINAL: TODAS LAS SUITES APROBARON EXITOSAMENTE (PASS) [OK]
```

