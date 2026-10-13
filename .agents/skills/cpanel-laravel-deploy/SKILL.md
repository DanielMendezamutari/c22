---
name: "cpanel-laravel-deploy"
description: "Guía y comandos de despliegue de Laravel en cPanel con PHP 8.2 en c22.ribersoft.com"
---

# Despliegue de C22 Inventario en cPanel (bh8970 / c22.ribersoft.com)

Esta habilidad documenta la configuración del hosting cPanel para ejecutar Laravel 11.

## Ruta del Binario PHP 8.2
En el servidor cPanel `bh8970`, el binario predeterminado del sistema suele ser una versión anterior. Se debe invocar explícitamente PHP 8.2:

```bash
/opt/cpanel/ea-php82/root/usr/bin/php
```

## Configuración Permanente de Alias en SSH
Para no escribir la ruta completa en cada comando, añadir este alias a la sesión de usuario:

```bash
echo "alias php='/opt/cpanel/ea-php82/root/usr/bin/php'" >> ~/.bashrc
source ~/.bashrc
```

A partir de ese momento, cualquier comando estándar de Laravel funciona de forma nativa:
```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
```

## Script Automatizado de Despliegue
En la raíz de la API (`c22.ribersoft.com/`) se encuentra `deploy.sh`:
```bash
chmod +x deploy.sh
./deploy.sh
```

## Rutas Permanentes del Servidor cPanel (bh8970)
- **Directorio raíz del proyecto:** `~/c22.ribersoft.com` (o `/home/vnplktsg/c22.ribersoft.com`)
- **Directorio del Bot de WhatsApp:** `~/c22.ribersoft.com/scripts/whatsapp_bot`

## Comandos del Bot de WhatsApp (24/7)
Para levantar o verificar el bot en segundo plano:
```bash
cd ~/c22.ribersoft.com/scripts/whatsapp_bot
bash verificar_y_levantar.sh
```
O directamente desde la raíz del proyecto:
```bash
cd ~/c22.ribersoft.com
./levantar_bot.sh
```

## Tarea Cron en cPanel (Watchdog cada 5 minutos)
En **Tareas Cron** de cPanel, configurar:
- **Intervalo:** `*/5 * * * *`
- **Comando:**
```bash
cd /home/vnplktsg/c22.ribersoft.com/scripts/whatsapp_bot && bash verificar_y_levantar.sh > /dev/null 2>&1
```

## DocumentRoot y Redirección en cPanel
El servidor Apache debe apuntar a la carpeta `public/` de Laravel.
Si el cPanel no permite cambiar el DocumentRoot del subdominio, usar un `.htaccess` en la raíz de `c22.ribersoft.com`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```
