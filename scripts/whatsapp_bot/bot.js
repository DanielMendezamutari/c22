import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  downloadMediaMessage,
  fetchLatestBaileysVersion,
} from '@whiskeysockets/baileys';
import terminalQr from 'qrcode-terminal';
import QRCode from 'qrcode';
import axios from 'axios';
import dotenv from 'dotenv';
import pino from 'pino';
import fs from 'fs';

dotenv.config();

const LARAVEL_API_URL = process.env.LARAVEL_API_URL || 'https://c22.ribersoft.com/api/v1';
const WEBHOOK_SECRET = process.env.WHATSAPP_WEBHOOK_SECRET || 'puntofrio_wh_secret_2026_super';
const SYNC_INTERVAL_MS = parseInt(process.env.SYNC_INTERVAL_MS || '300000', 10);
const AUTH_DIR = process.env.AUTH_DIR || './auth_info_baileys';
const IS_LIST_GROUPS_MODE = process.argv.includes('--list-groups');

// Logger silencioso
const logger = pino({ level: process.env.LOG_LEVEL || 'warn' });

// Whitelist en memoria de grupos auditables (Zero-Leakage Guard)
let auditableGroupsMap = new Map(); // remote_jid -> { sucursal_id, sucursal_nombre, tipo_auditoria }

/**
 * Notifica el estado del bot a Laravel (QR en base64, conexión o desconexión)
 */
async function reportarEstadoBot(estado, qrDataUrl = null, telefono = null) {
  try {
    await axios.post(`${LARAVEL_API_URL}/whatsapp/bot-status`, {
      estado,
      qr_code_data_url: qrDataUrl,
      telefono,
    }, {
      headers: {
        'Content-Type': 'application/json',
        'X-Webhook-Secret': WEBHOOK_SECRET,
      },
      timeout: 10000,
    });
    console.log(`[BOT ESTADO] Notificado a Laravel (${estado}) en ${LARAVEL_API_URL}`);
  } catch (err) {
    console.error(`[BOT ESTADO WARN] No se pudo reportar a Laravel (${LARAVEL_API_URL}):`, err.response?.data?.error || err.message);
  }
}

/**
 * Consulta la whitelist dinámica de grupos auditables desde el backend Laravel
 */
async function refrescarWhitelistGrupos() {
  try {
    const url = `${LARAVEL_API_URL}/whatsapp/grupos-auditables`;
    const res = await axios.get(url, {
      headers: {
        'X-Webhook-Secret': WEBHOOK_SECRET,
      },
      timeout: 10000,
    });

    if (res.data && res.data.success && Array.isArray(res.data.data)) {
      const nuevoMapa = new Map();
      for (const item of res.data.data) {
        nuevoMapa.set(item.remote_jid, {
          sucursal_id: item.sucursal_id,
          sucursal_nombre: item.sucursal_nombre,
          tipo_auditoria: item.tipo_auditoria,
          nombre_grupo: item.nombre_grupo,
        });
      }
      auditableGroupsMap = nuevoMapa;
      console.log(`[WHITELIST] Sincronizados ${auditableGroupsMap.size} grupos auditables autorizados desde Laravel.`);
    }
  } catch (err) {
    console.warn(`[WHITELIST WARN] No se pudo refrescar grupos desde Laravel (${err.message}). Manteniendo ${auditableGroupsMap.size} grupos en memoria.`);
  }
}

/**
 * Sincroniza todos los grupos donde participa el WhatsApp hacia Laravel para visualización web
 */
async function sincronizarGruposDescubiertos(sock) {
  try {
    const chats = await sock.groupFetchAllParticipating();
    const groupList = Object.values(chats).map(g => ({
      jid: g.id,
      name: g.subject,
      participants: g.participants?.length || 0,
    }));

    await axios.post(`${LARAVEL_API_URL}/whatsapp/grupos-descubiertos`, {
      grupos: groupList,
    }, {
      headers: {
        'Content-Type': 'application/json',
        'X-Webhook-Secret': WEBHOOK_SECRET,
      },
      timeout: 15000,
    });

    console.log(`[GRUPOS DESCUBIERTOS] ${groupList.length} grupos sincronizados exitosamente con Laravel.`);
  } catch (err) {
    console.warn(`[GRUPOS WARN] No se pudieron sincronizar grupos descubiertos: ${err.message}`);
  }
}

