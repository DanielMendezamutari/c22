#!/bin/bash
# ==============================================================================
# SCRIPT GUARDIÁN (WATCHDOG) - BOT WHATSAPP C22
# Se ejecuta vía Cron Job cada 5 o 10 minutos en cPanel (bh8970).
# Si el proceso se cayó o el servidor se reinició, lo levanta automáticamente.
# ==============================================================================

BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR" || exit 1

# Verificar si node bot.js de C22 está corriendo
PID=$(ps aux | grep -v grep | grep -E "node.*scripts/whatsapp_bot/bot\.js|node.*whatsapp_bot.*bot\.js" | awk '{print $2}' | head -n 1)

if [ -n "$PID" ]; then
    # El bot ya está corriendo normalmente
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
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 🤖 El bot C22 estaba apagado. Reiniciando con $NODE_BIN..." >> bot_watchdog.log
    nohup "$NODE_BIN" --experimental-global-webcrypto bot.js >> bot_salida.log 2>&1 &
    NUEVO_PID=$!
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✅ Bot reiniciado con éxito (PID: $NUEVO_PID)" >> bot_watchdog.log
fi
