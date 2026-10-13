#!/bin/bash
BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR" || exit 1

echo "=========================================================="
echo " [C22 WHATSAPP] Reiniciando Sesión Criptográfica Corrupta"
echo "=========================================================="

echo "1. Liberando puerto 3001 y deteniendo procesos activos del bot..."
pkill -9 -f "bot\.js" 2>/dev/null
fuser -k -9 3001/tcp 2>/dev/null
if command -v lsof >/dev/null 2>&1; then
    lsof -ti :3001 | xargs -r kill -9 2>/dev/null
fi

if [ -f bot.pid ]; then
    kill -9 $(cat bot.pid) 2>/dev/null
    rm -f bot.pid
fi

sleep 1
echo "   ✔ Puerto 3001 liberado y procesos detenidos."

echo "2. Purgando credenciales de sesión corruptas (auth_info_baileys)..."
rm -rf auth_info_baileys
echo "   ✔ Credenciales limpiadas."

echo "3. Iniciando Bot C22 para generar NUEVO código QR limpio..."
echo "   (Escanea el QR que aparecerá abajo con tu WhatsApp)"
echo "=========================================================="

./iniciar_bot.sh