/**
 * Conexión principal con Baileys
 */
async function startWhatsAppBot() {
  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
  }

  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
  const { version, isLatest } = await fetchLatestBaileysVersion();
  console.log(`[WHATSAPP BOT] Iniciando Baileys v${version.join('.')} (Última: ${isLatest})...`);

  // Sincronizar whitelist inicial
  await refrescarWhitelistGrupos();
  const whitelistTimer = setInterval(refrescarWhitelistGrupos, SYNC_INTERVAL_MS);

  const sock = makeWASocket({
    version,
    auth: state,
    logger,
    printQRInTerminal: false,
  });

  sock.ev.on('creds.update', saveCreds);

  // Monitor para desconexión remota solicitada desde la web
  let monitorDesconexionTimer = null;

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      console.log('\n======================================================');
      console.log('   CÓDIGO QR GENERADO (Disponible también en Plataforma Web)');
      console.log('======================================================\n');
      terminalQr.generate(qr, { small: true });

      try {
        const qrDataUrl = await QRCode.toDataURL(qr, { width: 350, margin: 2 });
        await reportarEstadoBot('esperando_qr', qrDataUrl, null);
      } catch (err) {
        console.error('Error generando QR data URL:', err.message);
      }
    }

    if (connection === 'close') {
      clearInterval(whitelistTimer);
      if (monitorDesconexionTimer) clearInterval(monitorDesconexionTimer);

      const statusCode = (lastDisconnect?.error)?.output?.statusCode;
      const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
      console.log(`[CONEXIÓN CERRADA] Código: ${statusCode}. Reintentando reconexión: ${shouldReconnect}`);

      await reportarEstadoBot('desconectado', null, null);

      if (shouldReconnect) {
        setTimeout(startWhatsAppBot, 5000);
      } else {
        console.warn('[SESIÓN CERRADA] Sesión invalidada. Limpiando credenciales para nuevo QR...');
        try {
          fs.rmSync(AUTH_DIR, { recursive: true, force: true });
        } catch (e) {}
        setTimeout(startWhatsAppBot, 3000);
      }
    } else if (connection === 'open') {
      const telefono = (sock.user?.id || '').split(':')[0].split('@')[0] || 'Conectado';
      console.log('\n[CONECTADO CON ÉXITO]');
      console.log(`Usuario WhatsApp: +${telefono}`);
      console.log(`Zero-Leakage Guard ACTIVO: Solo se monitorean ${auditableGroupsMap.size} grupos registrados.`);

      // Notificar conexión a Laravel con número
      await reportarEstadoBot('conectado', null, telefono);

      // Sincronizar automáticamente grupos descubiertos a la base de datos
      await sincronizarGruposDescubiertos(sock);

      // Activar sondeo para escuchar si el usuario pide desconectar desde la Web
      monitorDesconexionTimer = setInterval(async () => {
        try {
          const res = await axios.get(`${LARAVEL_API_URL}/whatsapp/bot-status`, {
            headers: { 'X-Webhook-Secret': WEBHOOK_SECRET },
            timeout: 5000,
          });
          if (res.data?.data?.desconectar_solicitado === true) {
            console.log('[COMANDO WEB] Desconexión solicitada desde la Plataforma Web.');
            clearInterval(monitorDesconexionTimer);
            try {
              await sock.logout();
            } catch (e) {
              sock.end(new Error('Logout forzado'));
            }
            try {
              fs.rmSync(AUTH_DIR, { recursive: true, force: true });
            } catch (e) {}
            setTimeout(startWhatsAppBot, 3000);
          }
        } catch (e) {}
      }, 15000);

      if (IS_LIST_GROUPS_MODE) {
        console.log('\n--- LISTADO DE GRUPOS DETECTADOS EN LA CUENTA ---');
        try {
          const chats = await sock.groupFetchAllParticipating();
          const groups = Object.values(chats);
          console.table(groups.map(g => ({
            Nombre: g.subject,
            JID: g.id,
            Participantes: g.participants?.length || 0,
            Auditado: auditableGroupsMap.has(g.id) ? 'SÍ (Activo)' : 'NO (Ignorado)'
          })));
          console.log('--------------------------------------------------\n');
        } catch (e) {
          console.error('Error listando grupos:', e.message);
        }
      }
    }
  });

  // Escucha de mensajes entrantes
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return;

    for (const msg of messages) {
      if (!msg.message || msg.key.fromMe) continue;

      const remoteJid = msg.key.remoteJid;
      if (!remoteJid) continue;

      // =========================================================================
      // ZERO-LEAKAGE PRIVACY GUARD:
      // Descarte en memoria local. Si el JID no es un grupo registrado en Laravel,
      // NUNCA se procesa, nunca se almacena, nunca se retransmite.
      // Protege 100% los chats personales y familiares del administrador.
      // =========================================================================
      if (!auditableGroupsMap.has(remoteJid)) {
        continue;
      }

      const grupoConfig = auditableGroupsMap.get(remoteJid);
      const senderPhone = (msg.key.participant || remoteJid).split('@')[0].replace(/\D/g, '');
      const senderName = msg.pushName || 'Encargada';
      const timestamp = msg.messageTimestamp || Math.floor(Date.now() / 1000);

      const messageContent = msg.message;
      let tipoMensaje = 'texto';
      let caption = '';
      let mimetype = 'text/plain';
      let buffer = null;

      if (messageContent.imageMessage) {
        tipoMensaje = 'imagen';
        caption = messageContent.imageMessage.caption || '';
        mimetype = messageContent.imageMessage.mimetype || 'image/jpeg';
        try {
          buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
        } catch (err) {
          console.error(`[ERROR DESCARGA IMAGEN] ${err.message}`);
        }
      } else if (messageContent.documentMessage) {
        tipoMensaje = 'documento';
        caption = messageContent.documentMessage.caption || messageContent.documentMessage.fileName || '';
        mimetype = messageContent.documentMessage.mimetype || 'application/pdf';
        try {
          buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
        } catch (err) {
          console.error(`[ERROR DESCARGA DOC] ${err.message}`);
        }
      } else if (messageContent.extendedTextMessage) {
        tipoMensaje = 'texto';
        caption = messageContent.extendedTextMessage.text || '';
      } else if (messageContent.conversation) {
        tipoMensaje = 'texto';
        caption = messageContent.conversation || '';
      }

      console.log(`[AUDITABLE MSG] Grupo: "${grupoConfig.nombre_grupo}" (${grupoConfig.sucursal_nombre}) | Emisor: ${senderName} (+${senderPhone}) | Tipo: ${tipoMensaje}`);

      // Retransmisión al webhook de Laravel
      try {
        const payload = {
          remote_jid: remoteJid,
          sender_phone: senderPhone,
          sender_name: senderName,
          message_timestamp: timestamp,
          tipo: tipoMensaje,
          caption: caption,
          mimetype: mimetype,
          media_base64: buffer ? `data:${mimetype};base64,${buffer.toString('base64')}` : null,
        };

        const response = await axios.post(`${LARAVEL_API_URL}/webhook/whatsapp`, payload, {
          headers: {
            'Content-Type': 'application/json',
            'X-Webhook-Secret': WEBHOOK_SECRET,
          },
          timeout: 30000,
        });

        console.log(`[WEBHOOK RETRANSMITIDO] Inbound ID: ${response.data.inbound_id} | Sucursal ID: ${response.data.sucursal_id} | Estado: ${response.data.status}`);
      } catch (err) {
        console.error(`[ERROR WEBHOOK] Falló retransmisión a Laravel: ${err.response?.data?.error || err.message}`);
      }
    }
  });
}

// Iniciar microservicio
startWhatsAppBot().catch((err) => {
  console.error('[FATAL ERROR IN BOT]', err);
});
