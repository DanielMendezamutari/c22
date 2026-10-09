#!/bin/bash
# ==========================================================
# C22 Inventario - Script de Despliegue Automatizado en cPanel
# Servidor: bh8970 (c22.ribersoft.com)
# ==========================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Si estamos en la raiz del repo, entramos a backend/
if [ -d "$SCRIPT_DIR/backend" ]; then
    PROJECT_ROOT="$SCRIPT_DIR"
    BACKEND_DIR="$SCRIPT_DIR/backend"
else
    PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
    BACKEND_DIR="$SCRIPT_DIR"
fi

cd "$PROJECT_ROOT"

echo "=========================================================="
echo " [SDD] Iniciando Despliegue C22 en c22.ribersoft.com"
echo "=========================================================="

echo "=== 1. Actualizando repositorio Git (origin/main) ==="
git pull origin main

cd "$BACKEND_DIR"

# Detectar binario PHP 8.2 (cPanel o local fallback)
if [ -x "/opt/cpanel/ea-php82/root/usr/bin/php" ]; then
    PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"
elif command -v php >/dev/null 2>&1; then
    PHP_BIN="php"
else
    echo "ERROR: No se encontro el ejecutable de PHP."
    exit 1
fi

trap '$PHP_BIN artisan up || true' EXIT

echo "=== 2. Verificando binario PHP ==="
$PHP_BIN -v

echo "=== 3. Modo Mantenimiento temporal ==="
$PHP_BIN artisan down || true

echo "=== 4. Creando directorios requeridos de Storage y Framework ==="
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/framework/cache/data storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "=== 5. Ejecutando Migraciones Pendientes (--force) ==="
$PHP_BIN artisan migrate --force

echo "=== 6. Asegurando enlace simbolico a Storage ==="
$PHP_BIN artisan storage:link 2>/dev/null || true

echo "=== 7. Limpieza y Reconstruccion de Caches ==="
$PHP_BIN artisan config:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "=== 8. Activando Sistema Productivo ==="
$PHP_BIN artisan up

echo "=========================================================="
echo " ¡Despliegue C22 finalizado con exito!                    "
echo " URL: https://c22.ribersoft.com                           "
echo "=========================================================="
