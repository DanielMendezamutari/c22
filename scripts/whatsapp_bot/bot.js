import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  makeCacheableSignalKeyStore,
  downloadMediaMessage,
  fetchLatestBaileysVersion,
  proto,
  BufferJSON,
  normalizeMessageContent,
  isPnUser,
  generateMessageIDV2,
} from '@whiskeysockets/baileys';
import terminalQr from 'qrcode-terminal';
import QRCode from 'qrcode';
import axios from 'axios';
import http from 'http';
import dotenv from 'dotenv';
import pino from 'pino';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
// Carpeta del bot aunque se ejecute el paquete compilado dist/bot.mjs
const BOT_DIR = path.basename(__dirname) === 'dist' ? path.dirname(__dirname) : __dirname;

dotenv.config();

const LARAVEL_API_URL = process.env.LARAVEL_API_URL || 'https://c22.ribersoft.com/api/v1';
const WEBHOOK_SECRET = process.env.WHATSAPP_WEBHOOK_SECRET || 'puntofrio_wh_secret_2026_super';
const SYNC_INTERVAL_MS = parseInt(process.env.SYNC_INTERVAL_MS || '300000', 10);
const AUTH_DIR = process.env.AUTH_DIR || './auth_info_baileys';
const IS_LIST_GROUPS_MODE = process.argv.includes('--list-groups');
const BOT_HTTP_PORT = parseInt(process.env.BOT_HTTP_PORT || '3001', 10);
const OUTBOUND_STORE_PATH = process.env.OUTBOUND_STORE_PATH || path.join(BOT_DIR, 'outbound_store.json');

let currentSock = null;
let activeSock = null;
let socketGeneration = 0;
let restartTimer = null;
let whitelistTimer = null;
let monitorDesconexionTimer = null;

function limpiarIntervals() {
  clearInterval(whitelistTimer);
  clearInterval(monitorDesconexionTimer);
  whitelistTimer = null;
  monitorDesconexionTimer = null;
}

function reiniciarBot(delayMs, motivo) {
  if (restartTimer !== null) return;

  const generation = ++socketGeneration;
  limpiarIntervals();
  const anterior = activeSock;
  activeSock = null;
  currentSock = null;
  if (anterior) {
    try { anterior.end(new Error(motivo)); } catch (err) {
      console.warn(`[REINICIO WARN] No se pudo cerrar el socket anterior: ${err.message}`);
    }
    anterior.ev.removeAllListeners?.();
  }

  console.log(`[REINICIO] ${motivo}. Nueva conexión en ${delayMs} ms (generación ${generation}).`);
  restartTimer = setTimeout(() => {
    restartTimer = null;
    startWhatsAppBot(generation).catch((err) => {
      if (generation !== socketGeneration) return;
      console.error('[ERROR INICIANDO BOT]', err);
      reiniciarBot(5000, 'Error al iniciar conexión');
    });
  }, delayMs);
}

// Logger silencioso
const logger = pino({ level: process.env.LOG_LEVEL || 'warn' });

// Whitelist en memoria de grupos auditables (Zero-Leakage Guard)
let auditableGroupsMap = new Map(); // remote_jid -> { sucursal_id, sucursal_nombre, tipo_auditoria }

// Solo mensajes enviados: persisten entre reconexiones y reinicios del proceso.
const messageStore = new Map();
try {
  const guardados = JSON.parse(fs.readFileSync(OUTBOUND_STORE_PATH, 'utf8'), BufferJSON.reviver);
  for (const [id, message] of Object.entries(guardados).slice(-500)) {
    messageStore.set(id, message);
  }
} catch (err) {
  if (err.code !== 'ENOENT') console.warn(`[OUTBOUND STORE WARN] No se pudo cargar el almacén: ${err.message}`);
}

// IDs generados antes de enviar: Baileys emite el eco propio (process.nextTick) antes de que
// sendMessage resuelva, así que el ID debe conocerse de antemano para no re-ingestar el informe.
const idsPropios = new Set();
function nuevoIdPropio() {
  const id = generateMessageIDV2(currentSock?.user?.id);
  idsPropios.add(id);
  if (idsPropios.size > 1000) idsPropios.delete(idsPropios.values().next().value);
  return id;
}

