<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { axiosIns } from '@/plugins/axios'

// Estados del Bot y Daemon 24/7
const botStatus = ref({
  estado: 'desconectado',
  qr_code_data_url: null,
  telefono: null,
  ultimo_ping: null,
  desconectar_solicitado: false,
})
const loadingStatus = ref(false)
const reconectando = ref(false)

// Configuración Control de Recaudo y 4 Casas
const configRecaudo = ref(null)
const loadingConfigRecaudo = ref(false)

// Catálogo de Sucursales
const sucursales = ref([])
const loadingSucursales = ref(false)

// Grupos descubiertos y asignaciones
const grupos = ref([])
const loadingGrupos = ref(false)
const filtroBusqueda = ref('')
const guardandoGrupoId = ref(null)

// Filtros Rápidos y Paginación UX de Grupos
const tabFiltroGrupos = ref('sugeridos') // 'vinculados' | 'sugeridos' | 'todos'
const paginaActual = ref(1)
const itemsPorPagina = ref(10)
const solicitandoSync = ref(false)

const palabrasClaveNegocio = [
  'c22', 'casa', 'casas', '22', 'belen', 'belén', 'corona', 'madan',
  'adm', 'admin', 'administrac', 'gerencia', 'central', 'general',
  'cierre', 'cierres', 'recaud', 'recaudacion', 'recaudaciones',
  'arqueo', 'arqueos', 'taxi', 'taxis', 'rotacion', 'rotaciones',
  'turno', 'caja', 'joker', 'licoreria', 'ventas', 'venta', 'vip', 'sistema'
]

const esGrupoSugerido = (nombre) => {
  if (!nombre) return false
  const lower = nombre.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  return palabrasClaveNegocio.some(kw => {
    const kwNorm = kw.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    return lower.includes(kwNorm)
  })
}

const gruposVinculadosCount = computed(() => grupos.value.filter(g => g.vinculado).length)
const gruposSugeridosCount = computed(() => grupos.value.filter(g => esGrupoSugerido(g.nombre_grupo) || g.vinculado).length)

const aplicarFiltroChip = (texto) => {
  filtroBusqueda.value = texto
  paginaActual.value = 1
}

const limpiarFiltro = () => {
  filtroBusqueda.value = ''
  paginaActual.value = 1
}

const solicitarSyncGrupos = async () => {
  solicitandoSync.value = true
  try {
    const res = await axiosIns.post('/whatsapp/solicitar-sync-grupos')
    mostrarSnackbar(res.data?.message || 'Solicitud de re-escaneo enviada al bot de WhatsApp. Actualizando...')
    // Reintentar lectura tras dar unos segundos al socket de Baileys
    setTimeout(async () => {
      await cargarGruposDisponibles()
      solicitandoSync.value = false
    }, 4000)
  } catch (err) {
    console.error('Error al solicitar sync de grupos:', err)
    mostrarSnackbar('No se pudo enviar la solicitud de escaneo al bot.', 'error')
    solicitandoSync.value = false
  }
}

// Auditoría Cruzada Inter-Canales
const auditoriasCruce = ref([])
const loadingCruces = ref(false)
const dialogResolverCruce = ref(false)
const cruceSeleccionado = ref(null)
const formResolverCruce = ref({
  monto_definitivo_auditado: 0,
  imputar_responsable: 'cajera',
  observaciones: '',
})
const resolviendoCruce = ref(false)

// Despacho Manual de Informes
const dialogDespachoManual = ref(false)
const turnoIdDespacho = ref('')
const forzarReenvio = ref(false)
const despachandoInforme = ref(false)
const auditandoHoy = ref(false)

const ejecutarAuditoriaHoy = async () => {
  auditandoHoy.value = true
  try {
    const res = await axiosIns.post('/whatsapp/auditar-hoy', { forzar: true })
    mostrarSnackbar(res.data?.message || 'Auditoría de hoy ejecutada con éxito.')
    await Promise.all([
      cargarAuditoriasCruce(),
      cargarMensajesInbound(),
      cargarConfigRecaudo(),
    ])
  } catch (err) {
    console.error('Error al ejecutar auditoría de hoy:', err)
    mostrarSnackbar('Error al ejecutar auditoría de hoy.', 'error')
  } finally {
    auditandoHoy.value = false
  }
}

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
  { value: 'cierre_recaudacion', title: 'Cierres y Recaudación de Sucursal' },
  { value: 'control_recaudo_global', title: 'Control de Recaudo (Consolidado 4 Casas)' },
  { value: 'gastos_caja_chica', title: 'Gastos de Caja Chica' },
  { value: 'taxis_rotacion', title: 'Taxis y Rotación' },
  { value: 'general', title: 'General / Grupo Multi-Sucursal' },
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
      const items = res.data.data.map(s => ({
        value: s.id,
        title: s.nombre,
      }))
      // Agregar opción global para grupos multi-sucursal
      items.unshift({
        value: 0,
        title: '🌐 [Todas las Casas / Grupo Global]',
      })
      sucursales.value = items
    }
  } catch (err) {
    console.error('Error cargando sucursales:', err)
  } finally {
    loadingSucursales.value = false
  }
}

