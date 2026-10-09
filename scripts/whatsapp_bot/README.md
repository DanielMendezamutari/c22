# Microservicio Autónomo de WhatsApp (Baileys) con Zero-Leakage Guard

Este servicio corre en segundo plano monitoreando exclusivamente los grupos de WhatsApp vinculados a las sucursales de Casa22 (`sucursal_whatsapp_grupos`).

## Características
1. **Zero-Leakage Privacy Guard**: Al utilizar inicialmente un número personal (de Daniel), el bot descarga periódicamente la lista blanca de grupos autorizados (`GET /api/v1/whatsapp/grupos-auditables`). Cualquier mensaje de un chat personal, familiar o grupo no registrado es **descartado de inmediato en memoria local a 0ms** sin procesarse ni enviarse a internet.
2. **Extracción Automática**: Descarga silenciosamente planillas de caja manuscritas, vouchers bancarios de depósito y recibos de gastos, convirtiéndolos a Base64 y enviándolos a `POST /api/v1/webhook/whatsapp`.
3. **Comando de Detección de JIDs**:
   ```bash
   npm run list-groups
   ```
   Muestra una tabla con el nombre de cada grupo en WhatsApp y su `remote_jid` inmutable (ej: `1203630283921@g.us`), listo para vincular en el sistema.

## Puesta en Marcha

1. Instalar dependencias:
   ```bash
   cd scripts/whatsapp_bot
   npm install
   ```

2. Configurar variables de entorno:
   ```bash
   cp .env.example .env
   ```

3. Iniciar el bot y escanear el código QR:
   ```bash
   npm start
   ```
   Aparecerá un código QR en la consola para vincular desde WhatsApp en tu celular (Dispositivos vinculados > Vincular un dispositivo).
