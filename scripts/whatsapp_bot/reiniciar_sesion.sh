#!/bin/bash
BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR" || exit 1

echo "=========================================================="
echo " [C22 WHATSAPP] Reiniciando Sesión Criptográfica Corrupta"
echo "=========================================================="

echo "1. Liberando hilos y procesos Node.js huérfanos del usuario..."
pkill -9 -f node 2>/dev/null
killall -9 node 2>/dev/null
fuser -k -9 3001/tcp 2>/dev/null
if command -v lsof >/dev/null 2>&1; then
    lsof -ti :3001 | xargs -r kill -9 2>/dev/null
fi
rm -f bot.pid
sleep 1
echo "   ✔ Procesos huérfanos terminados y puerto 3001 liberado."

echo "2. Purgando credenciales de sesión corruptas (auth_info_baileys)..."
rm -rf auth_info_baileys
echo "   ✔ Credenciales limpiadas."

echo "3. Iniciando Bot C22 para generar NUEVO código QR limpio..."
echo "   (Escanea el QR que aparecerá abajo con tu WhatsApp)"
echo "=========================================================="

./iniciar_bot.sh
