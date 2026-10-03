<#
.SYNOPSIS
    Casa 22 - Agente Nativo de Sincronización POS RestoTech (SQL Server -> Cloud)
    No requiere instalación de Python ni librerías externas en la PC de caja.
    Compatible con Windows 10, Windows 11 y Windows Server (PowerShell 5.1+).
#>

[CmdletBinding()]
param(
    [string]$ConfigPath = "$PSScriptRoot\config.json",
    [switch]$Loop,
    [int]$IntervalSeconds = 300
)

# Establecer protocolo TLS 1.2 para conexiones HTTPS seguras con Laravel
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not (Test-Path $ConfigPath)) {
    $ConfigPath = Join-Path $ScriptDir "config.json"
}

$LogFile = Join-Path $ScriptDir "sync_log.txt"
$StateFile = Join-Path $ScriptDir "last_sync.json"
$BufferFile = Join-Path $ScriptDir "offline_buffer.json"

function Write-SyncLog {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    $logLine = "[$timestamp] [$Level] $Message"
    Write-Output $logLine
    try {
        Add-Content -Path $LogFile -Value $logLine -ErrorAction SilentlyContinue
    } catch {}
}

# Cargar Configuración
if (Test-Path $ConfigPath) {
    try {
        $configRaw = Get-Content -Path $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
        $ApiUrl = $configRaw.api_url
        $BranchToken = $configRaw.branch_token
        $SqlServer = $configRaw.sql_server
        $Database = $configRaw.database
        $DbUser = $configRaw.db_user
        $DbPassword = $configRaw.db_password
        $BatchSize = if ($configRaw.batch_size) { [int]$configRaw.batch_size } else { 200 }
        if ($configRaw.poll_interval_seconds) { $IntervalSeconds = [int]$configRaw.poll_interval_seconds }
    } catch {
        Write-SyncLog "Error leyendo config.json. Usando valores por defecto." "WARN"
    }
}

if (-not $ApiUrl) { $ApiUrl = "https://c22.ribersoft.com/api/v1/sync/pos-transacciones" }
if (-not $BranchToken) { $BranchToken = "C22-SANTA-CRUZ-SECRET-KEY-2026" }
if (-not $SqlServer) { $SqlServer = "localhost\SQLEXPRESS" }
if (-not $Database) { $Database = "ControlConsumoCasa22" }
if (-not $DbUser) { $DbUser = "sa" }
if (-not $DbPassword) { $DbPassword = "toptech" }
if (-not $BatchSize) { $BatchSize = 200 }

