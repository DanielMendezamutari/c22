#!/bin/bash
# ==============================================================================
# SCRIPT GUARDIÁN (WATCHDOG 24/7) - BOT WHATSAPP C22
# Se ejecuta vía Cron Job cada 5 o 10 minutos en cPanel (bh8970) o servidor local.
# Si el proceso se cayó o el servidor se reinició, lo levanta automáticamente.
# ==============================================================================

BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR" || exit 1
source "$BOT_DIR/entrypoint.sh"

# El log del bot crece sin límite (Baileys vuelca sesiones de cifrado completas) y llenó la cuota de disco:
# si pasa de 20 MB se conservan solo las últimas 3000 líneas. El cron corre este script cada 5 minutos.
for LOG in "$BOT_DIR/bot_salida.log" "$BOT_DIR/bot_watchdog.log"; do
    if [ -f "$LOG" ] && [ "$(stat -c %s "$LOG" 2>/dev/null || stat -f %z "$LOG")" -gt 20971520 ]; then
        tail -n 3000 "$LOG" > "$LOG.tmp" && cat "$LOG.tmp" > "$LOG" && rm -f "$LOG.tmp"
    fi
done

PID_FILE="$BOT_DIR/bot.pid"

# Verificar si node bot.js de C22 está corriendo por ps y por bot.pid
PID=""
if [ -f "$PID_FILE" ]; then
    SAVED_PID=$(cat "$PID_FILE")
    if ps -p "$SAVED_PID" > /dev/null 2>&1; then
        PID="$SAVED_PID"
    fi
fi

if [ -z "$PID" ]; then
    PID=$(bot_pids | head -n 1)
fi

# Si se pasó argumento --status
if [ "$1" == "--status" ]; then
    if [ -n "$PID" ]; then
        echo "{\"estado\":\"activo\",\"pid\":$PID}"
    else
        echo "{\"estado\":\"inactivo\",\"pid\":null}"
    fi
    exit 0
fi

if [ -n "$PID" ]; then
    # El bot ya está corriendo normalmente
    echo "$PID" > "$PID_FILE"
    exit 0
else
    # Auto-detección del binario de Node.js 20+ en cPanel (BanaHosting / CloudLinux)
    NODE_BIN=""
    for p in /opt/alt/alt-nodejs20/root/usr/bin/node \
             /opt/cpanel/ea-nodejs20/bin/node \
             /home/vnplktsg/.nvm/versions/node/*/bin/node \
             ~/.nvm/versions/node/*/bin/node \
             /usr/local/bin/node \
             $(which node 2>/dev/null); do
        if [ -x "$p" ]; then
            NODE_BIN="$p"
            break
        fi
    done

    if [ -z "$NODE_BIN" ]; then
        NODE_BIN="node"
    fi

    export PATH="$(dirname "$NODE_BIN"):$PATH"

    # El bot está caído: Levantarlo en segundo plano
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 🤖 El bot C22 estaba apagado. Reiniciando $BOT_ENTRY con $NODE_BIN..." >> bot_watchdog.log
    export UV_THREADPOOL_SIZE=2
    nohup "$NODE_BIN" "$BOT_ENTRY" >> bot_salida.log 2>&1 &
    NUEVO_PID=$!
    echo "$NUEVO_PID" > "$PID_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✅ Bot reiniciado con éxito 24/7 (PID: $NUEVO_PID)" >> bot_watchdog.log
fi
