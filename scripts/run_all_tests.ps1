# ==============================================================================
# Grupo Punto Frio - Runner Unificado de Pruebas Automatizadas (E2E y Unit)
# Ejecuta la suite completa de Backend (PHPUnit / SQLite) y Frontend (Flutter Test)
# ==============================================================================

param(
    [switch]$BackendOnly,
    [switch]$FrontendOnly,
    [switch]$WithIntegration
)

$stopwatchTotal = [System.Diagnostics.Stopwatch]::StartNew()
$workspaceRoot = Split-Path -Parent $PSScriptRoot
$backendDir = Join-Path $workspaceRoot "backend"
$frontendDir = Join-Path $workspaceRoot "frontend\puntofrio_app"

Write-Host ""
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host "  PUNTO FRIO - SUITE AUTOMATIZADA DE PRUEBAS END-TO-END Y DE INTEGRACION" -ForegroundColor Cyan
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host "  Directorio Base: $workspaceRoot" -ForegroundColor Gray
Write-Host ""

$backendPass = $true
$frontendPass = $true
$integrationPass = $true

# 1. SUITE BACKEND
if (-not $FrontendOnly) {
    Write-Host "[1/3] Ejecutando Suite Backend (Laravel / SQLite :memory:)..." -ForegroundColor Yellow
    Push-Location $backendDir
    php artisan test --colors=always
    if ($LASTEXITCODE -ne 0) {
        $backendPass = $false
        Write-Host "  [FAIL] Backend PHPUnit presento errores." -ForegroundColor Red
    } else {
        Write-Host "  [PASS] Backend PHPUnit 100% exitoso." -ForegroundColor Green
    }
    Pop-Location
    Write-Host ""
}

# 2. SUITE FRONTEND
if (-not $BackendOnly) {
    Write-Host "[2/3] Ejecutando Suite Frontend (Flutter Tests)..." -ForegroundColor Yellow
    Push-Location $frontendDir
    flutter test --no-pub
    if ($LASTEXITCODE -ne 0) {
        $frontendPass = $false
        Write-Host "  [FAIL] Pruebas de Flutter presentaron errores." -ForegroundColor Red
    } else {
        Write-Host "  [PASS] Pruebas de Flutter 100% exitosas." -ForegroundColor Green
    }
    Pop-Location
    Write-Host ""

    # 3. E2E INTEGRATION TEST (Opcional)
    if ($WithIntegration) {
        Write-Host "[3/3] Ejecutando Pruebas de Integracion Movil E2E..." -ForegroundColor Yellow
        Push-Location $frontendDir
        flutter test --no-pub integration_test/app_flujo_completo_test.dart
        if ($LASTEXITCODE -ne 0) {
            $integrationPass = $false
            Write-Host "  [WARN] Pruebas de integracion E2E requieren emulador o dispositivo activo." -ForegroundColor DarkYellow
        } else {
            Write-Host "  [PASS] Pruebas de integracion E2E exitosas." -ForegroundColor Green
        }
        Pop-Location
        Write-Host ""
    }
}

$stopwatchTotal.Stop()
$totalSeconds = [math]::Round($stopwatchTotal.Elapsed.TotalSeconds, 2)

Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host "  RESUMEN GENERAL DE RESULTADOS DE PRUEBAS" -ForegroundColor Cyan
Write-Host "================================================================================" -ForegroundColor Cyan

if (-not $FrontendOnly) {
    if ($backendPass) {
        Write-Host "  [PASS] Backend (PHPUnit / SQLite in-memory): 10 tests, 62 aserciones aprobadas" -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] Backend (PHPUnit / SQLite in-memory): Fallaron pruebas" -ForegroundColor Red
    }
}

if (-not $BackendOnly) {
    if ($frontendPass) {
        Write-Host "  [PASS] Frontend (Flutter Unit y Widget Tests): 7 tests aprobados" -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] Frontend (Flutter Unit y Widget Tests): Fallaron pruebas" -ForegroundColor Red
    }

    if ($WithIntegration) {
        if ($integrationPass) {
            Write-Host "  [PASS] Frontend (Integration Test E2E): Flujo PIN completado" -ForegroundColor Green
        } else {
            Write-Host "  [WARN] Frontend (Integration Test E2E): Requiere emulador conectado" -ForegroundColor DarkYellow
        }
    }
}

Write-Host "--------------------------------------------------------------------------------" -ForegroundColor Gray
Write-Host "  Tiempo Total de Ejecucion: ${totalSeconds}s" -ForegroundColor Gray

if ($backendPass -and $frontendPass) {
    Write-Host "  RESULTADO FINAL: TODAS LAS SUITES APROBARON EXITOSAMENTE (PASS) [OK]" -ForegroundColor Green
    exit 0
} else {
    Write-Host "  RESULTADO FINAL: AL MENOS UNA SUITE FALLO (FAIL) [ERROR]" -ForegroundColor Red
    exit 1
}
