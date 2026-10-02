<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { axiosIns } from '@/plugins/axios'
import ConsolaHerramientasTecnicas from '@/views/dashboard/ConsolaHerramientasTecnicas.vue'

const authStore = useAuthStore()

const loading = ref(false)
const dashboardData = ref(null)
const lastSyncTime = ref(null)
const secondsRemaining = ref(60)
const syncInterval = 60 // 60 segundos
let timerId = null
let countdownTimerId = null

const fetchDashboardData = async (isManual = false) => {
  if (isManual) loading.value = true
  try {
    const res = await axiosIns.get('/dashboard/metricas-tiempo-real')
    if (res.data?.success) {
      dashboardData.value = res.data
      lastSyncTime.value = new Date().toLocaleTimeString()
      secondsRemaining.value = syncInterval
    }
  } catch (err) {
    console.error('Error fetching live metrics:', err)
  } finally {
    if (isManual) loading.value = false
  }
}

const startTimers = () => {
  // Timer de polling cada 60s
  timerId = setInterval(() => {
    fetchDashboardData(false)
  }, syncInterval * 1000)

  // Timer de segundero decreciente para UI
  countdownTimerId = setInterval(() => {
    if (secondsRemaining.value > 0) {
      secondsRemaining.value -= 1
    } else {
      secondsRemaining.value = syncInterval
    }
  }, 1000)
}

const stopTimers = () => {
  if (timerId) clearInterval(timerId)
  if (countdownTimerId) clearInterval(countdownTimerId)
}

const sucursales = computed(() => dashboardData.value?.sucursales || [])
const resumen = computed(() => dashboardData.value?.resumen_general || {})

const getEstadoColor = (estadoCaja) => {
  switch (estadoCaja) {
    case 'online':
      return 'success'
    case 'inactivo':
      return 'warning'
    case 'alerta_desconexion':
      return 'error'
    default:
      return 'secondary'
  }
}

const getEstadoTexto = (estadoCaja) => {
  switch (estadoCaja) {
    case 'online':
      return 'Caja Conectada'
    case 'inactivo':
      return 'Sin actividad reciente'
    case 'alerta_desconexion':
      return 'Alerta Desconexión'
    default:
      return 'Caja Cerrada'
  }
}

onMounted(() => {
  fetchDashboardData(true)
  startTimers()
})

onUnmounted(() => {
  stopTimers()
})
</script>

