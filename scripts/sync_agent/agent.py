#!/usr/bin/env python3
"""
Casa 22 - Agente de Sincronización POS RestoTech en Python.
Soporta buffer local offline (offline_buffer.json) e inserción idempotente en Laravel.
"""

import os
import sys
import json
import time
import datetime
import urllib.request
import urllib.error

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_PATH = os.path.join(SCRIPT_DIR, "config.json")
STATE_PATH = os.path.join(SCRIPT_DIR, "last_sync.json")
BUFFER_PATH = os.path.join(SCRIPT_DIR, "offline_buffer.json")
LOG_PATH = os.path.join(SCRIPT_DIR, "sync_log.txt")

def log(msg, level="INFO"):
    timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{timestamp}] [{level}] {msg}"
    print(line)
    try:
        with open(LOG_PATH, "a", encoding="utf-8") as f:
            f.write(line + "\n")
    except Exception:
        pass

def load_config():
    if os.path.exists(CONFIG_PATH):
        with open(CONFIG_PATH, "r", encoding="utf-8") as f:
            return json.load(f)
    return {
        "api_url": "https://c22.ribersoft.com/api/v1/sync/pos-transacciones",
        "branch_token": "C22-SANTA-CRUZ-SECRET-KEY-2026",
        "sql_server": "localhost\\SQLEXPRESS",
        "database": "ControlConsumoCasa22",
        "db_user": "sa",
        "db_password": "toptech",
        "poll_interval_seconds": 300,
        "batch_size": 200
    }

def get_last_id():
    if os.path.exists(STATE_PATH):
        try:
            with open(STATE_PATH, "r", encoding="utf-8") as f:
                data = json.load(f)
                return int(data.get("last_id", 0))
        except Exception:
            pass
    return 0

def save_last_id(last_id):
    try:
        with open(STATE_PATH, "w", encoding="utf-8") as f:
            json.dump({
                "last_id": last_id,
                "updated_at": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            }, f, indent=2)
    except Exception as e:
        log(f"Error guardando last_sync.json: {e}", "WARN")

def load_buffer():
    if os.path.exists(BUFFER_PATH):
        try:
            with open(BUFFER_PATH, "r", encoding="utf-8") as f:
                content = f.read().strip()
                if content:
                    return json.loads(content)
        except Exception as e:
            log(f"Error leyendo offline_buffer.json: {e}", "WARN")
    return []

def save_buffer(items):
    try:
        with open(BUFFER_PATH, "w", encoding="utf-8") as f:
            json.dump(items, f, indent=2)
    except Exception as e:
        log(f"Error guardando offline_buffer.json: {e}", "ERROR")

def clear_buffer():
    if os.path.exists(BUFFER_PATH):
        try:
            os.remove(BUFFER_PATH)
        except Exception:
            pass

def send_to_cloud(api_url, branch_token, items):
    payload = json.dumps({"transacciones": items}).encode("utf-8")
    req = urllib.request.Request(
        api_url,
        data=payload,
        headers={
            "X-Branch-Token": branch_token,
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": "Casa22-Python-Agent/1.0"
        },
        method="POST"
    )
    with urllib.request.urlopen(req, timeout=30) as response:
        res_data = response.read().decode("utf-8")
        return json.loads(res_data)

