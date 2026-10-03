@echo off
chcp 65001 >nul
:: ============================================================================
:: CASA 22 - Instalador Automático del Agente de Sincronización POS RestoTech
:: Configura una Tarea Programada en Windows para ejecutarse en segundo plano
:: ============================================================================

echo ============================================================================
echo   CASA 22 - INSTALADOR DE AGENTE DE SINCRONIZACION POS
echo ============================================================================
echo.

set SCRIPT_DIR=%~dp0
set PS_SCRIPT=%SCRIPT_DIR%sync_casa22.ps1
set TASK_NAME=Casa22_POS_Sync

if not exist "%PS_SCRIPT%" (
    echo [ERROR] No se encontro el archivo sync_casa22.ps1 en %SCRIPT_DIR%
    pause
    exit /b 1
)

echo [INFO] Directorio del Agente: %SCRIPT_DIR%
echo [INFO] Script de sincronizacion: %PS_SCRIPT%
echo.

:: 1. Probar conectividad y ejecución inicial inmediata
echo [1/3] Probando ejecucion inicial y conexion con la base de datos local...
powershell -ExecutionPolicy Bypass -NoProfile -File "%PS_SCRIPT%"
echo.

:: 2. Eliminar tarea programada anterior si existiese
echo [2/3] Verificando si existe tarea programada anterior...
schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [INFO] Eliminando tarea existente para actualizarla...
    schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1
)

:: 3. Crear nueva tarea programada que corre cada 5 minutos
echo [3/3] Registrando tarea programada "%TASK_NAME%" cada 5 minutos...
schtasks /create /tn "%TASK_NAME%" /tr "powershell.exe -ExecutionPolicy Bypass -WindowStyle Hidden -NoProfile -File \"%PS_SCRIPT%\"" /sc minute /mo 5 /ru "%USERNAME%" /f

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ============================================================================
    echo [EXITO] Agente de Casa22 instalado correctamente!
    echo Sincronizara ventas desde RestoTech hacia el cloud cada 5 minutos.
    echo No requiere ninguna intervencion adicional de la cajera.
    echo.
    echo Registro de actividad disponible en: %SCRIPT_DIR%sync_log.txt
    echo ============================================================================
) else (
    echo.
    echo [AVISO] Se intento crear la tarea como usuario regular. Si falló por permisos,
    echo haga clic derecho en este archivo 'instalar.bat' y seleccione 'Ejecutar como Administrador'.
)

echo.
pause