function Ejecutar-Sincronizacion {
    Write-SyncLog "Iniciando ciclo de sincronización..." "INFO"

    # 1. Leer último ID sincronizado
    $lastId = 0
    if (Test-Path $StateFile) {
        try {
            $state = Get-Content -Path $StateFile -Raw -Encoding UTF8 | ConvertFrom-Json
            if ($state.last_id) { $lastId = [long]$state.last_id }
        } catch {
            Write-SyncLog "No se pudo leer last_sync.json. Iniciando desde 0." "WARN"
        }
    }

    # 2. Conectar a SQL Server nativamente mediante ADO.NET
    $connString = "Server=$SqlServer;Database=$Database;User Id=$DbUser;Password=$DbPassword;TrustServerCertificate=True;Connect Timeout=15;"
    $connection = New-Object System.Data.SqlClient.SqlConnection($connString)
    
    $nuevasTransacciones = @()
    $maxIdObtenido = $lastId

    try {
        $connection.Open()
        Write-SyncLog "Conexión exitosa a SQL Server ($SqlServer - $Database)" "INFO"

        # Consulta SQL para extraer ventas de DetalleCuenta
        $query = @"
SELECT TOP ($BatchSize)
    d.DetalleCuentaID AS pos_transaccion_id,
    d.CuentaID AS pos_cuenta_id,
    d.ProductoID AS pos_producto_id,
    ISNULL(p.Nombre, ISNULL(p.Descripcion, CONCAT('Producto #', d.ProductoID))) AS nombre_producto_pos,
    d.Cantidad AS cantidad,
    d.Precio AS precio_unitario,
    d.Subtotal AS subtotal,
    ISNULL(c.Fecha, GETDATE()) AS fecha_hora,
    ISNULL(c.Mozo, 'Caja') AS cajero_nombre,
    CASE 
        WHEN EXISTS(SELECT 1 FROM Pagos pg WHERE pg.CuentaID = d.CuentaID AND LOWER(pg.TipoPago) LIKE '%qr%') THEN 'qr'
        WHEN EXISTS(SELECT 1 FROM Pagos pg WHERE pg.CuentaID = d.CuentaID AND (LOWER(pg.TipoPago) LIKE '%tarjeta%' OR LOWER(pg.TipoPago) LIKE '%card%')) THEN 'tarjeta'
        ELSE 'efectivo'
    END AS metodo_pago
FROM DetalleCuenta d
LEFT JOIN Cuentas c ON d.CuentaID = c.CuentaID
LEFT JOIN Productos p ON d.ProductoID = p.ProductoID
WHERE d.DetalleCuentaID > $lastId
ORDER BY d.DetalleCuentaID ASC
"@

        $command = $connection.CreateCommand()
        $command.CommandText = $query
        $reader = $command.ExecuteReader()

        while ($reader.Read()) {
            $transId = [long]$reader["pos_transaccion_id"]
            if ($transId -gt $maxIdObtenido) { $maxIdObtenido = $transId }

            $fechaRaw = $reader["fecha_hora"]
            $fechaStr = (Get-Date $fechaRaw).ToString("yyyy-MM-dd HH:mm:ss")

            $item = [PSCustomObject]@{
                pos_transaccion_id  = $transId.ToString()
                pos_cuenta_id       = $reader["pos_cuenta_id"].ToString()
                pos_producto_id     = $reader["pos_producto_id"].ToString()
                nombre_producto_pos = $reader["nombre_producto_pos"].ToString()
                cantidad            = [decimal]$reader["cantidad"]
                precio_unitario     = [decimal]$reader["precio_unitario"]
                subtotal            = [decimal]$reader["subtotal"]
                fecha_hora          = $fechaStr
                cajero_nombre       = $reader["cajero_nombre"].ToString()
                metodo_pago         = $reader["metodo_pago"].ToString()
            }
            $nuevasTransacciones += $item
        }
        $reader.Close()
    } catch {
        Write-SyncLog "Error consultando SQL Server: $_" "ERROR"
    } finally {
        if ($connection.State -eq [System.Data.ConnectionState]::Open) {
            $connection.Close()
        }
    }

    # 3. Incorporar transacciones del buffer offline pendiente
    $bufferPendiente = @()
    if (Test-Path $BufferFile) {
        try {
            $bufferJson = Get-Content -Path $BufferFile -Raw -Encoding UTF8
            if (-not [string]::IsNullOrWhiteSpace($bufferJson)) {
                $bufferPendiente = $bufferJson | ConvertFrom-Json
            }
        } catch {
            Write-SyncLog "Error leyendo offline_buffer.json" "WARN"
        }
    }

    $loteParaEnviar = @($bufferPendiente) + @($nuevasTransacciones)

    if ($loteParaEnviar.Count -eq 0) {
        Write-SyncLog "Sin nuevas transacciones para sincronizar. (Último ID: $lastId)" "INFO"
        return
    }

    Write-SyncLog "Preparando envío de $($loteParaEnviar.Count) transacciones al cloud ($ApiUrl)..." "INFO"

    # 4. Enviar a la API de Laravel
    $headers = @{
        "X-Branch-Token" = $BranchToken
        "Content-Type"   = "application/json"
        "Accept"         = "application/json"
    }

    $payloadObject = @{
        transacciones = $loteParaEnviar
    }
    $jsonPayload = $payloadObject | ConvertTo-Json -Depth 5 -Compress

    try {
        $response = Invoke-RestMethod -Uri $ApiUrl -Method Post -Headers $headers -Body $jsonPayload -TimeoutSec 30
        
        if ($response.success) {
            Write-SyncLog "Sincronización exitosa: $($response.data.insertadas) insertadas, $($response.data.actualizadas) actualizadas, $($response.data.sin_mapeo) sin mapeo." "INFO"

            # Actualizar watermark del último ID
            $newState = @{
                last_id    = $maxIdObtenido
                updated_at = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
            }
            $newState | ConvertTo-Json | Set-Content -Path $StateFile -Encoding UTF8

            # Limpiar buffer offline
            if (Test-Path $BufferFile) {
                Remove-Item -Path $BufferFile -Force -ErrorAction SilentlyContinue
            }
        } else {
            Write-SyncLog "La API devolvió un resultado no exitoso: $($response.message)" "WARN"
            # Guardar en buffer si eran nuevas
            $loteParaEnviar | ConvertTo-Json -Depth 5 | Set-Content -Path $BufferFile -Encoding UTF8
        }
    } catch {
        Write-SyncLog "Error de comunicación con el servidor cloud: $_" "WARN"
        Write-SyncLog "Guardando $($loteParaEnviar.Count) transacciones en offline_buffer.json para reintento automático." "INFO"
        $loteParaEnviar | ConvertTo-Json -Depth 5 | Set-Content -Path $BufferFile -Encoding UTF8
    }
}

# Ejecutar una vez o en bucle daemon
if ($Loop) {
    Write-SyncLog "Modo servicio activo. Intervalo: $IntervalSeconds segundos. Presione Ctrl+C para detener." "INFO"
    while ($true) {
        Ejecutar-Sincronizacion
        Start-Sleep -Seconds $IntervalSeconds
    }
} else {
    Ejecutar-Sincronizacion
}