def sync_cycle(config):
    log("Iniciando ciclo de sincronización (Python)...")
    last_id = get_last_id()
    new_items = []
    max_id = last_id

    # Intentar conexión con pyodbc si está instalado
    try:
        import pyodbc
        conn_str = (
            f"DRIVER={{ODBC Driver 17 for SQL Server}};"
            f"SERVER={config.get('sql_server', 'localhost\\SQLEXPRESS')};"
            f"DATABASE={config.get('database', 'ControlConsumoCasa22')};"
            f"UID={config.get('db_user', 'sa')};"
            f"PWD={config.get('db_password', 'toptech')};"
            f"TrustServerCertificate=yes;"
        )
        conn = pyodbc.connect(conn_str, timeout=10)
        cursor = conn.cursor()
        
        query = f"""
        SELECT TOP ({config.get('batch_size', 200)})
            d.DetalleCuentaID,
            d.CuentaID,
            d.ProductoID,
            ISNULL(p.Nombre, ISNULL(p.Descripcion, 'Producto ' + CAST(d.ProductoID AS VARCHAR))),
            d.Cantidad,
            d.Precio,
            d.Subtotal,
            ISNULL(c.Fecha, GETDATE()),
            ISNULL(c.Mozo, 'Caja'),
            CASE 
                WHEN EXISTS(SELECT 1 FROM Pagos pg WHERE pg.CuentaID = d.CuentaID AND LOWER(pg.TipoPago) LIKE '%qr%') THEN 'qr'
                WHEN EXISTS(SELECT 1 FROM Pagos pg WHERE pg.CuentaID = d.CuentaID AND (LOWER(pg.TipoPago) LIKE '%tarjeta%' OR LOWER(pg.TipoPago) LIKE '%card%')) THEN 'tarjeta'
                ELSE 'efectivo'
            END
        FROM DetalleCuenta d
        LEFT JOIN Cuentas c ON d.CuentaID = c.CuentaID
        LEFT JOIN Productos p ON d.ProductoID = p.ProductoID
        WHERE d.DetalleCuentaID > {last_id}
        ORDER BY d.DetalleCuentaID ASC
        """
        cursor.execute(query)
        rows = cursor.fetchall()
        for r in rows:
            tid = int(r[0])
            if tid > max_id:
                max_id = tid
            fecha = r[7].strftime("%Y-%m-%d %H:%M:%S") if hasattr(r[7], "strftime") else str(r[7])
            new_items.append({
                "pos_transaccion_id": str(tid),
                "pos_cuenta_id": str(r[1]),
                "pos_producto_id": str(r[2]),
                "nombre_producto_pos": str(r[3]),
                "cantidad": float(r[4]),
                "precio_unitario": float(r[5]),
                "subtotal": float(r[6]),
                "fecha_hora": fecha,
                "cajero_nombre": str(r[8]),
                "metodo_pago": str(r[9])
            })
        conn.close()
    except ImportError:
        log("pyodbc no está instalado en este entorno. Si está en Windows sin Python, use sync_casa22.ps1", "WARN")
    except Exception as e:
        log(f"Error consultando SQL Server en Python: {e}", "ERROR")

    buffer_items = load_buffer()
    all_items = buffer_items + new_items

    if not all_items:
        log(f"Sin transacciones pendientes (Último ID: {last_id})")
        return

    try:
        res = send_to_cloud(config["api_url"], config["branch_token"], all_items)
        if res.get("success"):
            log(f"Éxito: {res.get('data', {})}")
            save_last_id(max_id)
            clear_buffer()
        else:
            log(f"Respuesta inesperada del servidor: {res}", "WARN")
            save_buffer(all_items)
    except urllib.error.URLError as e:
        log(f"Servidor cloud inaccesible ({e}). Almacenando {len(all_items)} en buffer offline.", "WARN")
        save_buffer(all_items)
    except Exception as e:
        log(f"Fallo general de red ({e}). Guardando en buffer.", "ERROR")
        save_buffer(all_items)

def main():
    config = load_config()
    loop_mode = "--loop" in sys.argv
    interval = int(config.get("poll_interval_seconds", 300))

    if loop_mode:
        log(f"Iniciando servicio de fondo cada {interval} segundos. Presione Ctrl+C para salir.")
        while True:
            try:
                sync_cycle(config)
            except Exception as e:
                log(f"Excepción en ciclo de sincronización: {e}", "ERROR")
            time.sleep(interval)
    else:
        sync_cycle(config)

if __name__ == "__main__":
    main()