<template>
  <div>
    <!-- Encabezado de la Plataforma Web en Tiempo Real -->
    <VCard class="mb-6 elevation-1">
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4 py-4">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="primary"
            variant="tonal"
            size="48"
            rounded
          >
            <VIcon
              icon="ri-dashboard-2-line"
              size="28"
            />
          </VAvatar>
          <div>
            <h3 class="text-h5 font-weight-bold mb-0">
              Control Operativo en Tiempo Real (Grupo Punto Frío)
            </h3>
            <span class="text-caption text-medium-emphasis">
              Monitoreo centralizado cada 60s &bull; Sesión: {{ authStore.userName }} ({{ authStore.user?.rol || 'Super Admin' }})
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-4 flex-wrap">
          <!-- Reloj dinámico de 60 segundos -->
          <div class="d-flex align-center gap-2 bg-surface px-3 py-2 rounded-lg border">
            <VProgressCircular
              :model-value="((60 - secondsRemaining) / 60) * 100"
              size="28"
              width="3"
              color="primary"
            >
              <span class="text-caption font-weight-bold" style="font-size: 10px;">{{ secondsRemaining }}</span>
            </VProgressCircular>
            <div>
              <div class="text-caption text-disabled" style="line-height: 1;">Próximo sync</div>
              <div class="text-caption font-weight-medium">{{ secondsRemaining }}s</div>
            </div>
          </div>

          <VBtn
            color="primary"
            variant="tonal"
            :loading="loading"
            prepend-icon="ri-refresh-line"
            @click="fetchDashboardData(true)"
          >
            Sincronizar Ahora
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- KPIs Globales Consolidados -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard class="h-100 border-s-lg border-primary">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="primary" variant="tonal" size="48" rounded>
              <VIcon icon="ri-drinks-line" size="26" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Botellas Transformadas</span>
              <h4 class="text-h4 font-weight-bold">{{ resumen.total_botellas_transformadas || 0 }}</h4>
              <span class="text-caption text-success font-weight-medium">Producción neta en barra</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="h-100 border-s-lg border-success">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="success" variant="tonal" size="48" rounded>
              <VIcon icon="ri-money-dollar-circle-line" size="26" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Comisiones Acumuladas</span>
              <h4 class="text-h4 font-weight-bold">{{ resumen.total_comisiones_devengadas_bs || 0 }} Bs</h4>
              <span class="text-caption text-medium-emphasis">Pendientes de liquidar</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="h-100 border-s-lg border-info">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="info" variant="tonal" size="48" rounded>
              <VIcon icon="ri-store-3-line" size="26" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Turnos en Servicio</span>
              <h4 class="text-h4 font-weight-bold">{{ resumen.total_turnos_activos || 0 }} / {{ resumen.total_sucursales || 3 }}</h4>
              <span class="text-caption text-info font-weight-medium">Casas operativas</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="h-100 border-s-lg border-error">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="error" variant="tonal" size="48" rounded>
              <VIcon icon="ri-alert-line" size="26" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Alertas Críticas</span>
              <h4 class="text-h4 font-weight-bold">{{ resumen.total_alertas_criticas || 0 }}</h4>
              <span class="text-caption text-error font-weight-medium">Discrepancias activas</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tarjetas de las 3 Casas (Casa22, Casa Coron, Madan) -->
    <div class="d-flex align-center justify-space-between mb-4">
      <h4 class="text-h5 font-weight-bold mb-0">
        Estado de Sucursales en Vivo
      </h4>
      <span class="text-caption text-disabled">
        Última actualización: {{ lastSyncTime || 'Conectando...' }}
      </span>
    </div>

    <VRow class="mb-6">
      <VCol
        v-for="sucursal in sucursales"
        :key="sucursal.id"
        cols="12"
        md="4"
      >
        <VCard class="h-100 position-relative">
          <VCardItem class="border-b pb-3">
            <template #prepend>
              <VAvatar
                :color="sucursal.turno_activo?.estado === 'abierto' ? 'primary' : 'secondary'"
                variant="tonal"
                size="40"
                class="me-2"
              >
                <VIcon icon="ri-building-line" size="22" />
              </VAvatar>
            </template>

            <VCardTitle class="text-h6 font-weight-bold">
              {{ sucursal.nombre }}
            </VCardTitle>

            <VCardSubtitle>
              Código: {{ sucursal.codigo }} &bull; {{ sucursal.direccion || 'Sede Operativa' }}
            </VCardSubtitle>

            <template #append>
              <VChip
                :color="getEstadoColor(sucursal.estado_caja)"
                size="x-small"
                variant="tonal"
              >
                {{ getEstadoTexto(sucursal.estado_caja) }}
              </VChip>
            </template>
          </VCardItem>

          <VCardText class="pt-4">
            <!-- Caso Turno Activo -->
            <div v-if="sucursal.turno_activo && sucursal.turno_activo.estado === 'abierto'">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption text-disabled">Turno en curso:</span>
                <VChip color="success" size="x-small">
                  Turno #{{ sucursal.turno_activo.id }} ({{ sucursal.turno_activo.tipo_turno }})
                </VChip>
              </div>

              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption text-disabled">Barman responsable:</span>
                <span class="text-body-2 font-weight-medium">
                  {{ sucursal.turno_activo.barman?.nombre_completo || 'No asignado' }}
                  <VBadge
                    v-if="sucursal.turno_activo.es_suplencia"
                    color="warning"
                    content="Suplencia Cajera"
                    inline
                    class="ms-1"
                  />
                </span>
              </div>

              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption text-disabled">Tiempo de jornada:</span>
                <span class="text-body-2 font-weight-medium text-primary">
                  {{ sucursal.turno_activo.tiempo_transcurrido }}
                </span>
              </div>

              <VDivider class="my-3" />

              <div class="d-flex justify-space-between text-caption mb-1">
                <span>Rellenos / Transformaciones:</span>
                <strong class="text-high-emphasis">{{ sucursal.turno_activo.total_transformaciones_netas }} unds</strong>
              </div>

              <div class="d-flex justify-space-between text-caption mb-1">
                <span>Comisión de turno:</span>
                <strong class="text-success">{{ sucursal.turno_activo.total_comision_bruta }} Bs</strong>
              </div>

              <div class="d-flex justify-space-between text-caption mb-1">
                <span>Bajas / Roturas:</span>
                <strong class="text-error">{{ sucursal.turno_activo.total_bajas }} unds</strong>
              </div>
            </div>

            <!-- Caso Turno Cerrado -->
            <div v-else class="text-center py-6">
              <VIcon
                icon="ri-door-lock-line"
                size="42"
                class="text-disabled mb-2"
              />
              <div class="text-body-2 font-weight-medium text-disabled">
                Sin turno activo en este momento
              </div>
              <div v-if="sucursal.turno_activo?.fecha_cierre" class="text-caption text-disabled mt-1">
                Último cierre: {{ new Date(sucursal.turno_activo.fecha_cierre).toLocaleDateString() }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Consola Técnica para Daniel (Super Usuario) -->
    <ConsolaHerramientasTecnicas
      v-if="authStore.isSuperAdmin"
      :sucursales="sucursales"
      @resync-success="fetchDashboardData(true)"
    />
  </div>
</template>

<style scoped>
.border-s-lg {
  border-left-width: 5px !important;
  border-left-style: solid !important;
}
</style>
