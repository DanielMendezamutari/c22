<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { axiosIns } from '@/plugins/axios'

// Estados del Bot
const botStatus = ref({
  estado: 'desconectado',
  qr_code_data_url: null,
  telefono: null,
  ultimo_ping: null,
  desconectar_solicitado: false,
})
const loadingStatus = ref(false)

// Catálogo de Sucursales
const sucursales = ref([])
const loadingSucursales = ref(false)

// Grupos descubiertos y asignaciones
const grupos = ref([])
const loadingGrupos = ref(false)
const filtroBusqueda = ref('')
const guardandoGrupoId = ref(null)

// Ingesta Inbound reciente
const mensajesInbound = ref([])
const loadingInbound = ref(false)

// Diálogo y feedback
const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})
const dialogDesconectar = ref(false)
const desconectando = ref(false)

// Modal de Previsualización de Imagen
const dialogPreview = ref(false)
const previewImageSrc = ref('')

const tiposAuditoria = [
  { value: 'cierre_recaudacion', title: 'Cierres y Recaudación' },
  { value: 'gastos_caja_chica', title: 'Gastos de Caja Chica' },
  { value: 'taxis_rotacion', title: 'Taxis y Rotación' },
  { value: 'general', title: 'General / Otros' },
]

// Polling de Estado del Bot
let statusPollingTimer = null

