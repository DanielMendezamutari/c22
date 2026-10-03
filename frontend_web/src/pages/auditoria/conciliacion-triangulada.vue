<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { axiosIns } from '@/plugins/axios'

const route = useRoute()

const turnoId = ref(route.query.turno_id ? parseInt(route.query.turno_id) : 1)
const turnosDisponibles = ref([])
const auditoria = ref(null)
const isLoading = ref(false)
const errorMessage = ref('')

const fetchTurnos = async () => {
  try {
    const res = await axiosIns.get('/auditoria/turnos-pendientes')
    if (res.data?.success) {
      turnosDisponibles.value = res.data.data || []
      if (!route.query.turno_id && turnosDisponibles.value.length > 0) {
        turnoId.value = turnosDisponibles.value[0].id
      }
    }
  } catch (e) {
    console.error('Error cargando turnos:', e)
  }
}

const cargarConciliacion = async () => {
  if (!turnoId.value) return
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await axiosIns.get(`/auditoria/conciliacion-triangulada/${turnoId.value}`)
    if (res.data?.success) {
      auditoria.value = res.data.data
    } else {
      errorMessage.value = res.data?.error || 'No se pudo obtener la conciliación'
    }
  } catch (err) {
    console.error('Error cargando conciliación triangulada:', err)
    errorMessage.value = err.response?.data?.error || 'Error de conexión al cargar la conciliación'
  } finally {
    isLoading.value = false
  }
}

const semaforoColor = computed(() => {
  if (!auditoria.value) return 'grey'
  switch (auditoria.value.estado_semaforo) {
    case 'verde_cuadrado':
      return 'success'
    case 'ambar_observado':
      return 'warning'
    case 'rojo_discrepancia':
      return 'error'
    default:
      return 'info'
  }
})

const semaforoTitulo = computed(() => {
  if (!auditoria.value) return 'Sin datos'
  switch (auditoria.value.estado_semaforo) {
    case 'verde_cuadrado':
      return 'TURNO CUADRADO (100% CONCILIADO)'
    case 'ambar_observado':
      return 'TURNO CON OBSERVACIÓN LEVE'
    case 'rojo_discrepancia':
      return 'DISCREPANCIA CRÍTICA DETECTADA'
    default:
      return 'EN PROCESO'
  }
})

onMounted(async () => {
  await fetchTurnos()
  cargarConciliacion()
})
</script>

