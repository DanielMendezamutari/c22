#!/bin/bash
# Compartido por los scripts de arranque: qué archivo ejecutar y cómo encontrar el proceso del bot.
# Prioriza el paquete compilado (dist/bot.mjs, sin node_modules); si no existe, usa bot.js.

if [ -f "$BOT_DIR/dist/bot.mjs" ]; then
    BOT_ENTRY="dist/bot.mjs"
else
    BOT_ENTRY="bot.js"
fi

# PIDs de node ejecutando el bot de esta carpeta (bot.js o dist/bot.mjs)
bot_pids() {
    for pid in $(pgrep -f "node.*(bot\.js|bot\.mjs)" 2>/dev/null); do
        if [ "$(readlink "/proc/$pid/cwd" 2>/dev/null)" = "$BOT_DIR" ]; then
            echo "$pid"
        fi
    done
}
