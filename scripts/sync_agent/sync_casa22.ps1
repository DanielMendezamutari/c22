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

    try {
        $connection.Open()
        Write-SyncLog "Conexión exitosa a SQL Server ($SqlServer - $Database)" "INFO"

        # Si es la primera vez que se sincroniza ($lastId == 0):
        # Determinar punto de inicio inteligente para sincronizar turnos recientes (últimos 7 días)
        if ($lastId -eq 0) {
            try {
                $checkCmd = $connection.CreateCommand()
                $checkCmd.CommandText = "SELECT ISNULL(MIN(ID), 0) FROM DetalleCuenta WHERE Hora >= DATEADD(day, -7, GETDATE())"
                $minRecent = [long]$checkCmd.ExecuteScalar()
                if ($minRecent -gt 0) {
                    $lastId = $minRecent - 1
                    Write-SyncLog "Primera sincronización: comenzando desde los últimos 7 días (ID: $lastId)" "INFO"
                } else {
                    $checkCmd.CommandText = "SELECT ISNULL(MAX(ID) - 500, 0) FROM DetalleCuenta"
                    $maxMinus500 = [long]$checkCmd.ExecuteScalar()
                    if ($maxMinus500 -gt 0) {
                        $lastId = $maxMinus500
                        Write-SyncLog "Primera sincronización: comenzando desde las últimas 500 ventas (ID: $lastId)" "INFO"
                    }
                }
            } catch {
                Write-SyncLog "Determinando ID inicial: $_" "WARN"
            }
        }

        # Ciclo de procesamiento por lotes hasta que no queden transacciones pendientes
        $hayMasLotes = $true
        $totalSincronizadas = 0

        while ($hayMasLotes) {
            $nuevasTransacciones = @()
            $maxIdLote = $lastId

            # Consulta SQL para extraer ventas de DetalleCuenta en RestoTech
            $query = @"
SELECT TOP ($BatchSize)
    d.ID AS pos_transaccion_id,
    ISNULL(d.VisitaID, 0) AS pos_cuenta_id,
    ISNULL(d.ProductoID, 0) AS pos_producto_id,
    ISNULL(p.Nombre, ISNULL(p.Descripcion, CONCAT('Producto #', d.ProductoID))) AS nombre_producto_pos,
    ISNULL(d.Cantidad, 1) AS cantidad,
    ISNULL(d.PrecioUnit, ISNULL(d.Pago, 0)) AS precio_unitario,
    ISNULL(d.Pago, ISNULL(d.Cantidad * d.PrecioUnit, 0)) AS subtotal,
    ISNULL(d.Hora, ISNULL(v.Fecha, GETDATE())) AS fecha_hora,
    ISNULL(m.Nombre, 'Caja') AS cajero_nombre,
    CASE 
        WHEN EXISTS(
            SELECT 1 FROM Pagos pg 
            INNER JOIN Cuentas ct ON pg.CuentaID = ct.CuentaID 
            WHERE pg.DetalleCuentaID = d.ID 
            AND (LOWER(ct.Nombre) LIKE '%qr%' OR ct.TengoQR = 1)
        ) THEN 'qr'
        WHEN EXISTS(
            SELECT 1 FROM Pagos pg 
            INNER JOIN Cuentas ct ON pg.CuentaID = ct.CuentaID 
            WHERE pg.DetalleCuentaID = d.ID 
            AND (LOWER(ct.Nombre) LIKE '%tarjeta%' OR LOWER(ct.Nombre) LIKE '%card%')
        ) THEN 'tarjeta'
        ELSE 'efectivo'
    END AS metodo_pago
FROM DetalleCuenta d
LEFT JOIN Visitas v ON d.VisitaID = v.ID
LEFT JOIN Productos p ON d.ProductoID = p.ID
LEFT JOIN Meseros m ON d.MeseroID = m.MeseroID
WHERE d.ID > $lastId
  AND (d.Borrada = 0 OR d.Borrada IS NULL)
ORDER BY d.ID ASC
"@

            $command = $connection.CreateCommand()
            $command.CommandText = $query
            $reader = $command.ExecuteReader()

            while ($reader.Read()) {
                $transId = [long]$reader["pos_transaccion_id"]
                if ($transId -gt $maxIdLote) { $maxIdLote = $transId }

                $fechaRaw = $reader["fecha_hora"]
                $fechaStr = (Get-Date $fechaRaw).ToString("yyyy-MM-dd HH:mm:ss")

                $item = [PSCustomObject]@{
                    pos_detalle_id      = $transId.ToString()
                    pos_transaccion_id  = $transId.ToString()
                    pos_cuenta_id       = $reader["pos_cuenta_id"].ToString()
                    pos_producto_id     = $reader["pos_producto_id"].ToString()
                    pos_nombre_producto = $reader["nombre_producto_pos"].ToString()
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

            # Incorporar transacciones del buffer offline pendiente
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
                if ($totalSincronizadas -eq 0) {
                    Write-SyncLog "Sin nuevas transacciones para sincronizar. (Último ID: $lastId)" "INFO"
                } else {
                    Write-SyncLog "Todas las transacciones pendientes han sido sincronizadas. Total: $totalSincronizadas" "INFO"
                }
                $hayMasLotes = $false
                break
            }

            Write-SyncLog "Enviando lote de $($loteParaEnviar.Count) transacciones al servidor cloud..." "INFO"

            # Enviar a la API de Laravel
            $headers = @{
                "X-Branch-Token" = $BranchToken
                "Content-Type"   = "application/json"
                "Accept"         = "application/json"
            }

            $payloadObject = @{ transacciones = $loteParaEnviar }
            $jsonPayload = $payloadObject | ConvertTo-Json -Depth 5 -Compress

            try {
                $response = Invoke-RestMethod -Uri $ApiUrl -Method Post -Headers $headers -Body $jsonPayload -TimeoutSec 30
                
                if ($response.success) {
                    $insertadas = $response.data.insertadas
                    $actualizadas = $response.data.actualizadas
                    $totalSincronizadas += $loteParaEnviar.Count
                    Write-SyncLog "Lote sincronizado exitosamente: $insertadas insertadas, $actualizadas actualizadas." "INFO"

                    # Actualizar watermark del último ID
                    $lastId = $maxIdLote
                    $newState = @{
                        last_id    = $lastId
                        updated_at = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
                    }
                    $newState | ConvertTo-Json | Set-Content -Path $StateFile -Encoding UTF8

                    # Limpiar buffer offline
                    if (Test-Path $BufferFile) {
                        Remove-Item -Path $BufferFile -Force -ErrorAction SilentlyContinue
                    }

                    # Si el lote trajo menos que el BatchSize, ya alcanzamos el final
                    if ($nuevasTransacciones.Count -lt $BatchSize) {
                        $hayMasLotes = $false
                    }
                } else {
                    Write-SyncLog "Servidor devolvió error: $($response.message)" "WARN"
                    $loteParaEnviar | ConvertTo-Json -Depth 5 | Set-Content -Path $BufferFile -Encoding UTF8
                    $hayMasLotes = $false
                }
            } catch {
                Write-SyncLog "Error de comunicación con el servidor cloud: $_" "WARN"
                Write-SyncLog "Guardando $($loteParaEnviar.Count) transacciones en offline_buffer.json para reintento automático." "INFO"
                $loteParaEnviar | ConvertTo-Json -Depth 5 | Set-Content -Path $BufferFile -Encoding UTF8
                $hayMasLotes = $false
            }
        }
    } catch {
        Write-SyncLog "Error general de conexión o consulta SQL Server: $_" "ERROR"
    } finally {
        if ($connection.State -eq [System.Data.ConnectionState]::Open) {
            $connection.Close()
        }
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
