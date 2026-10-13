#!/bin/bash
BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR" || exit 1

echo "=========================================================="
echo " [C22 WHATSAPP] Reiniciando Sesión Criptográfica Corrupta"
echo "=========================================================="

echo "1. Deteniendo procesos activos del bot..."
PID_ENCONTRADO=$(ps aux | grep -v grep | grep -E "node.*scripts/whatsapp_bot/bot\.js|node.*whatsapp_bot.*bot\.js" | awk '{print $2}')
if [ -n "$PID_ENCONTRADO" ]; then
    kill -9 $PID_ENCONTRADO 2>/dev/null
    echo "   ✔ Proceso anterior terminado (PID: $PID_ENCONTRADO)."
fi
rm -f bot.pid

echo "2. Purgando credenciales de sesión corruptas (auth_info_baileys)..."
rm -rf auth_info_baileys
echo "   ✔ Credenciales limpiadas."

echo "3. Iniciando Bot C22 para generar NUEVO código QR limpio..."
echo "   (Escanea el QR que aparecerá abajo con tu WhatsApp)"
echo "=========================================================="

./iniciar_bot.sh
