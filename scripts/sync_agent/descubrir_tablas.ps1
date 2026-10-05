[CmdletBinding()]
param(
    [string]$ConfigPath = "$PSScriptRoot\config.json"
)

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not (Test-Path $ConfigPath)) {
    $ConfigPath = Join-Path $ScriptDir "config.json"
}

$SqlServer = "localhost\SQLEXPRESS"
$Database = "ControlConsumoCasa22"
$DbUser = "sa"
$DbPassword = "toptech"

if (Test-Path $ConfigPath) {
    try {
        $c = Get-Content -Path $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
        if ($c.sql_server) { $SqlServer = $c.sql_server }
        if ($c.database) { $Database = $c.database }
        if ($c.db_user) { $DbUser = $c.db_user }
        if ($c.db_password) { $DbPassword = $c.db_password }
    } catch {}
}

$connStr = "Server=$SqlServer;Database=$Database;User Id=$DbUser;Password=$DbPassword;TrustServerCertificate=True;"
$outFile = Join-Path $ScriptDir "estructura_pos.txt"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   DESCUBRIDOR DE ESTRUCTURA RESTOTECH ($Database)        " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

$output = @()
$output += "=== TABLAS EN $Database ==="

try {
    $conn = New-Object System.Data.SqlClient.SqlConnection($connStr)
    $conn.Open()
    Write-Host "[OK] Conectado exitosamente a SQL Server!" -ForegroundColor Green

    $cmd = $conn.CreateCommand()
    $cmd.CommandText = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"
    $reader = $cmd.ExecuteReader()
    $tablas = @()
    while ($reader.Read()) {
        $t = $reader["TABLE_NAME"].ToString()
        $tablas += $t
        $output += "Tabla: $t"
    }
    $reader.Close()

    $output += "`n=== COLUMNAS DETALLADAS ==="
    foreach ($t in $tablas) {
        $cmdCol = $conn.CreateCommand()
        $cmdCol.CommandText = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$t' ORDER BY ORDINAL_POSITION"
        $rCol = $cmdCol.ExecuteReader()
        $cols = @()
        while ($rCol.Read()) {
            $cols += "$($rCol['COLUMN_NAME']) ($($rCol['DATA_TYPE']))"
        }
        $rCol.Close()

        $linea = "[$t]: " + ($cols -join ", ")
        $output += $linea
        Write-Host $linea -ForegroundColor Yellow
    }

    $conn.Close()
    $output | Out-File -FilePath $outFile -Encoding utf8
    Write-Host "`n[EXITO] Estructura guardada en: $outFile" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] $_" -ForegroundColor Red
}

Write-Host "`nPresione cualquier tecla para continuar..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
