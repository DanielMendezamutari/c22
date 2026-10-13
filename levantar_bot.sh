#!/bin/bash
# ==============================================================================
# ACCESO DIRECTO RÁPIDO PARA LEVANTAR EL BOT 24/7 EN c22.ribersoft.com
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BOT_DIR="$SCRIPT_DIR/scripts/whatsapp_bot"

if [ -d "$BOT_DIR" ]; then
    cd "$BOT_DIR" || exit 1
    bash verificar_y_levantar.sh "$@"
else
    echo "❌ No se encontró la carpeta: $BOT_DIR"
    exit 1
fi
