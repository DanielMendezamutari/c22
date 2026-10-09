#!/bin/bash
# ==============================================================================
# INICIAR BOT WHATSAPP C22 - ENTORNO CPANEL / HOSTING BANALHOSTING
# ==============================================================================

BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR" || exit 1

echo "================================================================="
echo "   Iniciando Bot de WhatsApp C22 en Servidor cPanel"
echo "================================================================="

# 1. Detección automática de Node.js 20+ (BanaHosting / CloudLinux / cPanel)
NODE_BIN=""
NPM_BIN=""

for p in /opt/alt/alt-nodejs20/root/usr/bin/node \
         /opt/cpanel/ea-nodejs20/bin/node \
         /home/vnplktsg/.nvm/versions/node/*/bin/node \
         ~/.nvm/versions/node/*/bin/node \
         /usr/local/bin/node \
         $(which node 2>/dev/null); do
    if [ -x "$p" ]; then
        NODE_BIN="$p"
        NODE_DIR="$(dirname "$p")"
        if [ -x "$NODE_DIR/npm" ]; then
            NPM_BIN="$NODE_DIR/npm"
        fi
        break
    fi
done

if [ -z "$NODE_BIN" ]; then
    echo "❌ Error: No se encontró Node.js en el sistema."
    exit 1
fi

export PATH="$(dirname "$NODE_BIN"):$PATH"
echo "✅ Usando Node.js: $("$NODE_BIN" -v) en: $NODE_BIN"

# 2. Asegurar archivo .env con URL de produccion
if [ ! -f .env ] || grep -q "localhost" .env; then
    echo "⚙️ Configurando archivo .env para produccion c22.ribersoft.com..."
    cat <<EOF > .env
LARAVEL_API_URL=https://c22.ribersoft.com/api/v1
WHATSAPP_WEBHOOK_SECRET=puntofrio_wh_secret_2026_super
SYNC_INTERVAL_MS=300000
AUTH_DIR=./auth_info_baileys
EOF
fi

# 3. Verificar dependencias instaladas
if [ ! -d "node_modules/@whiskeysockets/baileys" ]; then
    echo "📦 Instalando dependencias requeridas..."
    if [ -n "$NPM_BIN" ]; then
        "$NPM_BIN" install --no-audit --no-fund
    else
        npm install --no-audit --no-fund
    fi
fi

# 4. Iniciar bot interactivo
echo "🚀 Levantando bot de WhatsApp..."
echo "👉 El código QR se proyectará en la consola Y en https://c22.ribersoft.com/whatsapp/gestion"
echo "-----------------------------------------------------------------"

"$NODE_BIN" --experimental-global-webcrypto bot.js
