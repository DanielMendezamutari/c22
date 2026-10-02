#!/bin/bash
# ==========================================================
# C22 Inventario - Script de Despliegue Automatizado en cPanel
# Servidor: bh8970 (c22.ribersoft.com)
# ==========================================================

set -e

PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"

echo "=========================================================="
echo " [SDD] Iniciando Despliegue C22 en c22.ribersoft.com"
echo "=========================================================="

echo "=== 1. Actualizando repositorio Git (origin/main) ==="
git pull origin main

echo "=== 2. Verificando binario PHP 8.2 ==="
$PHP_BIN -v

echo "=== 3. Modo Mantenimiento temporal ==="
$PHP_BIN artisan down || true

echo "=== 4. Creando directorios requeridos de Storage y Framework ==="
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/framework/cache/data storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

echo "=== 5. Ejecutando Migraciones Pendientes (--force) ==="
$PHP_BIN artisan migrate --force

echo "=== 6. Asegurando enlace simbólico a Storage ==="
$PHP_BIN artisan storage:link || true

echo "=== 7. Limpieza y Reconstrucción de Cachés ==="
$PHP_BIN artisan config:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "=== 8. Activando Sistema Productivo ==="
$PHP_BIN artisan up

echo "=========================================================="
echo " ¡Despliegue C22 finalizado con éxito!                    "
echo " URL: https://c22.ribersoft.com                           "
echo "=========================================================="