const mostrarSnackbar = (text, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

const cargarSucursales = async () => {
  loadingSucursales.value = true
  try {
    const res = await axiosIns.get('/sucursales')
    if (res.data && res.data.data) {
      sucursales.value = res.data.data.map(s => ({
        value: s.id,
        title: s.nombre,
      }))
    }
  } catch (err) {
    console.error('Error cargando sucursales:', err)
  } finally {
    loadingSucursales.value = false
  }
}

const cargarBotStatus = async () => {
  try {
    const res = await axiosIns.get('/whatsapp/bot-status')
    if (res.data && res.data.data) {
      botStatus.value = res.data.data
    }
  } catch (err) {
    console.warn('Error consultando estado del bot:', err)
  }
}

const cargarGruposDisponibles = async () => {
  loadingGrupos.value = true
  try {
    const res = await axiosIns.get('/whatsapp/grupos-disponibles')
    if (res.data && res.data.data) {
      // Normalizar datos locales para binding reactivo
      grupos.value = res.data.data.map(g => ({
        ...g,
        sucursal_id_edit: g.sucursal_id || null,
        tipo_auditoria_edit: g.tipo_auditoria || 'cierre_recaudacion',
        activo_edit: g.vinculado ? g.activo : true,
      }))
    }
  } catch (err) {
    console.error('Error cargando grupos:', err)
  } finally {
    loadingGrupos.value = false
  }
}

const cargarMensajesInbound = async () => {
  loadingInbound.value = true
  try {
    const res = await axiosIns.get('/whatsapp/mensajes-recientes')
    if (res.data && res.data.data) {
      mensajesInbound.value = res.data.data
    }
  } catch (err) {
    console.warn('Error cargando mensajes inbound:', err)
  } finally {
    loadingInbound.value = false
  }
}

const guardarVinculacion = async (item) => {
  if (!item.sucursal_id_edit) {
    mostrarSnackbar('Selecciona una sucursal para este grupo.', 'warning')
    return
  }

  guardandoGrupoId.value = item.remote_jid
  try {
    const payload = {
      sucursal_id: item.sucursal_id_edit,
      remote_jid: item.remote_jid,
      nombre_grupo: item.nombre_grupo,
      tipo_auditoria: item.tipo_auditoria_edit,
      activo: item.activo_edit,
    }

    const res = await axiosIns.post('/sucursal-whatsapp-grupos', payload)
    if (res.data && res.data.success) {
      item.vinculado = true
      item.sucursal_id = item.sucursal_id_edit
      item.tipo_auditoria = item.tipo_auditoria_edit
      item.activo = item.activo_edit
      const sucursalSel = sucursales.value.find(s => s.value === item.sucursal_id_edit)
      item.sucursal_nombre = sucursalSel ? sucursalSel.title : 'Asignada'

      mostrarSnackbar(`Grupo "${item.nombre_grupo}" vinculado exitosamente.`)
    }
  } catch (err) {
    console.error('Error guardando vinculación:', err)
    mostrarSnackbar(err.response?.data?.message || 'Error al guardar vinculación', 'error')
  } finally {
    guardandoGrupoId.value = null
  }
}

const confirmarDesconexion = async () => {
  desconectando.value = true
  try {
    const res = await axiosIns.post('/whatsapp/desconectar')
    if (res.data && res.data.success) {
      dialogDesconectar.value = false
      mostrarSnackbar('Solicitud de desconexión enviada. Esperando nuevo código QR...')
      await cargarBotStatus()
    }
  } catch (err) {
    mostrarSnackbar('Error al solicitar desconexión.', 'error')
  } finally {
    desconectando.value = false
  }
}

const gruposFiltrados = computed(() => {
  if (!filtroBusqueda.value.trim()) return grupos.value
  const query = filtroBusqueda.value.toLowerCase()
  return grupos.value.filter(g =>
    (g.nombre_grupo || '').toLowerCase().includes(query) ||
    (g.sucursal_nombre || '').toLowerCase().includes(query) ||
    (g.remote_jid || '').toLowerCase().includes(query)
  )
})

const abrirPreview = (url) => {
  if (url) {
    previewImageSrc.value = url
    dialogPreview.value = true
  }
}

onMounted(async () => {
  await Promise.all([
    cargarSucursales(),
    cargarBotStatus(),
    cargarGruposDisponibles(),
    cargarMensajesInbound(),
  ])

  // Polling cada 4s para actualizar estado QR o mensajes
  statusPollingTimer = setInterval(async () => {
    await cargarBotStatus()
    // Si acaba de conectarse y no hay grupos, recargarlos
    if (botStatus.value.estado === 'conectado' && grupos.value.length === 0) {
      await cargarGruposDisponibles()
    }
  }, 4000)
})

onUnmounted(() => {
  if (statusPollingTimer) clearInterval(statusPollingTimer)
})
</script>

<template>
  <div class="whatsapp-gestion-page">
    <!-- Encabezado de Página -->
    <VRow class="mb-4 align-center">
      <VCol cols="12" md="8">
        <h2 class="text-h4 font-weight-bold d-flex align-center gap-2">
          <VIcon icon="tabler-brand-whatsapp" color="success" size="36" />
          Gestión y Auditoría de WhatsApp
        </h2>
        <p class="text-subtitle-1 text-medium-emphasis mb-0">
          Monitoreo del bot autónomo, vinculación por código QR y asignación determinista de grupos a sucursales.
        </p>
      </VCol>
      <VCol cols="12" md="4" class="text-md-end">
        <VBtn
          color="primary"
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loadingGrupos || loadingStatus"
          @click="() => { cargarBotStatus(); cargarGruposDisponibles(); cargarMensajesInbound(); }"
        >
          Refrescar Datos
        </VBtn>
      </VCol>
    </VRow>

    <VRow>
      <!-- ========================================== -->
      <!-- TARJETA 1: ESTADO DEL BOT & VISOR QR EN VIVO -->
      <!-- ========================================== -->
      <VCol cols="12" lg="4">
        <VCard elevation="2" class="h-100">
          <VCardItem class="border-b">
            <template #prepend>
              <VIcon icon="tabler-access-point" size="24" color="primary" />
            </template>
            <VCardTitle class="text-h6 font-weight-bold">Estado del Servicio</VCardTitle>
            <VCardSubtitle>Conexión Baileys Multi-Dispositivo</VCardSubtitle>
          </VCardItem>

          <VCardText class="pa-6 text-center">
            <!-- ESTADO: CONECTADO -->
            <div v-if="botStatus.estado === 'conectado'" class="py-4">
              <VAvatar color="success" variant="tonal" size="72" class="mb-3">
                <VIcon icon="tabler-circle-check" size="44" />
              </VAvatar>
              <h3 class="text-h5 font-weight-bold text-success mb-1">WhatsApp Vinculado</h3>
              <p class="text-body-1 font-weight-medium mb-3">
                {{ botStatus.telefono ? `+${botStatus.telefono}` : 'Dispositivo Maestro Activo' }}
              </p>

              <VChip color="success" variant="flat" size="small" class="mb-4">
                <VIcon start icon="tabler-shield-check" size="14" />
                Zero-Leakage Guard Activo
              </VChip>

              <p class="text-caption text-medium-emphasis mb-6">
                El bot escucha silenciosamente los grupos configurados sin interferir con chats personales.
              </p>

              <VBtn
                color="error"
                variant="outlined"
                prepend-icon="tabler-unlink"
                block
                @click="dialogDesconectar = true"
              >
                Desvincular / Cambiar Teléfono
              </VBtn>
            </div>

            <!-- ESTADO: ESPERANDO ESCANEO QR -->
            <div v-else-if="botStatus.estado === 'esperando_qr'" class="py-2">
              <VChip color="warning" variant="tonal" size="small" class="mb-3">
                <VIcon start icon="tabler-qrcode" size="14" />
                Esperando Escaneo de QR
              </VChip>

              <h4 class="text-h6 font-weight-bold mb-2">Escanea este código QR</h4>
              <p class="text-caption text-medium-emphasis mb-4">
                En tu WhatsApp: Ajustes > Dispositivos vinculados > Vincular un dispositivo
              </p>

              <div v-if="botStatus.qr_code_data_url" class="qr-container mb-4">
                <img
                  :src="botStatus.qr_code_data_url"
                  alt="Código QR WhatsApp"
                  class="qr-image elevation-3 rounded"
                />
              </div>
              <div v-else class="pa-8 text-medium-emphasis">
                <VProgressCircular indeterminate color="warning" class="mb-2" />
                <p class="text-caption mb-0">Generando código QR...</p>
              </div>

              <p class="text-caption text-warning d-flex align-center justify-center gap-1">
                <VIcon icon="tabler-clock" size="14" />
                El código se actualiza automáticamente.
              </p>
            </div>

            <!-- ESTADO: DESCONECTADO -->
            <div v-else class="py-6">
              <VAvatar color="secondary" variant="tonal" size="72" class="mb-3">
                <VIcon icon="tabler-wifi-off" size="44" />
              </VAvatar>
              <h3 class="text-h6 font-weight-bold mb-1">Bot Desconectado</h3>
              <p class="text-caption text-medium-emphasis mb-4">
                El microservicio en Node.js no está en ejecución o está iniciando.
              </p>
              <VAlert type="info" variant="tonal" density="compact" class="text-start text-caption">
                Ejecuta en el servidor:
                <code>pm2 start scripts/whatsapp_bot/bot.js</code>
              </VAlert>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- ========================================== -->
      <!-- TARJETA 2: MAPEO VISUAL DE GRUPOS A SUCURSALES -->
      <!-- ========================================== -->
      <VCol cols="12" lg="8">
        <VCard elevation="2" class="h-100">
          <VCardItem class="border-b">
            <template #prepend>
              <VIcon icon="tabler-folders" size="24" color="primary" />
            </template>
            <VCardTitle class="text-h6 font-weight-bold">Asignación de Grupos a Sucursales</VCardTitle>
            <VCardSubtitle>Mapea cada grupo de WhatsApp para auditoría automática</VCardSubtitle>
          </VCardItem>

          <VCardText class="pa-4">
            <!-- Barra de Búsqueda -->
            <VRow class="mb-2">
              <VCol cols="12">
                <VTextField
                  v-model="filtroBusqueda"
                  density="compact"
                  variant="outlined"
                  placeholder="Buscar grupo por nombre o sucursal..."
                  prepend-inner-icon="tabler-search"
                  hide-details
                  clearable
                />
              </VCol>
            </VRow>

            <!-- Tabla de Grupos -->
            <VTable density="comfortable" class="tabla-grupos border rounded">
              <thead>
                <tr>
                  <th class="font-weight-bold">Grupo de WhatsApp</th>
                  <th class="font-weight-bold" style="min-width: 170px;">Sucursal Destino</th>
                  <th class="font-weight-bold" style="min-width: 180px;">Tipo de Auditoría</th>
                  <th class="font-weight-bold text-center" style="width: 90px;">Activo</th>
                  <th class="font-weight-bold text-center" style="width: 110px;">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loadingGrupos">
                  <td colspan="5" class="text-center pa-6">
                    <VProgressCircular indeterminate color="primary" class="me-2" />
                    Cargando grupos detectados...
                  </td>
                </tr>
                <tr v-else-if="gruposFiltrados.length === 0">
                  <td colspan="5" class="text-center pa-6 text-medium-emphasis">
                    <VIcon icon="tabler-folder-off" size="32" class="mb-2" /><br />
                    No se encontraron grupos disponibles. Conecta el bot para detectar grupos de WhatsApp.
                  </td>
                </tr>
                <tr
                  v-for="grupo in gruposFiltrados"
                  :key="grupo.remote_jid"
                  :class="{ 'bg-var-theme-surface': grupo.vinculado }"
                >
                  <td>
                    <div class="font-weight-bold text-body-1">{{ grupo.nombre_grupo }}</div>
                    <div class="text-caption text-medium-emphasis d-flex align-center gap-1">
                      <VIcon icon="tabler-users" size="14" />
                      {{ grupo.participantes_count }} participantes
                      <VChip
                        v-if="grupo.vinculado"
                        color="success"
                        size="x-small"
                        variant="tonal"
                        class="ms-2"
                      >
                        Vinculado
                      </VChip>
                      <VChip
                        v-else
                        color="secondary"
                        size="x-small"
                        variant="tonal"
                        class="ms-2"
                      >
                        Pendiente
                      </VChip>
                    </div>
                  </td>
                  <td>
                    <VSelect
                      v-model="grupo.sucursal_id_edit"
                      :items="sucursales"
                      density="compact"
                      variant="outlined"
                      placeholder="Seleccionar..."
                      hide-details
                    />
                  </td>
                  <td>
                    <VSelect
                      v-model="grupo.tipo_auditoria_edit"
                      :items="tiposAuditoria"
                      density="compact"
                      variant="outlined"
                      hide-details
                    />
                  </td>
                  <td class="text-center">
                    <VSwitch
                      v-model="grupo.activo_edit"
                      color="success"
                      density="compact"
                      hide-details
                      class="d-inline-flex"
                    />
                  </td>
                  <td class="text-center">
                    <VBtn
                      color="primary"
                      size="small"
                      variant="elevated"
                      :loading="guardandoGrupoId === grupo.remote_jid"
                      @click="guardarVinculacion(grupo)"
                    >
                      Guardar
                    </VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ========================================== -->
    <!-- TARJETA 3: FEED DE INGESTA INBOUND RECIENTE -->
    <!-- ========================================== -->
    <VRow class="mt-4">
      <VCol cols="12">
        <VCard elevation="2">
          <VCardItem class="border-b">
            <template #prepend>
              <VIcon icon="tabler-photo-check" size="24" color="primary" />
            </template>
            <VCardTitle class="text-h6 font-weight-bold">Bandeja de Ingesta Inbound Reciente</VCardTitle>
            <VCardSubtitle>Planillas físicas, vouchers bancarios y recibos recibidos por WhatsApp</VCardSubtitle>
          </VCardItem>

          <VCardText class="pa-4">
            <VTable density="comfortable">
              <thead>
                <tr>
                  <th class="font-weight-bold" style="width: 80px;">Foto</th>
                  <th class="font-weight-bold">Fecha / Hora</th>
                  <th class="font-weight-bold">Sucursal</th>
                  <th class="font-weight-bold">Remitente</th>
                  <th class="font-weight-bold">Clasificación IA</th>
                  <th class="font-weight-bold">Estado</th>
                  <th class="font-weight-bold">Detalle OCR Extraído</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loadingInbound">
                  <td colspan="7" class="text-center pa-6">
                    <VProgressCircular indeterminate color="primary" class="me-2" />
                    Cargando historial de mensajes...
                  </td>
                </tr>
                <tr v-else-if="mensajesInbound.length === 0">
                  <td colspan="7" class="text-center pa-6 text-medium-emphasis">
                    No se han recibido mensajes recientes en los grupos auditados.
                  </td>
                </tr>
                <tr v-for="msg in mensajesInbound" :key="msg.id">
                  <td>
                    <VAvatar
                      v-if="msg.media_path"
                      rounded
                      size="48"
                      class="cursor-pointer border elevation-1"
                      @click="abrirPreview(msg.media_path)"
                    >
                      <VImg :src="msg.media_path" cover />
                    </VAvatar>
                    <VIcon v-else icon="tabler-file-text" size="28" color="secondary" />
                  </td>
                  <td class="text-caption font-weight-medium">
                    {{ msg.created_at || 'Reciente' }}
                  </td>
                  <td>
                    <VChip
                      :color="msg.sucursal_id ? 'primary' : 'warning'"
                      size="small"
                      variant="tonal"
                    >
                      {{ msg.sucursal_nombre }}
                    </VChip>
                  </td>
                  <td>
                    <div class="font-weight-medium text-body-2">{{ msg.sender_name || 'Encargada' }}</div>
                    <div class="text-caption text-medium-emphasis">+{{ msg.sender_phone }}</div>
                  </td>
                  <td>
                    <VChip
                      v-if="msg.clasificacion_ia === 'planilla_caja'"
                      color="info"
                      size="small"
                      variant="flat"
                    >
                      Planilla Caja
                    </VChip>
                    <VChip
                      v-else-if="msg.clasificacion_ia === 'voucher_deposito'"
                      color="success"
                      size="small"
                      variant="flat"
                    >
                      Voucher Banco
                    </VChip>
                    <VChip
                      v-else-if="msg.clasificacion_ia === 'recibo_gasto'"
                      color="warning"
                      size="small"
                      variant="flat"
                    >
                      Recibo Gasto
                    </VChip>
                    <VChip v-else color="secondary" size="small" variant="tonal">
                      {{ msg.clasificacion_ia || 'Pendiente' }}
                    </VChip>
                  </td>
                  <td>
                    <VChip
                      :color="msg.estado === 'procesado' ? 'success' : msg.estado === 'requiere_confirmacion' ? 'warning' : 'primary'"
                      size="small"
                      variant="tonal"
                    >
                      {{ msg.estado }}
                    </VChip>
                  </td>
                  <td>
                    <div v-if="msg.metadata_ia" class="text-caption font-mono">
                      <span v-if="msg.metadata_ia.total_efectivo_declarado_bs">
                        Efectivo: <strong>{{ msg.metadata_ia.total_efectivo_declarado_bs }} Bs</strong> |
                      </span>
                      <span v-if="msg.metadata_ia.monto_depositado_bs">
                        Depósito: <strong>{{ msg.metadata_ia.monto_depositado_bs }} Bs</strong> |
                      </span>
                      <span v-if="msg.metadata_ia.banco_destino">
                        {{ msg.metadata_ia.banco_destino }}
                      </span>
                    </div>
                    <span v-else class="text-caption text-medium-emphasis">
                      {{ msg.raw_text ? msg.raw_text.substring(0, 40) + '...' : 'Procesamiento en curso' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Modal de Confirmación de Desconexión -->
    <VDialog v-model="dialogDesconectar" max-width="450">
      <VCard>
        <VCardTitle class="text-h6 font-weight-bold pa-4 border-b">
          ¿Desvincular WhatsApp actual?
        </VCardTitle>
        <VCardText class="pa-4">
          Esta acción cerrará la sesión activa del bot en el dispositivo actual y generará de inmediato un nuevo código QR en pantalla para que puedas vincular otro teléfono.
        </VCardText>
        <VCardActions class="pa-4 border-t justify-end">
          <VBtn variant="plain" @click="dialogDesconectar = false">Cancelar</VBtn>
          <VBtn color="error" :loading="desconectando" @click="confirmarDesconexion">
            Sí, Desvincular
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de Previsualización de Imagen -->
    <VDialog v-model="dialogPreview" max-width="650">
      <VCard class="pa-2 text-center bg-black">
        <img :src="previewImageSrc" alt="Vista previa de comprobante" style="max-width: 100%; max-height: 80vh; object-fit: contain;" />
        <VBtn icon="tabler-x" variant="text" color="white" class="position-absolute top-0 right-0 ma-2" @click="dialogPreview = false" />
      </VCard>
    </VDialog>

    <!-- Snackbar Global -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top end">
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.qr-container {
  display: inline-block;
  padding: 8px;
  background-color: white;
  border-radius: 8px;
}

.qr-image {
  width: 100%;
  max-width: 260px;
  height: auto;
  display: block;
}

.tabla-grupos th {
  background-color: rgba(var(--v-theme-surface-variant), 0.3);
}
</style>
