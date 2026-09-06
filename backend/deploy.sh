#!/bin/bash
# ==========================================================
# C22 Inventario - Script de Despliegue en cPanel / Hosting
# Servidor: bh8970 (c22.ribersoft.com)
# ==========================================================

PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"

echo "=== 1. Verificando PHP 8.2 en cPanel ==="
$PHP_BIN -v

echo "=== 2. Modo Mantenimiento ==="
$PHP_BIN artisan down || true

echo "=== 3. Instalando dependencias Composer ==="
if [ -f "composer.phar" ]; then
    $PHP_BIN composer.phar install --no-dev --optimize-autoloader
elif command -v composer &> /dev/null; then
    $PHP_BIN $(which composer) install --no-dev --optimize-autoloader
else
    echo "Descargando composer.phar..."
    $PHP_BIN -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    $PHP_BIN composer-setup.php
    $PHP_BIN -r "unlink('composer-setup.php');"
    $PHP_BIN composer.phar install --no-dev --optimize-autoloader
fi

echo "=== 4. Generando clave si no existe ==="
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    $PHP_BIN artisan key:generate --force
fi

echo "=== 5. Ejecutando Migraciones y Seed ==="
$PHP_BIN artisan migrate --force

echo "=== 6. Enlazando Storage ==="
$PHP_BIN artisan storage:link || true

echo "=== 7. Optimizando Caché de Laravel ==="
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "=== 8. Desactivando Mantenimiento ==="
$PHP_BIN artisan up

echo "=========================================="
echo " ¡Despliegue de C22 completado con éxito! "
echo "=========================================="
