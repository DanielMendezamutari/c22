# Quickstart / Despliegue — 004 Informes de Cierre de Encargadas

## Validación local
```bash
cd backend && php artisan test tests/Feature/InformeCierreEncargadaTest.php   # 7 escenarios del spec
cd backend && php artisan test                                              # suite completa
node --check scripts/whatsapp_bot/bot.js
```
El bot NO se arranca en local: su `.env` apunta a producción.

## Despliegue en producción (orden obligatorio)

1. **Respaldar la sesión del bot** (la librería cambia de 6.7.24 a 7.0.0-rc14):
   ```bash
   cd ~/c22.ribersoft.com/scripts/whatsapp_bot
   cp -r auth_info_baileys ~/auth_info_baileys_respaldo_$(date +%F)
   ```
2. **Llevar el código a `main`** (deploy.sh hace `git pull origin main`).
3. **Backend**: `cd ~/c22.ribersoft.com && ./deploy.sh` (aplica la migración `2026_10_16_000001`).
4. **Verificar la clave de Gemini** (ahora se lee con `config()`, compatible con `config:cache`):
   ```bash
   cd ~/c22.ribersoft.com/backend
   /opt/cpanel/ea-php82/root/usr/bin/php artisan tinker --execute="echo config('services.gemini.api_key') ? 'GEMINI OK' : 'FALTA GEMINI_API_KEY';"
   ```
   Si dice FALTA: agregar `GEMINI_API_KEY=...` en `backend/.env` y `php artisan config:cache`. Sin clave, las fotos quedan "requiere_revision" (ya no se inventan cifras).
5. **Bot** (se ejecuta compilado: `dist/bot.mjs`, sin `node_modules`; la cuota del hosting no alcanza para `npm`):
   ```bash
   cd ~/c22.ribersoft.com/scripts/whatsapp_bot
   rm -rf node_modules ~/.npm/_cacache          # ya no se usan; liberan espacio
   for p in $(pgrep -f "node.*bot\.(m?js)"); do kill $p; done; sleep 3
   bash verificar_y_levantar.sh
   sleep 20; tail -n 30 bot_salida.log      # esperar "Iniciando Baileys" y conexión
   ```
   Si pide QR: escanearlo desde la Plataforma Web → Gestión WhatsApp.
6. **Prueba real**:
   - Un barman cierra turno en la app → **no** llega informe a "Control de recaudado".
   - La encargada envía la foto de su planilla al grupo de su casa → llega **un** informe con sus montos y su nombre.
   - Abrir el informe en 2 teléfonos del grupo (uno que no lo haya abierto antes) → se lee, sin "Esperando mensaje".
   - En `bot_salida.log` no aparece `error in sending message again`.

## Vuelta atrás del bot
```bash
cd ~/c22.ribersoft.com/scripts/whatsapp_bot
pkill -f "bot.js"
git checkout <commit-anterior-al-004> -- bot.js package.json package-lock.json   # bot.js nuevo usa isPnUser, que no existe en 6.7.24
npm install --no-audit --no-fund
rm -rf auth_info_baileys && cp -r ~/auth_info_baileys_respaldo_<fecha> auth_info_baileys
bash verificar_y_levantar.sh
```

## Datos a revisar (no es código)
- Sucursales duplicadas en producción: `corona` (2) y `Casa Corona` (6); `Madam` (3) y `Madan` (7); `prueba` (4). La IA recibe esta lista: desactivar las que no correspondan.
- Registrar a las encargadas con rol `cajera` y su casa (`sucursal_actual_id`) para que la IA las reconozca por nombre.
