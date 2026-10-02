<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { axiosIns } from '@/plugins/axios'

const props = defineProps({
  sucursales: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['resync-success'])

const logs = ref([])
const isLoadingLogs = ref(false)
const isResyncing = ref(false)
const selectedTurnoId = ref(null)
const logFilter = ref('all')
const logSearch = ref('')
const autoScroll = ref(true)
const logContainerRef = ref(null)
const snackbar = ref({ show: false, message: '', color: 'success' })
const apiLatency = ref(null)

const activeTurnos = computed(() => {
  const list = []
  props.sucursales.forEach(s => {
    if (s.turno_activo && s.turno_activo.estado === 'abierto') {
      list.push({
        id: s.turno_activo.id,
        label: `Turno #${s.turno_activo.id} - ${s.nombre} (${s.turno_activo.barman?.nombre_completo || 'Barman'})`,
        sucursal: s.nombre,
      })
    }
  })
  return list
})

const filteredLogs = computed(() => {
  return logs.value.filter(item => {
    const matchesFilter = logFilter.value === 'all' || item.nivel === logFilter.value
    const matchesSearch = !logSearch.value || item.mensaje.toLowerCase().includes(logSearch.value.toLowerCase())
    return matchesFilter && matchesSearch
  })
})

const fetchLogs = async () => {
  isLoadingLogs.value = true
  const startTime = Date.now()
  try {
    const res = await axiosIns.get('/sistema/logs-en-vivo')
    apiLatency.value = Date.now() - startTime
    if (res.data?.success) {
      logs.value = res.data.logs || []
      if (autoScroll.value && logContainerRef.value) {
        setTimeout(() => {
          logContainerRef.value.scrollTop = logContainerRef.value.scrollHeight
        }, 100)
      }
    }
  } catch (err) {
    console.error('Error fetching live logs:', err)
  } finally {
    isLoadingLogs.value = false
  }
}

const forzarResincronizacion = async () => {
  if (!selectedTurnoId.value) {
    snackbar.value = {
      show: true,
      message: 'Selecciona primero una sucursal con turno activo para resincronizar.',
      color: 'warning',
    }
    return
  }

  isResyncing.value = true
  try {
    const res = await axiosIns.post(`/turnos/${selectedTurnoId.value}/forzar-resincronizacion`)
    if (res.data?.success) {
      snackbar.value = {
        show: true,
        message: res.data.message || 'Turno resincronizado exitosamente.',
        color: 'success',
      }
      emit('resync-success', res.data.data)
      await fetchLogs()
    } else {
      throw new Error(res.data?.error || 'No se pudo completar la resincronización.')
    }
  } catch (err) {
    snackbar.value = {
      show: true,
      message: err.response?.data?.error || err.message || 'Error al ejecutar resincronización.',
      color: 'error',
    }
  } finally {
    isResyncing.value = false
  }
}

const clearLogs = () => {
  logs.value = []
}

let logInterval = null
onMounted(() => {
  fetchLogs()
  // Actualizar logs cada 30 segundos
  logInterval = setInterval(fetchLogs, 30000)
})

onUnmounted(() => {
  if (logInterval) clearInterval(logInterval)
})
</script>

<template>
  <VCard class="mb-6 technical-console-card">
    <VCardItem class="border-b">
      <template #prepend>
        <VAvatar
          color="error"
          variant="tonal"
          rounded
          size="42"
          class="me-3"
        >
          <VIcon
            icon="ri-terminal-box-line"
            size="24"
          />
        </VAvatar>
      </template>

      <VCardTitle class="d-flex align-center gap-2 flex-wrap">
        <span>Consola Técnica de Super Usuario (Ing. Daniel)</span>
        <VChip
          color="error"
          size="x-small"
          label
        >
          Acceso Root
        </VChip>
        <VChip
          v-if="apiLatency !== null"
          color="success"
          size="x-small"
          variant="outlined"
        >
          Latencia API: {{ apiLatency }}ms
        </VChip>
      </VCardTitle>

      <VCardSubtitle>
        Herramientas de emergencia, auditoría de logs de API y reconciliación forzada de turnos desfasados.
      </VCardSubtitle>
    </VCardItem>

    <VCardText class="pt-4">
      <!-- Barra de Acciones de Emergencia -->
      <VRow
        align="center"
        class="mb-4"
      >
        <VCol
          cols="12"
          md="6"
        >
          <VSelect
            v-model="selectedTurnoId"
            :items="activeTurnos"
            item-title="label"
            item-value="id"
            label="Turno Activo a Resincronizar"
            placeholder="Selecciona sucursal..."
            density="compact"
            prepend-inner-icon="ri-store-2-line"
            hide-details
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
          class="d-flex gap-2 justify-end"
        >
          <VBtn
            color="warning"
            variant="elevated"
            :loading="isResyncing"
            :disabled="!selectedTurnoId"
            @click="forzarResincronizacion"
          >
            <VIcon
              start
              icon="ri-refresh-line"
            />
            Forzar Resincronización
          </VBtn>

          <VBtn
            color="primary"
            variant="tonal"
            :loading="isLoadingLogs"
            @click="fetchLogs"
          >
            <VIcon
              start
              icon="ri-history-line"
            />
            Refrescar Logs
          </VBtn>
        </VCol>
      </VRow>

      <!-- Consola de Logs con apariencia de Terminal -->
      <div class="terminal-wrapper rounded-lg">
        <div class="terminal-header d-flex align-center justify-space-between px-4 py-2 border-b">
          <div class="d-flex align-center gap-2">
            <span class="terminal-dot bg-error" />
            <span class="terminal-dot bg-warning" />
            <span class="terminal-dot bg-success" />
            <span class="text-caption font-mono text-disabled ms-2">c22-backend-stream.log</span>
          </div>

          <div class="d-flex align-center gap-3">
            <VTextField
              v-model="logSearch"
              placeholder="Filtrar por texto..."
              density="compact"
              variant="plain"
              hide-details
              prepend-inner-icon="ri-search-line"
              style="max-width: 180px;"
              class="font-mono text-caption"
            />

            <VBtn
              size="x-small"
              variant="text"
              color="error"
              @click="clearLogs"
            >
              Limpiar
            </VBtn>
          </div>
        </div>

        <div
          ref="logContainerRef"
          class="terminal-body pa-4 font-mono text-caption"
          style="max-height: 260px; overflow-y: auto;"
        >
          <div
            v-if="filteredLogs.length === 0"
            class="text-disabled text-center py-4"
          >
            Sin registros de logs en este momento.
          </div>

          <div
            v-for="(log, idx) in filteredLogs"
            :key="idx"
            class="log-line mb-1"
          >
            <span class="text-disabled">[{{ log.timestamp }}]</span>
            <span
              class="ms-2 font-weight-bold"
              :class="{
                'text-info': log.nivel === 'info',
                'text-warning': log.nivel === 'warning',
                'text-error': log.nivel === 'error',
              }"
            >
              [{{ log.nivel?.toUpperCase() || 'LOG' }}]
            </span>
            <span class="ms-2 text-white">{{ log.mensaje }}</span>
          </div>
        </div>
      </div>
    </VCardText>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top right"
      :timeout="4000"
    >
      {{ snackbar.message }}
    </VSnackbar>
  </VCard>
</template>

<style scoped>
.technical-console-card {
  border-left: 4px solid rgb(var(--v-theme-error));
}

.terminal-wrapper {
  background-color: #0f141c;
  color: #e6edf3;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.terminal-header {
  background-color: #161b22;
}

.terminal-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.font-mono {
  font-family: 'Fira Code', 'Courier New', Courier, monospace;
}

.log-line {
  line-height: 1.5;
  word-break: break-all;
}
</style>