<template>
  <div class="conciliacion-container">
    <!-- Header -->
    <VRow class="mb-4 align-center">
      <VCol cols="12" md="7">
        <h2 class="text-h4 font-weight-bold text-primary mb-1">
          <VIcon icon="ri-scales-3-line" class="me-2" />
          Conciliación Triangulada de Ventas e Inventario
        </h2>
        <p class="text-subtitle-1 text-medium-emphasis mb-0">
          Cruce automático e inteligente: [POS RestoTech] vs [Planilla Manual OCR] vs [Balance Físico de Barra].
        </p>
      </VCol>
      <VCol cols="12" md="5" class="d-flex align-center justify-md-end gap-2">
        <VTextField
          v-model="turnoId"
          label="ID Turno"
          type="number"
          density="compact"
          variant="outlined"
          style="max-width: 140px;"
          @keyup.enter="cargarConciliacion"
        />
        <VBtn
          color="primary"
          prepend-icon="ri-search-eye-line"
          :loading="isLoading"
          @click="cargarConciliacion"
        >
          Consultar
        </VBtn>
        <VBtn
          variant="outlined"
          color="secondary"
          to="/pos/mapeo"
          prepend-icon="ri-git-merge-line"
        >
          Mapeo POS
        </VBtn>
      </VCol>
    </VRow>

    <!-- Error Alert -->
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-6 rounded-xl"
      closable
    >
      {{ errorMessage }}
    </VAlert>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-12">
      <VProgressCircular indeterminate color="primary" size="64" />
      <div class="mt-4 text-h6 text-medium-emphasis">Calculando conciliación triangulada...</div>
    </div>

    <!-- Main Content -->
    <div v-else-if="auditoria">
      <!-- Semáforo Banner -->
      <VCard
        :color="semaforoColor"
        variant="tonal"
        class="mb-6 rounded-xl border-dashed"
        elevation="0"
      >
        <VCardText class="d-flex flex-wrap align-center justify-space-between py-4">
          <div class="d-flex align-center gap-4">
            <VAvatar :color="semaforoColor" size="56" variant="flat">
              <VIcon
                :icon="auditoria.estado_semaforo === 'verde_cuadrado' ? 'ri-checkbox-circle-fill' : 'ri-alert-fill'"
                size="36"
                color="white"
              />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-bold">{{ semaforoTitulo }}</div>
              <div class="text-subtitle-2 text-medium-emphasis">
                Sucursal: <strong>{{ auditoria.sucursal_nombre }}</strong> | Fecha: <strong>{{ auditoria.fecha_turno }}</strong> | Turno #{{ auditoria.turno_id }}
              </div>
            </div>
          </div>
          <div class="d-flex gap-4 mt-2 mt-md-0">
            <div class="text-end">
              <div class="text-caption text-medium-emphasis">Responsable Caja</div>
              <div class="font-weight-bold text-subtitle-2">{{ auditoria.responsables?.caja }}</div>
            </div>
            <VDivider vertical class="mx-2" />
            <div class="text-end">
              <div class="text-caption text-medium-emphasis">Responsable Barra</div>
              <div class="font-weight-bold text-subtitle-2">{{ auditoria.responsables?.barra }}</div>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Matriz Triangulada de 3 Columnas -->
      <VRow class="mb-6">
        <!-- Vértice 1: POS RestoTech -->
        <VCol cols="12" md="4">
          <VCard elevation="2" class="rounded-xl h-100 border">
            <VCardItem class="bg-blue-lighten-5 py-3">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-blue-darken-3 text-subtitle-1">
                  <VIcon icon="ri-computer-line" class="me-2" />
                  1. POS RestoTech (Caja)
                </span>
                <VChip size="small" color="blue" variant="flat">Sensor Bruto</VChip>
              </div>
            </VCardItem>
            <VDivider />
            <VCardText class="pa-4">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Total Ventas Registradas en POS</div>
                <div class="text-h4 font-weight-bold text-blue-darken-3">
                  Bs. {{ (auditoria.vertice_1_pos?.ventas_brutas_bs || 0).toFixed(2) }}
                </div>
              </div>

              <VDivider class="my-3" />

              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">Efectivo en Caja POS:</span>
                <span class="font-weight-bold">Bs. {{ (auditoria.vertice_1_pos?.efectivo_bs || 0).toFixed(2) }}</span>
              </div>
              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">Cobros QR POS:</span>
                <span class="font-weight-medium">Bs. {{ (auditoria.vertice_1_pos?.qr_bs || 0).toFixed(2) }}</span>
              </div>
              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">Tarjeta / Otros POS:</span>
                <span class="font-weight-medium">Bs. {{ (auditoria.vertice_1_pos?.tarjeta_bs || 0).toFixed(2) }}</span>
              </div>
              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">Transacciones Ingestadas:</span>
                <VChip size="x-small" color="primary">{{ auditoria.vertice_1_pos?.transacciones_count || 0 }}</VChip>
              </div>

              <VDivider class="my-3" />

              <div class="bg-blue-lighten-5 pa-3 rounded-lg text-center">
                <div class="text-caption text-blue-darken-4 font-weight-medium">Equivalente Botellas Vendidas (Recetas C22)</div>
                <div class="text-h5 font-weight-bold text-blue-darken-3 mt-1">
                  {{ auditoria.vertice_1_pos?.botellas_vendidas_equiv || 0 }} <span class="text-caption">uds.</span>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Vértice 2: Planilla Manual de Caja -->
        <VCol cols="12" md="4">
          <VCard elevation="2" class="rounded-xl h-100 border">
            <VCardItem class="bg-amber-lighten-5 py-3">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-amber-darken-4 text-subtitle-1">
                  <VIcon icon="ri-file-text-line" class="me-2" />
                  2. Planilla Manual (OCR)
                </span>
                <VChip size="small" color="amber-darken-3" variant="flat">Declaración Físca</VChip>
              </div>
            </VCardItem>
            <VDivider />
            <VCardText class="pa-4">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Efectivo Anotado en Planilla</div>
                <div class="text-h4 font-weight-bold text-amber-darken-4">
                  Bs. {{ (auditoria.vertice_2_planilla?.efectivo_declarado_bs || 0).toFixed(2) }}
                </div>
              </div>

              <VDivider class="my-3" />

              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">(-) Gastos Caja Chica:</span>
                <span class="font-weight-medium text-error">Bs. {{ (auditoria.vertice_2_planilla?.gastos_caja_chica_bs || 0).toFixed(2) }}</span>
              </div>
              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">(=) Monto en Sobre Efectivo:</span>
                <span class="font-weight-bold">Bs. {{ (auditoria.vertice_2_planilla?.neto_sobre_declarado_bs || 0).toFixed(2) }}</span>
              </div>
              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">Depósito Bancario (Voucher):</span>
                <span class="font-weight-bold text-success">Bs. {{ (auditoria.vertice_2_planilla?.voucher_depositado_banco_bs || 0).toFixed(2) }}</span>
              </div>

              <VDivider class="my-3" />

              <div class="d-flex justify-space-around py-2">
                <VChip
                  size="small"
                  :color="auditoria.vertice_2_planilla?.tiene_foto_planilla ? 'success' : 'grey'"
                  variant="tonal"
                >
                  <VIcon icon="ri-image-line" class="me-1" />
                  {{ auditoria.vertice_2_planilla?.tiene_foto_planilla ? 'Foto Planilla OK' : 'Sin Foto Planilla' }}
                </VChip>
                <VChip
                  size="small"
                  :color="auditoria.vertice_2_planilla?.tiene_foto_voucher ? 'success' : 'grey'"
                  variant="tonal"
                >
                  <VIcon icon="ri-bank-card-line" class="me-1" />
                  {{ auditoria.vertice_2_planilla?.tiene_foto_voucher ? 'Voucher OK' : 'Sin Voucher' }}
                </VChip>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Vértice 3: Inventario Físico de Barra -->
        <VCol cols="12" md="4">
          <VCard elevation="2" class="rounded-xl h-100 border">
            <VCardItem class="bg-green-lighten-5 py-3">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-green-darken-3 text-subtitle-1">
                  <VIcon icon="ri-goblet-line" class="me-2" />
                  3. Barra (Inventario Real)
                </span>
                <VChip size="small" color="green-darken-2" variant="flat">Corte Físico</VChip>
              </div>
            </VCardItem>
            <VDivider />
            <VCardText class="pa-4">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Consumo Físico Real de Barra</div>
                <div class="text-h4 font-weight-bold text-green-darken-3">
                  {{ (auditoria.vertice_3_inventario?.consumo_fisico_botellas || 0).toFixed(2) }} <span class="text-caption">uds.</span>
                </div>
              </div>

              <VDivider class="my-3" />

              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">Conteo Físico Apertura:</span>
                <span class="font-weight-medium">{{ auditoria.vertice_3_inventario?.apertura_stock || 0 }} uds.</span>
              </div>
              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">(+) Ingresos de Almacén:</span>
                <span class="font-weight-medium text-success">+{{ auditoria.vertice_3_inventario?.ingresos_stock || 0 }} uds.</span>
              </div>
              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">(-) Bajas y Roturas:</span>
                <span class="font-weight-medium text-error">-{{ auditoria.vertice_3_inventario?.bajas_stock || 0 }} uds.</span>
              </div>
              <div class="d-flex justify-space-between py-1">
                <span class="text-body-2">(-) Conteo Físico Cierre:</span>
                <span class="font-weight-medium text-primary">-{{ auditoria.vertice_3_inventario?.cierre_stock || 0 }} uds.</span>
              </div>

              <VDivider class="my-3" />

              <div class="bg-green-lighten-5 pa-3 rounded-lg text-center">
                <div class="text-caption text-green-darken-4 font-weight-medium">Fórmula de Balance de Barra</div>
                <div class="text-caption font-mono text-medium-emphasis">
                  (Apertura + Ingresos - Bajas) - Cierre
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Panel de Imputación y Atribución de Responsabilidades -->
      <VRow>
        <!-- Responsabilidad de Dinero (Cajera) -->
        <VCol cols="12" md="6">
          <VCard elevation="2" class="rounded-xl border">
            <VCardItem class="py-3">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-subtitle-1">
                  <VIcon icon="ri-hand-coin-line" class="me-2 text-primary" />
                  Auditoría Financiera de Efectivo
                </span>
                <VChip
                  size="small"
                  :color="auditoria.auditoria_financiera?.diferencia_efectivo_bs < -20 ? 'error' : (auditoria.auditoria_financiera?.diferencia_efectivo_bs > 20 ? 'info' : 'success')"
                >
                  {{ auditoria.auditoria_financiera?.estado?.toUpperCase() }}
                </VChip>
              </div>
            </VCardItem>
            <VDivider />
            <VCardText class="pa-4">
              <div class="d-flex align-center justify-space-between mb-4">
                <div>
                  <div class="text-caption text-medium-emphasis">Diferencia [Planilla] - [POS]:</div>
                  <div
                    class="text-h4 font-weight-bold"
                    :class="auditoria.auditoria_financiera?.diferencia_efectivo_bs < -20 ? 'text-error' : 'text-success'"
                  >
                    Bs. {{ (auditoria.auditoria_financiera?.diferencia_efectivo_bs || 0).toFixed(2) }}
                  </div>
                </div>
                <div class="text-end">
                  <div class="text-caption text-medium-emphasis">Imputación Responsable:</div>
                  <div class="text-subtitle-1 font-weight-bold text-primary">
                    {{ auditoria.auditoria_financiera?.imputado_a || 'Sin faltante de dinero' }}
                  </div>
                </div>
              </div>

              <VAlert
                v-if="auditoria.auditoria_financiera?.diferencia_efectivo_bs < -20"
                type="warning"
                variant="tonal"
                density="compact"
                class="rounded-lg"
              >
                <strong>Regla de Imputación:</strong> El dinero faltante se imputa a la Cajera / Recaudador responsable del sobre y arqueo físico.
              </VAlert>
              <div v-else class="text-caption text-success d-flex align-center">
                <VIcon icon="ri-check-line" class="me-1" />
                El efectivo declarado coincide con los registros del sistema POS.
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Responsabilidad de Botellas (Barman) -->
        <VCol cols="12" md="6">
          <VCard elevation="2" class="rounded-xl border">
            <VCardItem class="py-3">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-subtitle-1">
                  <VIcon icon="ri-bubble-chart-line" class="me-2 text-primary" />
                  Auditoría Física de Botellas y Bebidas
                </span>
                <VChip
                  size="small"
                  :color="auditoria.auditoria_botellas?.diferencia_unidades < -1 ? 'error' : (auditoria.auditoria_botellas?.diferencia_unidades > 1 ? 'info' : 'success')"
                >
                  {{ auditoria.auditoria_botellas?.estado?.toUpperCase() }}
                </VChip>
              </div>
            </VCardItem>
            <VDivider />
            <VCardText class="pa-4">
              <div class="d-flex align-center justify-space-between mb-4">
                <div>
                  <div class="text-caption text-medium-emphasis">Diferencia [POS Vendido] - [Barra Consumo]:</div>
                  <div
                    class="text-h4 font-weight-bold"
                    :class="Math.abs(auditoria.auditoria_botellas?.diferencia_unidades || 0) > 1 ? 'text-error' : 'text-success'"
                  >
                    {{ (auditoria.auditoria_botellas?.diferencia_unidades || 0).toFixed(2) }} <span class="text-caption">uds.</span>
                  </div>
                </div>
                <div class="text-end">
                  <div class="text-caption text-medium-emphasis">Imputación Responsable:</div>
                  <div class="text-subtitle-1 font-weight-bold text-purple">
                    {{ auditoria.auditoria_botellas?.imputado_a || 'Sin descuadre físico' }}
                  </div>
                </div>
              </div>

              <VAlert
                v-if="auditoria.auditoria_botellas?.diferencia_unidades < -1"
                type="error"
                variant="tonal"
                density="compact"
                class="rounded-lg"
              >
                <strong>Fuga en Barra:</strong> Salieron más botellas del mostrador físico de las que figuran cobradas en el POS. Imputado al Barman.
              </VAlert>
              <div v-else class="text-caption text-success d-flex align-center">
                <VIcon icon="ri-check-line" class="me-1" />
                El consumo de botellas en barra coincide exactamente con los combos cobrados.
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.conciliacion-container {
  padding: 1.5rem;
}
.font-mono {
  font-family: monospace;
}
</style>
