#!/bin/bash
BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR" || exit 1

echo "=========================================================="
echo " [C22 WHATSAPP] Purgando Sender Keys Corruptas de Grupos"
echo " (Mantiene creds.json intacto - NO requiere escanear QR)"
echo "=========================================================="

echo "1. Deteniendo proceso del bot..."
source "$BOT_DIR/entrypoint.sh"
for pid in $(bot_pids); do kill "$pid" 2>/dev/null; done
sleep 1

echo "2. Purgando sender keys corruptas en auth_info_baileys..."
if [ -d "auth_info_baileys" ]; then
    rm -f auth_info_baileys/sender-key*
    echo "   ✔ Archivos sender-key eliminados exitosamente."
else
    echo "   ℹ No existe directorio auth_info_baileys."
fi

echo "3. Reiniciando bot con llaves limpias..."
bash verificar_y_levantar.sh

echo "=========================================================="
echo " ✔ Listo. Al enviar el próximo mensaje grupal, Baileys"
echo "   generará una nueva clave y la distribuirá a todos"
echo "   los miembros del grupo."
echo "=========================================================="
