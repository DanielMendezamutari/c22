# Regla Permanente de Entorno y Rutas del Servidor cPanel (C22)

## Rutas Canónicas del Servidor
- **Directorio raíz del proyecto:** `cd c22.ribersoft.com` o `~/c22.ribersoft.com` (Ruta absoluta: `/home/vnplktsg/c22.ribersoft.com`).
- **NUNCA usar:** `~/public_html/scripts/whatsapp_bot`.
- **SIEMPRE usar para el bot de WhatsApp:**
  ```bash
  cd ~/c22.ribersoft.com/scripts/whatsapp_bot && bash verificar_y_levantar.sh
  ```
  O desde la raíz:
  ```bash
  cd ~/c22.ribersoft.com && ./levantar_bot.sh
  ```

## Tarea Cron en cPanel
```bash
cd /home/vnplktsg/c22.ribersoft.com/scripts/whatsapp_bot && bash verificar_y_levantar.sh > /dev/null 2>&1
```
