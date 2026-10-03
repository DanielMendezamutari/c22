@echo off
chcp 65001 >nul
echo ============================================================================
echo   CASA 22 - DESINSTALAR TAREA DE SINCRONIZACION
echo ============================================================================
echo.
schtasks /delete /tn "Casa22_POS_Sync" /f
echo.
echo Tarea programada eliminada exitosamente.
pause