function guardarMensajeEnStore(keyId, message) {
  if (!keyId || !message) return;
  messageStore.delete(keyId);
  messageStore.set(keyId, message);
  while (messageStore.size > 500) {
    const firstKey = messageStore.keys().next().value;
    messageStore.delete(firstKey);
  }
  // Escritura inmediata y atómica: el informe debe poder reenviarse aunque el proceso caiga
  // justo después de responder "enviado" (los envíos son pocos, el costo es despreciable).
  try {
    fs.writeFileSync(`${OUTBOUND_STORE_PATH}.tmp`, JSON.stringify(Object.fromEntries(messageStore), BufferJSON.replacer));
    fs.renameSync(`${OUTBOUND_STORE_PATH}.tmp`, OUTBOUND_STORE_PATH);
  } catch (err) {
    console.error(`[OUTBOUND STORE ERROR] No se pudo persistir el almacén: ${err.message}`);
  }
}

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
async function sincronizarGruposDescubiertos(sock, groupMetadataCache = null) {
  try {
    const chats = await sock.groupFetchAllParticipating();
    if (groupMetadataCache && chats) {
      for (const [jid, groupData] of Object.entries(chats)) {
        groupMetadataCache.set(jid, groupData);
      }
    }
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
async function startWhatsAppBot(generation) {
  if (generation !== socketGeneration) return;
  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
  }

  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
  const { version, isLatest } = await fetchLatestBaileysVersion();
  console.log(`[WHATSAPP BOT] Iniciando Baileys v${version.join('.')} (Última: ${isLatest})...`);

  // Sincronizar whitelist inicial
  await refrescarWhitelistGrupos();
  if (generation !== socketGeneration) return;
  whitelistTimer = setInterval(refrescarWhitelistGrupos, SYNC_INTERVAL_MS);

  const msgRetryCounterCache = new Map();
  const groupMetadataCache = new Map();

  const sock = makeWASocket({
    version,
    auth: {
      creds: state.creds,
      keys: makeCacheableSignalKeyStore(state.keys, logger),
    },
    logger,
    printQRInTerminal: false,
    generateHighQualityLinkPreview: false,
    markOnlineOnConnect: true,
    cachedGroupMetadata: async (jid) => {
      if (groupMetadataCache.has(jid)) {
        return groupMetadataCache.get(jid);
      }
      try {
        const metadata = await sock.groupMetadata(jid);
        groupMetadataCache.set(jid, metadata);
        return metadata;
      } catch (err) {
        return undefined;
      }
    },
    msgRetryCounterCache: {
      get: (key) => msgRetryCounterCache.get(key),
      set: (key, val) => msgRetryCounterCache.set(key, val),
      del: (key) => msgRetryCounterCache.delete(key),
    },
    getMessage: async (key) => {
      if (key?.id && messageStore.has(key.id)) {
        return proto.Message.fromObject(messageStore.get(key.id));
      }
      return undefined;
    },
  });
  activeSock = sock;

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('groups.update', async ([event]) => {
    if (event?.id) {
      try {
        const metadata = await sock.groupMetadata(event.id);
        groupMetadataCache.set(event.id, metadata);
      } catch (e) {}
    }
  });

  sock.ev.on('group-participants.update', async (event) => {
    if (event?.id) {
      try {
        const metadata = await sock.groupMetadata(event.id);
        groupMetadataCache.set(event.id, metadata);
      } catch (e) {}
    }
  });

  // Monitor para desconexión remota solicitada desde la web
  let desconexionSolicitada = false;

  sock.ev.on('connection.update', async (update) => {
    if (generation !== socketGeneration) return;
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

    if (generation !== socketGeneration) return;
    if (connection === 'close') {
      currentSock = null;
      limpiarIntervals();
      if (desconexionSolicitada) {
        await reportarEstadoBot('desconectado', null, null);
        return;
      }

      const statusCode = (lastDisconnect?.error)?.output?.statusCode;
      console.log(`[CONEXIÓN CERRADA] Código: ${statusCode}.`);

      if (statusCode === DisconnectReason.connectionReplaced) {
        console.warn('[CONFLICTO] Otra instancia usa esta sesión');
        reiniciarBot(60000, 'Conflicto de sesión; esperar 60 segundos');
      } else if (statusCode === DisconnectReason.loggedOut) {
        console.warn('[SESIÓN CERRADA] Sesión invalidada. Limpiando credenciales para nuevo QR...');
        try {
          fs.rmSync(AUTH_DIR, { recursive: true, force: true });
        } catch (e) {}
        reiniciarBot(3000, 'Sesión cerrada; solicitar nuevo QR');
      } else {
        reiniciarBot(5000, 'Conexión cerrada');
      }
      await reportarEstadoBot('desconectado', null, null);
    } else if (connection === 'open') {
      currentSock = sock;
      const telefono = (sock.user?.id || '').split(':')[0].split('@')[0] || 'Conectado';
      console.log('\n[CONECTADO CON ÉXITO]');
      console.log(`Usuario WhatsApp: +${telefono}`);
      console.log(`Zero-Leakage Guard ACTIVO: Solo se monitorean ${auditableGroupsMap.size} grupos registrados.`);

      // Notificar conexión a Laravel con número
      await reportarEstadoBot('conectado', null, telefono);
      if (generation !== socketGeneration) return;

      // Sincronizar automáticamente grupos descubiertos a la base de datos
      await sincronizarGruposDescubiertos(sock, groupMetadataCache);
      if (generation !== socketGeneration) return;
      setTimeout(() => {
        if (generation === socketGeneration) sincronizarGruposDescubiertos(sock, groupMetadataCache);
      }, 6000);
      setTimeout(() => {
        if (generation === socketGeneration) sincronizarGruposDescubiertos(sock, groupMetadataCache);
      }, 25000);

      // Activar sondeo para escuchar si el usuario pide desconectar o sincronizar desde la Web
      let pingContador = 0;
      clearInterval(monitorDesconexionTimer);
      monitorDesconexionTimer = setInterval(async () => {
        if (generation !== socketGeneration || desconexionSolicitada) return;
        try {
          pingContador++;
          // Heartbeat periódico a Laravel cada 60 segundos para evitar que expire la caché
          if (pingContador % 6 === 0) {
            await reportarEstadoBot('conectado', null, telefono);
          }
          if (generation !== socketGeneration) return;

          const res = await axios.get(`${LARAVEL_API_URL}/whatsapp/bot-status`, {
            headers: { 'X-Webhook-Secret': WEBHOOK_SECRET },
            timeout: 5000,
          });
          if (generation !== socketGeneration || desconexionSolicitada) return;
          if (res.data?.data?.desconectar_solicitado === true) {
            console.log('[COMANDO WEB] Desconexión solicitada desde la Plataforma Web.');
            desconexionSolicitada = true;
            limpiarIntervals();
            try {
              await sock.logout();
            } catch (e) {
              console.warn(`[LOGOUT WARN] ${e.message}`);
            }
            if (generation !== socketGeneration) return;
            try {
              fs.rmSync(AUTH_DIR, { recursive: true, force: true });
            } catch (e) {}
            reiniciarBot(3000, 'Desconexión solicitada desde la web');
            return;
          }

          if (res.data?.data?.sync_grupos_solicitado === true) {
            console.log('[COMANDO WEB] Re-escaneo de grupos solicitado desde la Web.');
            await sincronizarGruposDescubiertos(sock);
          }
        } catch (e) {}
      }, 10000);

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

  // Registro de mensajes procesados para deduplicación
  const processedMessageIds = new Set();
  function yaFueProcesado(id) {
    if (!id) return false;
    if (processedMessageIds.has(id)) return true;
    processedMessageIds.add(id);
    if (processedMessageIds.size > 2000) {
      const first = processedMessageIds.values().next().value;
      processedMessageIds.delete(first);
    }
    return false;
  }

  /**
   * Procesa y retransmite un mensaje auditado hacia el webhook de Laravel
   */
  async function procesarMensajeEntrante(msg) {
    if (!msg?.message) return;
    const content = normalizeMessageContent(msg.message);
    if (!content) return;

    // Ignorar envíos del bot por ID, conservando el filtro de textos existente.
    if (msg.key?.fromMe) {
      if (idsPropios.has(msg.key.id) || messageStore.has(msg.key.id)) return;
      const txt = content.conversation || content.extendedTextMessage?.text || '';
      if (txt.includes('🤖 [C22 BOT AUDITOR]') || txt.includes('[AUDITORÍA FORENSE]') || txt.includes('Cifrado y sincronización')) {
        return; // Ignorar informe o aviso emitido por el propio bot para evitar bucle
      }
    }

    const messageId = msg.key?.id;
    if (yaFueProcesado(messageId)) {
      return;
    }

    const remoteJid = msg.key.remoteJid;
    if (!remoteJid) return;

    // =========================================================================
    // ZERO-LEAKAGE PRIVACY GUARD:
    // Descarte en memoria local. Si el JID no es un grupo registrado en Laravel,
    // NUNCA se procesa, nunca se almacena, nunca se retransmite.
    // Protege 100% los chats personales y familiares del administrador.
    // =========================================================================
    if (!auditableGroupsMap.has(remoteJid)) {
      return;
    }

    const grupoConfig = auditableGroupsMap.get(remoteJid);
    const participantRaw = msg.key.participantAlt && isPnUser(msg.key.participantAlt)
      ? msg.key.participantAlt
      : msg.key.participant || remoteJid;
    const senderPhone = participantRaw.split('@')[0].split(':')[0];
    const senderName = msg.pushName || 'Encargada';
    const timestamp = msg.messageTimestamp ? Number(msg.messageTimestamp) : Math.floor(Date.now() / 1000);

    let tipoMensaje = 'texto';
    let caption = '';
    let mimetype = 'text/plain';
    let buffer = null;

    if (content.imageMessage) {
      tipoMensaje = 'imagen';
      caption = content.imageMessage.caption || '';
      mimetype = content.imageMessage.mimetype || 'image/jpeg';
      try {
        buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
      } catch (err) {
        console.error(`[ERROR DESCARGA IMAGEN] ${err.message}`);
      }
    } else if (content.documentMessage) {
      tipoMensaje = 'documento';
      caption = content.documentMessage.caption || content.documentMessage.fileName || '';
      mimetype = content.documentMessage.mimetype || 'application/pdf';
      try {
        buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
      } catch (err) {
        console.error(`[ERROR DESCARGA DOC] ${err.message}`);
      }
    } else if (content.extendedTextMessage) {
      tipoMensaje = 'texto';
      caption = content.extendedTextMessage.text || '';
    } else if (content.conversation) {
      tipoMensaje = 'texto';
      caption = content.conversation || '';
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

  // Escucha de mensajes entrantes (en vivo y sincronizados recientes)
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    const nowSec = Math.floor(Date.now() / 1000);
    for (const msg of messages) {
      if (type === 'append') {
        const msgTime = msg.messageTimestamp ? Number(msg.messageTimestamp) : 0;
        if (msgTime < nowSec - 7200) continue; // Ignorar historial viejo de más de 2h
      }
      await procesarMensajeEntrante(msg);
    }
  });

  // Escucha de reintentos descifrados exitosamente (Fix para cuando llega tras Signal Handshake)
  sock.ev.on('messages.update', async (updates) => {
    for (const { key, update } of updates) {
      if (update?.message && !key.fromMe) {
        console.log(`[RETRY DECRYPTED] Mensaje ${key.id} descifrado post-reintento en ${key.remoteJid}`);
        const reconstructedMsg = {
          key,
          message: update.message,
          messageTimestamp: Math.floor(Date.now() / 1000),
          pushName: update.pushName || 'Encargada',
        };
        await procesarMensajeEntrante(reconstructedMsg);
      }
    }
  });
}

// =========================================================================
// SERVIDOR HTTP LOCAL OUTBOUND (Despacho de informes y control remoto)
// =========================================================================
const httpServer = http.createServer((req, res) => {
  res.setHeader('Content-Type', 'application/json');

  if (req.method === 'GET' && req.url === '/status') {
    res.writeHead(200);
    return res.end(JSON.stringify({
      success: true,
      conectado: !!currentSock,
      usuario: currentSock?.user?.id ? (currentSock.user.id.split(':')[0].split('@')[0]) : null,
      grupos_registrados: auditableGroupsMap.size,
    }));
  }

  if (req.method === 'POST' && req.url === '/enviar-mensaje') {
    const secret = req.headers['x-internal-secret'] || req.headers['x-webhook-secret'];
    if (secret !== WEBHOOK_SECRET) {
      res.writeHead(403);
      return res.end(JSON.stringify({ success: false, error: 'Unauthorized secret' }));
    }

    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', async () => {
      try {
        const data = JSON.parse(body || '{}');
        const { remote_jid, texto, media_url, media_path } = data;

        if (!remote_jid || (!texto && !media_url && !media_path)) {
          res.writeHead(400);
          return res.end(JSON.stringify({ success: false, error: 'remote_jid y texto o media_url/media_path son requeridos' }));
        }

        if (!currentSock) {
          res.writeHead(503);
          return res.end(JSON.stringify({ success: false, error: 'Bot no conectado a WhatsApp actualmente' }));
        }

        let sentMsg;
        const targetMedia = media_path || media_url;
        if (targetMedia) {
          let mediaBuffer = null;
          const isPdf = targetMedia.toLowerCase().endsWith('.pdf');

          // Intentar resolver desde disco local primero para cero dependencia de red en cPanel
          if (media_path) {
            const cleanPath = media_path.replace(/^\/?storage\//, '').replace(/^public\//, '');
            const candidatos = [
              path.resolve(BOT_DIR, '../../backend/public', media_path),
              path.resolve(BOT_DIR, '../../backend/storage/app/public', cleanPath),
              path.resolve(BOT_DIR, '../../backend/public/storage', cleanPath),
              path.resolve(process.cwd(), '../../backend/public/storage', cleanPath),
            ];
            for (const c of candidatos) {
              if (fs.existsSync(c)) {
                try {
                  mediaBuffer = fs.readFileSync(c);
                  console.log(`[OUTBOUND MEDIA] Archivo local cargado exitosamente desde: ${c} (${mediaBuffer.length} bytes)`);
                  break;
                } catch (e) {}
              }
            }
          }

          try {
            if (isPdf) {
              const docPayload = mediaBuffer ? mediaBuffer : { url: media_url };
              sentMsg = await currentSock.sendMessage(remote_jid, {
                document: docPayload,
                mimetype: 'application/pdf',
                fileName: 'informe_cierre_auditoria.pdf',
                caption: texto || '',
              }, { messageId: nuevoIdPropio() });
            } else {
              const imgPayload = mediaBuffer ? mediaBuffer : { url: media_url };
              sentMsg = await currentSock.sendMessage(remote_jid, {
                image: imgPayload,
                caption: texto || '',
              }, { messageId: nuevoIdPropio() });
            }
          } catch (mediaErr) {
            console.warn(`[OUTBOUND MEDIA FALLBACK] Error al enviar media (${mediaErr.message}), despachando solo texto...`);
            sentMsg = await currentSock.sendMessage(remote_jid, { text: texto }, { messageId: nuevoIdPropio() });
          }
        } else {
          sentMsg = await currentSock.sendMessage(remote_jid, { text: texto }, { messageId: nuevoIdPropio() });
        }

        if (sentMsg?.key?.id && sentMsg?.message) {
          guardarMensajeEnStore(sentMsg.key.id, sentMsg.message);
        }

        console.log(`[OUTBOUND ENVIADO] Mensaje despachado a ${remote_jid} (ID: ${sentMsg?.key?.id})`);

        res.writeHead(200);
        return res.end(JSON.stringify({
          success: true,
          messageId: sentMsg?.key?.id,
          remote_jid,
        }));
      } catch (err) {
        console.error('[OUTBOUND ERROR]', err.message);
        res.writeHead(500);
        return res.end(JSON.stringify({ success: false, error: err.message }));
      }
    });
    return;
  }

  if (req.method === 'POST' && req.url === '/purgar-sender-keys') {
    const secret = req.headers['x-internal-secret'] || req.headers['x-webhook-secret'];
    if (secret !== WEBHOOK_SECRET) {
      res.writeHead(403);
      return res.end(JSON.stringify({ success: false, error: 'Unauthorized secret' }));
    }

    try {
      console.log('[PURGA SENDER KEYS] Purgando sender-key* de auth_info_baileys para regenerar claves grupales limpias...');
      if (fs.existsSync(AUTH_DIR)) {
        const files = fs.readdirSync(AUTH_DIR);
        let purgedCount = 0;
        for (const file of files) {
          if (file.startsWith('sender-key')) {
            try {
              fs.unlinkSync(path.join(AUTH_DIR, file));
              purgedCount++;
            } catch (e) {}
          }
        }
        console.log(`[PURGA SENDER KEYS] ${purgedCount} archivos sender-key eliminados.`);
      }

      reiniciarBot(2000, 'Purga sender keys completada');

      res.writeHead(200);
      return res.end(JSON.stringify({
        success: true,
        message: 'Sender keys purgadas y reconexión limpia iniciada',
      }));
    } catch (e) {
      res.writeHead(500);
      return res.end(JSON.stringify({ success: false, error: e.message }));
    }
  }

  if (req.method === 'POST' && req.url === '/reconectar') {
    const secret = req.headers['x-internal-secret'] || req.headers['x-webhook-secret'];
    if (secret !== WEBHOOK_SECRET) {
      res.writeHead(403);
      return res.end(JSON.stringify({ success: false, error: 'Unauthorized secret' }));
    }

    try {
      console.log('[RECONEXIÓN MANUAL] Solicitada vía HTTP local');
      reiniciarBot(2000, 'Reconexión manual');
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true, message: 'Reconexión iniciada' }));
    } catch (e) {
      res.writeHead(500);
      return res.end(JSON.stringify({ success: false, error: e.message }));
    }
  }

  res.writeHead(404);
  res.end(JSON.stringify({ success: false, error: 'Ruta no encontrada' }));
});

httpServer.listen(BOT_HTTP_PORT, '127.0.0.1', () => {
  console.log(`[BOT HTTP LOCAL] Servidor interno outbound activo en http://127.0.0.1:${BOT_HTTP_PORT}`);
});

// Iniciar microservicio
reiniciarBot(0, 'Inicio del microservicio');