const cargarConfigRecaudo = async () => {
  loadingConfigRecaudo.value = true
  try {
    const res = await axiosIns.get('/whatsapp/config-control-recaudo')
    if (res.data && res.data.data) {
      configRecaudo.value = res.data.data
    }
  } catch (err) {
    console.warn('Error cargando configuración de control de recaudo:', err)
  } finally {
    loadingConfigRecaudo.value = false
  }
}

const cargarAuditoriasCruce = async () => {
  loadingCruces.value = true
  try {
    const res = await axiosIns.get('/whatsapp/auditorias-cruce')
    if (res.data && res.data.data) {
      auditoriasCruce.value = res.data.data.data || res.data.data || []
    }
  } catch (err) {
    console.warn('Error cargando auditorías de cruce:', err)
  } finally {
    loadingCruces.value = false
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
  if (item.sucursal_id_edit === undefined || item.sucursal_id_edit === null) {
    mostrarSnackbar('Selecciona una sucursal o Grupo Global para este grupo.', 'warning')
    return
  }

  guardandoGrupoId.value = item.remote_jid
  try {
    const esGlobal = item.sucursal_id_edit === 0
    const payload = {
      sucursal_id: esGlobal ? null : item.sucursal_id_edit,
      es_global: esGlobal,
      remote_jid: item.remote_jid,
      nombre_grupo: item.nombre_grupo,
      tipo_auditoria: item.tipo_auditoria_edit,
      activo: item.activo_edit,
    }

    const res = await axiosIns.post('/sucursal-whatsapp-grupos', payload)
    if (res.data && res.data.success) {
      item.vinculado = true
      item.sucursal_id = payload.sucursal_id
      item.tipo_auditoria = item.tipo_auditoria_edit
      item.activo = item.activo_edit
      const sucursalSel = sucursales.value.find(s => s.value === item.sucursal_id_edit)
      item.sucursal_nombre = sucursalSel ? sucursalSel.title : 'Asignada'

      mostrarSnackbar(`Grupo "${item.nombre_grupo}" configurado exitosamente.`)
      await cargarConfigRecaudo()
    }
  } catch (err) {
    console.error('Error guardando vinculación:', err)
    mostrarSnackbar(err.response?.data?.message || 'Error al guardar vinculación', 'error')
  } finally {
    guardandoGrupoId.value = null
  }
}

const solicitarReconexionBot = async () => {
  reconectando.value = true
  try {
    const res = await axiosIns.post('/whatsapp/reconectar')
    if (res.data && res.data.success) {
      mostrarSnackbar(res.data.message || 'Reconexión solicitada exitosamente.')
      await cargarBotStatus()
      await cargarConfigRecaudo()
    }
  } catch (err) {
    mostrarSnackbar('Error al solicitar reconexión del bot.', 'error')
  } finally {
    reconectando.value = false
  }
}

const abrirResolverCruce = (cruce) => {
  cruceSeleccionado.value = cruce
  formResolverCruce.value = {
    monto_definitivo_auditado: cruce.monto_declarado_local ?? cruce.monto_declarado_global ?? 0,
    imputar_responsable: 'cajera',
    observaciones: '',
  }
  dialogResolverCruce.value = true
}

const confirmarResolverCruce = async () => {
  if (!cruceSeleccionado.value) return
  resolviendoCruce.value = true
  try {
    const res = await axiosIns.post('/whatsapp/resolver-discrepancia-cruce', {
      cruce_id: cruceSeleccionado.value.id,
      monto_definitivo_auditado: parseFloat(formResolverCruce.value.monto_definitivo_auditado),
      imputar_responsable: formResolverCruce.value.imputar_responsable,
      observaciones: formResolverCruce.value.observaciones,
    })
    if (res.data && res.data.success) {
      mostrarSnackbar('Discrepancia inter-canales resuelta y asentada exitosamente.')
      dialogResolverCruce.value = false
      await cargarAuditoriasCruce()
    }
  } catch (err) {
    mostrarSnackbar(err.response?.data?.message || 'Error al resolver discrepancia.', 'error')
  } finally {
    resolviendoCruce.value = false
  }
}

const despacharInformeManual = async () => {
  if (!turnoIdDespacho.value) {
    mostrarSnackbar('Por favor ingresa el ID del turno a despachar.', 'warning')
    return
  }
  despachandoInforme.value = true
  try {
    const res = await axiosIns.post('/whatsapp/despachar-informe-cierre', {
      turno_id: parseInt(turnoIdDespacho.value),
      forzar_reenvio: forzarReenvio.value,
    })
    if (res.data && res.data.success) {
      mostrarSnackbar('Informe consolidado despachado con éxito al grupo Control de Recaudo.')
      dialogDespachoManual.value = false
      turnoIdDespacho.value = ''
    }
  } catch (err) {
    mostrarSnackbar(err.response?.data?.error || err.response?.data?.message || 'Error despachando informe.', 'error')
  } finally {
    despachandoInforme.value = false
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

const autoSugerirAsignaciones = () => {
  let sugeridos = 0
  
  // Buscar IDs de sucursales en el catálogo
  const idBelen = sucursales.value.find(s => s.title?.toLowerCase().includes('bel') || s.title?.toLowerCase().includes('c22'))?.value
  const idCorona = sucursales.value.find(s => s.title?.toLowerCase().includes('coron'))?.value
  const idMadan = sucursales.value.find(s => s.title?.toLowerCase().includes('madan'))?.value

  grupos.value.forEach(g => {
    if (g.vinculado) return
    const lower = (g.nombre_grupo || '').toLowerCase()

    let asigno = false
    // 1. Auto-detectar Sucursal
    if (lower.includes('adm') || lower.includes('central') || lower.includes('general') || (lower.includes('taxi') && !lower.includes('corona') && !lower.includes('madan'))) {
      g.sucursal_id_edit = 0 // Global / Multi-sucursal
      asigno = true
    } else if (lower.includes('corona')) {
      if (idCorona) { g.sucursal_id_edit = idCorona; asigno = true; }
    } else if (lower.includes('madan')) {
      if (idMadan) { g.sucursal_id_edit = idMadan; asigno = true; }
    } else if (lower.includes('c22') || lower.includes('casa 22') || lower.includes('casa22') || lower.includes('casas') || lower.includes('belen') || lower.includes('belén')) {
      if (idBelen) { g.sucursal_id_edit = idBelen; asigno = true; }
    } else if (lower.includes('recaud') || lower.includes('todas')) {
      g.sucursal_id_edit = 0 // Global
      asigno = true
    }

    // 2. Auto-detectar Tipo de Auditoría
    if (lower.includes('recaud') || lower.includes('control')) {
      g.tipo_auditoria_edit = 'control_recaudo_global'
    } else if (lower.includes('taxi') || lower.includes('rotacion')) {
      g.tipo_auditoria_edit = 'taxis_rotacion'
      if (g.sucursal_id_edit === null || g.sucursal_id_edit === undefined) g.sucursal_id_edit = 0
    } else if (lower.includes('adm') || lower.includes('general') || lower.includes('gerencia')) {
      g.tipo_auditoria_edit = 'general'
      if (g.sucursal_id_edit === null || g.sucursal_id_edit === undefined) g.sucursal_id_edit = 0
    } else if (lower.includes('gasto') || lower.includes('caja chica')) {
      g.tipo_auditoria_edit = 'gastos_caja_chica'
    } else if (lower.includes('cierre') || lower.includes('caja') || lower.includes('arqueo')) {
      g.tipo_auditoria_edit = 'cierre_recaudacion'
    }

    if (asigno) sugeridos++
  })

  tabFiltroGrupos.value = 'sugeridos'
  paginaActual.value = 1
  mostrarSnackbar(`🪄 Se pre-identificaron sugerencias para ${sugeridos} grupos. Revisa y pulsa 'Guardar'.`)
}

const gruposFiltrados = computed(() => {
  let list = grupos.value

  // Si hay término de búsqueda activo, buscar globalmente en TODOS los grupos para que NADA quede oculto
  if (filtroBusqueda.value && filtroBusqueda.value.trim()) {
    const query = filtroBusqueda.value.toLowerCase().trim()
    list = list.filter(g =>
      (g.nombre_grupo || '').toLowerCase().includes(query) ||
      (g.sucursal_nombre || '').toLowerCase().includes(query) ||
      (g.remote_jid || '').toLowerCase().includes(query)
    )
  } else {
    // Si no hay búsqueda, aplicar filtro por pestaña seleccionada
    if (tabFiltroGrupos.value === 'vinculados') {
      list = list.filter(g => g.vinculado)
    } else if (tabFiltroGrupos.value === 'sugeridos') {
      list = list.filter(g => esGrupoSugerido(g.nombre_grupo) || g.vinculado)
    }
  }

  // Ordenar: los vinculados siempre primero, luego alfabético
  return [...list].sort((a, b) => {
    if (a.vinculado && !b.vinculado) return -1
    if (!a.vinculado && b.vinculado) return 1
    return (a.nombre_grupo || '').localeCompare(b.nombre_grupo || '')
  })
})

const totalPaginas = computed(() => Math.ceil(gruposFiltrados.value.length / itemsPorPagina.value) || 1)

const gruposPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * itemsPorPagina.value
  return gruposFiltrados.value.slice(inicio, inicio + itemsPorPagina.value)
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
    cargarConfigRecaudo(),
    cargarGruposDisponibles(),
    cargarAuditoriasCruce(),
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
                color="primary"
                variant="tonal"
                prepend-icon="tabler-rotate-clockwise"
                block
                class="mb-3"
                :loading="reconectando"
                @click="solicitarReconexionBot"
              >
                Reconectar Servicio WhatsApp (24/7)
              </VBtn>

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

              <VBtn
                color="primary"
                variant="tonal"
                size="small"
                prepend-icon="tabler-rotate-clockwise"
                block
                class="mt-2 mb-3"
                :loading="reconectando"
                @click="solicitarReconexionBot"
              >
                Forzar Regeneración de QR
              </VBtn>

              <p class="text-caption text-warning d-flex align-center justify-center gap-1 mb-0">
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

              <VBtn
                color="success"
                variant="elevated"
                prepend-icon="tabler-player-play"
                block
                class="mb-3"
                :loading="reconectando"
                @click="solicitarReconexionBot"
              >
                Iniciar / Reconectar Servicio 24/7
              </VBtn>

              <VAlert type="info" variant="tonal" density="compact" class="text-start text-caption">
                Guardián automático activo:
                <code>bash scripts/whatsapp_bot/verificar_y_levantar.sh</code>
              </VAlert>
            </div>
          </VCardText>
        </VCard>

        <!-- TARJETA: ESTADO DE INTEGRACIONES MODULARES -->
        <VCard elevation="2" class="mt-4">
          <VCardItem class="border-b">
            <template #prepend>
              <VIcon icon="tabler-topology-star-3" size="24" color="info" />
            </template>
            <VCardTitle class="text-h6 font-weight-bold">Estado de Integraciones</VCardTitle>
            <VCardSubtitle>Ecosistema Modular 4 Casas</VCardSubtitle>
          </VCardItem>
          <VCardText class="pa-4">
            <div class="d-flex align-center justify-space-between mb-3 pb-2 border-b">
              <div>
                <div class="font-weight-bold d-flex align-center gap-1">
                  <VIcon icon="tabler-brand-whatsapp" color="success" size="18" />
                  WhatsApp Bot Autónomo
                </div>
                <div class="text-caption text-medium-emphasis">Monitoreo 24/7 de fotos y tickets</div>
              </div>
              <VChip color="success" size="small" variant="flat">Activo 24/7</VChip>
            </div>

            <div class="d-flex align-center justify-space-between mb-3 pb-2 border-b">
              <div>
                <div class="font-weight-bold d-flex align-center gap-1">
                  <VIcon icon="tabler-receipt-2" color="primary" size="18" />
                  POS RestoTech
                </div>
                <div class="text-caption text-medium-emphasis">Modo Solo WhatsApp (OCR de Fotos)</div>
              </div>
              <VChip color="info" size="small" variant="tonal">OCR Activo</VChip>
            </div>

            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="font-weight-bold d-flex align-center gap-1">
                  <VIcon icon="tabler-device-mobile" color="purple" size="18" />
                  App Móvil Barman / Cajera
                </div>
                <div class="text-caption text-medium-emphasis">Cortes de barra y apertura</div>
              </div>
              <VChip color="purple" size="small" variant="tonal">Integrado</VChip>
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
            <template #append>
              <div class="d-flex flex-wrap gap-2">
                <VBtn
                  color="secondary"
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-refresh"
                  :loading="solicitandoSync"
                  @click="solicitarSyncGrupos"
                >
                  🔄 Re-Escanear WhatsApp
                </VBtn>
                <VBtn
                  color="primary"
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-wand"
                  @click="autoSugerirAsignaciones"
                >
                  🪄 Auto-Detectar C22
                </VBtn>
              </div>
            </template>
          </VCardItem>

          <VCardText class="pa-4">
            <!-- Pestañas de Modo / Filtro Rápido -->
            <div class="d-flex flex-wrap gap-2 mb-3 align-center">
              <VBtn
                :color="tabFiltroGrupos === 'sugeridos' ? 'primary' : 'default'"
                :variant="tabFiltroGrupos === 'sugeridos' ? 'flat' : 'tonal'"
                size="small"
                prepend-icon="tabler-sparkles"
                @click="() => { tabFiltroGrupos = 'sugeridos'; paginaActual = 1; }"
              >
                🎯 Sugeridos Casa 22 ({{ gruposSugeridosCount }})
              </VBtn>

              <VBtn
                :color="tabFiltroGrupos === 'vinculados' ? 'success' : 'default'"
                :variant="tabFiltroGrupos === 'vinculados' ? 'flat' : 'tonal'"
                size="small"
                prepend-icon="tabler-check"
                @click="() => { tabFiltroGrupos = 'vinculados'; paginaActual = 1; }"
              >
                ⭐ Vinculados / Activos ({{ gruposVinculadosCount }})
              </VBtn>

              <VBtn
                :color="tabFiltroGrupos === 'todos' ? 'secondary' : 'default'"
                :variant="tabFiltroGrupos === 'todos' ? 'flat' : 'tonal'"
                size="small"
                prepend-icon="tabler-list"
                @click="() => { tabFiltroGrupos = 'todos'; paginaActual = 1; }"
              >
                🌐 Todos los Grupos ({{ grupos.length }})
              </VBtn>
            </div>

            <!-- Barra de Búsqueda -->
            <VRow class="mb-1">
              <VCol cols="12">
                <VTextField
                  v-model="filtroBusqueda"
                  density="compact"
                  variant="outlined"
                  placeholder="Buscar grupo por nombre o sucursal (ej. ADM, Corona, Taxi)..."
                  prepend-inner-icon="tabler-search"
                  hide-details
                  clearable
                  @update:model-value="paginaActual = 1"
                />
              </VCol>
            </VRow>

            <!-- Chips de Atajos Rápidos -->
            <div class="d-flex flex-wrap gap-1 mb-3 align-center">
              <span class="text-caption text-medium-emphasis me-1">Atajos:</span>
              <VChip size="x-small" variant="tonal" color="info" class="cursor-pointer" @click="aplicarFiltroChip('ADM')">ADM / Central</VChip>
              <VChip size="x-small" variant="tonal" color="primary" class="cursor-pointer" @click="aplicarFiltroChip('Casa')">Casa 22</VChip>
              <VChip size="x-small" variant="tonal" color="primary" class="cursor-pointer" @click="aplicarFiltroChip('Corona')">Corona</VChip>
              <VChip size="x-small" variant="tonal" color="primary" class="cursor-pointer" @click="aplicarFiltroChip('Madan')">Madan</VChip>
              <VChip size="x-small" variant="tonal" color="success" class="cursor-pointer" @click="aplicarFiltroChip('Cierres')">Cierres</VChip>
              <VChip size="x-small" variant="tonal" color="success" class="cursor-pointer" @click="aplicarFiltroChip('Recaudo')">Recaudo</VChip>
              <VChip size="x-small" variant="tonal" color="warning" class="cursor-pointer" @click="aplicarFiltroChip('Taxi')">Taxis</VChip>
              <VChip size="x-small" variant="tonal" color="warning" class="cursor-pointer" @click="aplicarFiltroChip('Rotaciones')">Rotaciones</VChip>
              <VChip size="x-small" variant="tonal" color="info" class="cursor-pointer" @click="aplicarFiltroChip('Venta')">Ventas</VChip>
              <VChip v-if="filtroBusqueda" size="x-small" variant="text" color="error" class="cursor-pointer" @click="limpiarFiltro">✖️ Quitar filtro</VChip>
            </div>

            <!-- Tabla de Grupos Paginada -->
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
                    No se encontraron grupos con este filtro. Cambia de pestaña o usa el botón "Todos los Grupos".
                  </td>
                </tr>
                <tr
                  v-for="grupo in gruposPaginados"
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
                        variant="flat"
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

            <!-- Barra de Paginación y Resumen -->
            <div class="d-flex flex-wrap align-center justify-space-between mt-3 px-1 gap-2">
              <div class="text-caption text-medium-emphasis">
                Mostrando {{ gruposFiltrados.length === 0 ? 0 : (paginaActual - 1) * itemsPorPagina + 1 }} a {{ Math.min(paginaActual * itemsPorPagina, gruposFiltrados.length) }} de {{ gruposFiltrados.length }} grupos
                <span v-if="tabFiltroGrupos !== 'todos'" class="font-italic">({{ tabFiltroGrupos }})</span>
              </div>

              <div class="d-flex align-center gap-2">
                <span class="text-caption text-medium-emphasis">Filas:</span>
                <VSelect
                  v-model="itemsPorPagina"
                  :items="[10, 20, 50]"
                  density="compact"
                  variant="outlined"
                  style="width: 85px;"
                  hide-details
                  @update:model-value="paginaActual = 1"
                />

                <VBtn
                  icon="tabler-chevron-left"
                  variant="tonal"
                  size="small"
                  :disabled="paginaActual <= 1"
                  @click="paginaActual--"
                />
                <span class="text-caption font-weight-medium">
                  {{ paginaActual }} / {{ totalPaginas }}
                </span>
                <VBtn
                  icon="tabler-chevron-right"
                  variant="tonal"
                  size="small"
                  :disabled="paginaActual >= totalPaginas"
                  @click="paginaActual++"
                />
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ========================================== -->
    <!-- TARJETA: CONTROL DE RECAUDO Y DESPACHO DE INFORMES (4 CASAS) -->
    <!-- ========================================== -->
    <VRow class="mt-4">
      <VCol cols="12">
        <VCard elevation="2">
          <VCardItem class="border-b">
            <template #prepend>
              <VIcon icon="tabler-send" size="24" color="success" />
            </template>
            <VCardTitle class="text-h6 font-weight-bold">
              Despacho Autónomo a "Control de Recaudo" (Consolidado 4 Casas)
            </VCardTitle>
            <VCardSubtitle>
              Informes ejecutivos automáticos al cierre de turno directo a WhatsApp
            </VCardSubtitle>
            <template #append>
              <div class="d-flex flex-wrap gap-2">
                <VBtn
                  color="primary"
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-bolt"
                  :loading="auditandoHoy"
                  @click="ejecutarAuditoriaHoy"
                >
                  ⚡ Auditar Cierres de Hoy
                </VBtn>
                <VBtn
                  color="success"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-paperplane"
                  @click="dialogDespachoManual = true"
                >
                  Despacho Manual
                </VBtn>
              </div>
            </template>
          </VCardItem>

          <VCardText class="pa-4">
            <VRow>
              <VCol cols="12" md="4">
                <div class="pa-3 border rounded bg-var-theme-surface">
                  <div class="text-caption text-medium-emphasis">Grupo Receptor Configurado</div>
                  <div class="font-weight-bold text-body-1 text-success d-flex align-center gap-1 mt-1">
                    <VIcon icon="tabler-brand-whatsapp" size="20" />
                    {{ configRecaudo?.grupo_asignado?.nombre_grupo || 'No asignado aún (Seleccionar en tabla)' }}
                  </div>
                  <div class="text-caption mt-1">
                    JID: <code>{{ configRecaudo?.grupo_asignado?.remote_jid || '---' }}</code>
                  </div>
                </div>
              </VCol>

              <VCol cols="12" md="4">
                <div class="pa-3 border rounded bg-var-theme-surface">
                  <div class="text-caption text-medium-emphasis">Casas Comerciales en Alcance</div>
                  <div class="d-flex flex-wrap gap-1 mt-1">
                    <VChip
                      v-for="casa in (configRecaudo?.casas_cubiertas || [])"
                      :key="casa.id"
                      size="small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ casa.nombre }}
                    </VChip>
                  </div>
                </div>
              </VCol>

              <VCol cols="12" md="4">
                <div class="pa-3 border rounded bg-var-theme-surface">
                  <div class="text-caption text-medium-emphasis">Daemon Guardián 24/7</div>
                  <div class="d-flex align-center gap-2 mt-1">
                    <VBadge dot color="success" class="me-1" />
                    <span class="font-weight-medium text-capitalize">
                      {{ configRecaudo?.daemon_24_7?.estado || botStatus.estado }}
                    </span>
                    <span class="text-caption text-medium-emphasis ms-auto">
                      Último pulso: {{ configRecaudo?.daemon_24_7?.ultimo_heartbeat || 'Activo' }}
                    </span>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ========================================== -->
    <!-- TARJETA: AUDITORÍA CRUZADA INTER-CANALES (US37) -->
    <!-- ========================================== -->
    <VRow class="mt-4">
      <VCol cols="12">
        <VCard elevation="2">
          <VCardItem class="border-b">
            <template #prepend>
              <VIcon icon="tabler-arrows-cross" size="24" color="warning" />
            </template>
            <VCardTitle class="text-h6 font-weight-bold">
              Auditoría Cruzada de Veracidad Inter-Canales
            </VCardTitle>
            <VCardSubtitle>
              Deduplicación SHA-256 y detección automática de diferencias entre grupo local y grupo general
            </VCardSubtitle>
            <template #append>
              <VBtn
                color="secondary"
                variant="text"
                size="small"
                icon="tabler-refresh"
                :loading="loadingCruces"
                @click="cargarAuditoriasCruce"
              />
            </template>
          </VCardItem>

          <VCardText class="pa-4">
            <VTable density="comfortable" class="border rounded">
              <thead>
                <tr>
                  <th class="font-weight-bold">Fecha / ID</th>
                  <th class="font-weight-bold">Sucursal</th>
                  <th class="font-weight-bold">Tipo Evento</th>
                  <th class="font-weight-bold text-end">Canal Local</th>
                  <th class="font-weight-bold text-end">Canal Global</th>
                  <th class="font-weight-bold text-end">Diferencia</th>
                  <th class="font-weight-bold text-center">Estado Veracidad</th>
                  <th class="font-weight-bold text-center">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loadingCruces">
                  <td colspan="8" class="text-center pa-6">
                    <VProgressCircular indeterminate color="warning" class="me-2" />
                    Analizando cruces inter-canales...
                  </td>
                </tr>
                <tr v-else-if="auditoriasCruce.length === 0">
                  <td colspan="8" class="text-center pa-6 text-medium-emphasis">
                    No se han registrado discrepancias o mensajes concurrentes aún.
                  </td>
                </tr>
                <tr v-for="cruce in auditoriasCruce" :key="cruce.id">
                  <td class="text-caption font-weight-medium">
                    #{{ cruce.id }} <br />
                    <span class="text-medium-emphasis">{{ cruce.created_at ? cruce.created_at.substring(0, 16) : '' }}</span>
                  </td>
                  <td>
                    <VChip size="small" color="primary" variant="tonal">
                      {{ cruce.sucursal?.nombre || 'Sucursal' }}
                    </VChip>
                  </td>
                  <td class="text-capitalize">{{ (cruce.tipo_evento || '').replace('_', ' ') }}</td>
                  <td class="text-end font-weight-bold">
                    {{ cruce.monto_declarado_local !== null ? `Bs ${parseFloat(cruce.monto_declarado_local).toFixed(2)}` : '---' }}
                  </td>
                  <td class="text-end font-weight-bold">
                    {{ cruce.monto_declarado_global !== null ? `Bs ${parseFloat(cruce.monto_declarado_global).toFixed(2)}` : '---' }}
                  </td>
                  <td class="text-end font-weight-bold" :class="{ 'text-error': Math.abs(cruce.diferencia_bs || 0) >= 0.01 }">
                    Bs {{ parseFloat(cruce.diferencia_bs || 0).toFixed(2) }}
                  </td>
                  <td class="text-center">
                    <VChip
                      v-if="cruce.estado_cruce === 'conforme_doble_canal'"
                      color="success"
                      size="small"
                      variant="flat"
                    >
                      Doble Canal OK
                    </VChip>
                    <VChip
                      v-else-if="cruce.estado_cruce === 'discrepancia_detectada'"
                      color="error"
                      size="small"
                      variant="flat"
                    >
                      Discrepancia
                    </VChip>
                    <VChip
                      v-else-if="cruce.estado_cruce === 'justificado'"
                      color="info"
                      size="small"
                      variant="flat"
                    >
                      Justificado
                    </VChip>
                    <VChip v-else color="secondary" size="small" variant="tonal">
                      {{ cruce.estado_cruce }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <VBtn
                      v-if="cruce.estado_cruce === 'discrepancia_detectada'"
                      color="warning"
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-gavel"
                      @click="abrirResolverCruce(cruce)"
                    >
                      Auditar
                    </VBtn>
                    <span v-else class="text-caption text-medium-emphasis">Conforme</span>
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
            <VCardSubtitle>Planillas físicas, tickets POS, vouchers bancarios y recibos recibidos por WhatsApp</VCardSubtitle>
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
                      v-else-if="msg.clasificacion_ia === 'ticket_pos'"
                      color="primary"
                      size="small"
                      variant="flat"
                    >
                      Ticket POS
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
                      <span v-if="msg.metadata_ia.total_ventas_declaradas_bs">
                        Ventas: <strong>{{ msg.metadata_ia.total_ventas_declaradas_bs }} Bs</strong> |
                      </span>
                      <span v-if="msg.metadata_ia.total_ticket_bs">
                        Ticket POS: <strong>{{ msg.metadata_ia.total_ticket_bs }} Bs</strong> |
                      </span>
                      <span v-if="msg.metadata_ia.qr_pos_bs">
                        QR POS: <strong>{{ msg.metadata_ia.qr_pos_bs }} Bs</strong> |
                      </span>
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

    <!-- Modal para Resolver Discrepancia de Cruce -->
    <VDialog v-model="dialogResolverCruce" max-width="500">
      <VCard>
        <VCardTitle class="text-h6 font-weight-bold pa-4 border-b">
          Auditar y Justificar Discrepancia Inter-Canales
        </VCardTitle>
        <VCardText class="pa-4">
          <p class="text-caption text-medium-emphasis mb-3">
            Fija el monto auditado definitivo y asienta la observación oficial en la auditoría inmutable.
          </p>

          <VTextField
            v-model="formResolverCruce.monto_definitivo_auditado"
            label="Monto Definitivo Auditado (Bs)"
            type="number"
            density="compact"
            variant="outlined"
            class="mb-3"
          />

          <VSelect
            v-model="formResolverCruce.imputar_responsable"
            label="Imputar Responsable"
            :items="[
              { title: 'Cajera / Encargada', value: 'cajera' },
              { title: 'Barman', value: 'barman' },
              { title: 'Error de Tipeo / Empresa', value: 'empresa' },
            ]"
            density="compact"
            variant="outlined"
            class="mb-3"
          />

          <VTextarea
            v-model="formResolverCruce.observaciones"
            label="Justificación / Observaciones de Auditoría"
            rows="3"
            density="compact"
            variant="outlined"
            placeholder="Ej: Se valida que el recibo real es por 300 Bs. Los 200 Bs de diferencia fueron un error de tipeo."
          />
        </VCardText>
        <VCardActions class="pa-4 border-t justify-end">
          <VBtn variant="plain" @click="dialogResolverCruce = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="resolviendoCruce" @click="confirmarResolverCruce">
            Guardar Dictamen
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal para Despacho Manual de Informe al Grupo Control de Recaudo -->
    <VDialog v-model="dialogDespachoManual" max-width="450">
      <VCard>
        <VCardTitle class="text-h6 font-weight-bold pa-4 border-b">
          Despachar Informe a Control de Recaudo
        </VCardTitle>
        <VCardText class="pa-4">
          <p class="text-caption text-medium-emphasis mb-3">
            Envía el informe ejecutivo formateado con alertas de discrepancia y cuadre al grupo de WhatsApp.
          </p>

          <VTextField
            v-model="turnoIdDespacho"
            label="ID del Turno de la Sucursal"
            type="number"
            density="compact"
            variant="outlined"
            placeholder="Ej: 1, 2, 45..."
            class="mb-3"
          />

          <VSwitch
            v-model="forzarReenvio"
            label="Forzar reenvío si ya fue despachado"
            color="success"
            density="compact"
            hide-details
          />
        </VCardText>
        <VCardActions class="pa-4 border-t justify-end">
          <VBtn variant="plain" @click="dialogDespachoManual = false">Cancelar</VBtn>
          <VBtn color="success" :loading="despachandoInforme" @click="despacharInformeManual">
            Enviar a WhatsApp
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

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
