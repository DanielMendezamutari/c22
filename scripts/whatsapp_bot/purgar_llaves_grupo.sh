#!/bin/bash
# ==============================================================================
# Fuerza al bot a redistribuir la llave de cifrado de UN grupo a todos sus integrantes.
# Úsalo cuando los mensajes del bot en ese grupo quedan en "Esperando mensaje".
#   bash purgar_llaves_grupo.sh 120363428775446657@g.us     (Control de recaudado)
# Borra el registro de a quién ya se le envió la llave (sender-key-memory) y la llave propia del bot en
# ese grupo; el próximo mensaje del bot crea una llave nueva y la envía a todos los dispositivos.
# Respalda los archivos antes de borrarlos. No toca la sesión de WhatsApp ni otros grupos.
# ==============================================================================
set -euo pipefail
BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR"
source "$BOT_DIR/entrypoint.sh"

GRUPO="${1:-}"
if [[ ! "$GRUPO" =~ ^[0-9]+@g\.us$ ]]; then
    echo "Uso: bash purgar_llaves_grupo.sh <id-del-grupo>@g.us" >&2
    exit 1
fi
AUTH_DIR="${AUTH_DIR:-./auth_info_baileys}"
ID="${GRUPO%@g.us}"

ARCHIVOS=$(ls "$AUTH_DIR" | grep -E "^sender-key(-memory)?-${ID}@g\.us" || true)
if [ -z "$ARCHIVOS" ]; then
    echo "No hay llaves guardadas para $GRUPO (nada que purgar)."
    exit 0
fi

RESPALDO="$HOME/respaldo_llaves_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$RESPALDO"
echo "1. Deteniendo el bot..."
for pid in $(bot_pids); do kill "$pid" 2>/dev/null || true; done
sleep 3

echo "2. Respaldando y borrando $(echo "$ARCHIVOS" | wc -l) archivo(s) de llaves del grupo en $RESPALDO"
for f in $ARCHIVOS; do
    cp "$AUTH_DIR/$f" "$RESPALDO/"
    rm -f "$AUTH_DIR/$f"
done

echo "3. Levantando el bot..."
bash verificar_y_levantar.sh
echo "Listo. El próximo mensaje del bot a $GRUPO enviará una llave nueva a todos los integrantes."
