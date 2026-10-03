@echo off
chcp 65001 >nul
echo ============================================================================
echo   CASA 22 - EJECUTAR SINCRONIZACION MANUAL INMEDIATA
echo ============================================================================
echo.
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0sync_casa22.ps1"
echo.
echo Presione cualquier tecla para cerrar...
pause >nul
