#!/bin/bash
# ==============================================================================
# Compila el bot en un solo archivo (dist/bot.mjs) para desplegarlo sin node_modules.
# Se ejecuta en LOCAL antes del commit; el hosting solo necesita Node 20 para correrlo.
#   cd scripts/whatsapp_bot && npm ci && ./build.sh && git add dist/bot.mjs
# sharp/jimp/link-preview-js/audio-decode quedan fuera: Baileys los carga solo si existen
# (miniaturas de imágenes y vistas previas de enlaces, que el bot no necesita).
# ==============================================================================
set -euo pipefail

BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR"

if [ ! -d node_modules/@whiskeysockets/baileys ]; then
    echo "Falta node_modules: ejecutar 'npm ci' primero." >&2
    exit 1
fi

VERSION_BAILEYS=$(node -p "require('./node_modules/@whiskeysockets/baileys/package.json').version")
COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "sin-git")

mkdir -p dist
npx --yes esbuild@0.25.10 bot.js \
    --bundle --minify --keep-names \
    --platform=node --target=node20 --format=esm \
    --outfile=dist/bot.mjs \
    --external:sharp --external:jimp --external:link-preview-js --external:audio-decode \
    --banner:js="// Compilado desde bot.js (commit ${COMMIT}, Baileys ${VERSION_BAILEYS}). No editar: ejecutar ./build.sh
import { createRequire as __cr } from 'module'; const require = __cr(import.meta.url);" \
    --log-level=warning

node --check dist/bot.mjs
echo "OK dist/bot.mjs ($(du -h dist/bot.mjs | cut -f1)) · Baileys ${VERSION_BAILEYS} · desde commit ${COMMIT}"
