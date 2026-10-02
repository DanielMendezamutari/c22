<script setup>
import { ref, onMounted, computed } from 'vue'
import { axiosIns } from '@/plugins/axios'
import ModalCargaRecaudacion from '@/views/auditoria/ModalCargaRecaudacion.vue'

const recaudaciones = ref([])
const isLoading = ref(false)
const showUploadModal = ref(false)
const selectedRecaudacion = ref(null)
const showDetailDialog = ref(false)

const filtroSucursal = ref(null)
const filtroFechaDesde = ref('')
const filtroFechaHasta = ref('')

const sucursales = [
  { id: 1, nombre: 'Casa22' },
  { id: 2, nombre: 'Casa Coron' },
  { id: 3, nombre: 'Madan' },
]

const fetchRecaudaciones = async () => {
  isLoading.value = true
  try {
    const params = {}
    if (filtroSucursal.value) params.sucursal_id = filtroSucursal.value
    if (filtroFechaDesde.value) params.fecha_desde = filtroFechaDesde.value
    if (filtroFechaHasta.value) params.fecha_hasta = filtroFechaHasta.value

    const res = await axiosIns.get('/recaudaciones/diarias', { params })
    if (res.data?.success) {
      recaudaciones.value = res.data.data?.data || []
    }
  } catch (err) {
    console.error('Error fetching recaudaciones:', err)
  } finally {
    isLoading.value = false
  }
}

const abrirDetalle = (item) => {
  selectedRecaudacion.value = item
  showDetailDialog.value = true
}

const totalFaltantesBs = computed(() => {
  return recaudaciones.value
    .filter(r => r.diferencia_bs < 0)
    .reduce((sum, r) => sum + Math.abs(parseFloat(r.diferencia_bs)), 0)
    .toFixed(2)
})

const totalDepositadoBs = computed(() => {
  return recaudaciones.value
    .reduce((sum, r) => sum + parseFloat(r.monto_voucher_banco_bs || 0), 0)
    .toFixed(2)
})

const getEstadoBadge = (estado, diff) => {
  switch (estado) {
    case 'conciliado_exacto':
      return { color: 'success', text: 'Conciliado Exacto (0.00 Bs)', icon: 'ri-checkbox-circle-line' }
    case 'discrepancia_faltante':
      return { color: 'error', text: `Faltante: ${Math.abs(diff)} Bs`, icon: 'ri-alarm-warning-line' }
    case 'discrepancia_sobrante':
      return { color: 'info', text: `Sobrante: +${diff} Bs`, icon: 'ri-add-circle-line' }
    case 'pendiente_voucher':
      return { color: 'warning', text: 'Pendiente Voucher Depósito', icon: 'ri-time-line' }
    default:
      return { color: 'secondary', text: estado, icon: 'ri-information-line' }
  }
}

onMounted(() => {
  fetchRecaudaciones()
})
</script>

<template>
  <div>
    <!-- Encabezado y Acciones -->
    <VCard class="mb-6">
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4 py-4">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="primary"
            variant="tonal"
            size="48"
            rounded
          >
            <VIcon
              icon="ri-file-search-line"
              size="28"
            />
          </VAvatar>
          <div>
            <h3 class="text-h5 font-weight-bold mb-0">
              Auditoría de Planillas vs Vouchers Bancarios (Gemini Vision)
            </h3>
            <span class="text-caption text-medium-emphasis">
              Detección de faltantes en sobres de efectivo y cruce automático con depósitos bancarios
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            color="primary"
            prepend-icon="ri-upload-cloud-line"
            @click="showUploadModal = true"
          >
            Subir Planilla / Voucher
          </VBtn>

          <VBtn
            variant="tonal"
            icon="ri-refresh-line"
            :loading="isLoading"
            @click="fetchRecaudaciones"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- KPIs Rápidos de Auditoría Financiera -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="4">
        <VCard class="border-s-lg border-success">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="success" variant="tonal" size="44" rounded>
              <VIcon icon="ri-bank-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Total Depositado en Banco</span>
              <h4 class="text-h5 font-weight-bold">{{ totalDepositadoBs }} Bs</h4>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4">
        <VCard class="border-s-lg border-error">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="error" variant="tonal" size="44" rounded>
              <VIcon icon="ri-error-warning-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Faltante Total Detectado</span>
              <h4 class="text-h5 font-weight-bold text-error">{{ totalFaltantesBs }} Bs</h4>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4">
        <VCard class="border-s-lg border-info">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="info" variant="tonal" size="44" rounded>
              <VIcon icon="ri-whatsapp-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Grupo Recaudaciones</span>
              <h4 class="text-h5 font-weight-bold text-info">Alertas Activas</h4>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Conciliaciones Diarias -->
    <VCard>
      <VCardItem class="border-b">
        <VCardTitle>Historial de Recaudaciones Diarias</VCardTitle>
        <VCardSubtitle>Cruce del sobre en efectivo físico contra la constancia bancaria</VCardSubtitle>
      </VCardItem>

      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th>FECHA</th>
            <th>SUCURSAL</th>
            <th>SOBRE DECLARADO</th>
            <th>DEPÓSITO BANCO</th>
            <th>DIFERENCIA</th>
            <th>ESTADO CONCILIACIÓN</th>
            <th>ALERTA WHATSAPP</th>
            <th class="text-center">COMPARADOR</th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="recaudaciones.length === 0 && !isLoading">
            <td colspan="8" class="text-center py-6 text-disabled">
              No hay registros de recaudación para los filtros seleccionados. Presiona "+ Subir Planilla / Voucher" para procesar el primer comprobante.
            </td>
          </tr>

          <tr
            v-for="item in recaudaciones"
            :key="item.id"
          >
            <td>
              <span class="font-weight-medium">{{ item.fecha }}</span>
            </td>
            <td>
              <VChip size="small" variant="tonal" color="primary">
                {{ item.sucursal?.nombre || 'Sucursal' }}
              </VChip>
            </td>
            <td>
              <span class="font-weight-bold">{{ item.monto_sobre_declarado_bs }} Bs</span>
              <div v-if="item.planilla" class="text-caption text-disabled">
                {{ item.planilla.cajero_nombre || 'Cajero' }}
              </div>
            </td>
            <td>
              <span class="font-weight-bold text-success">{{ item.monto_voucher_banco_bs }} Bs</span>
              <div v-if="item.voucher" class="text-caption text-disabled">
                {{ item.voucher.banco_nombre }} &bull; {{ item.voucher.nro_operacion }}
              </div>
            </td>
            <td>
              <span
                class="font-weight-bold"
                :class="{
                  'text-error': item.diferencia_bs < 0,
                  'text-success': item.diferencia_bs == 0,
                  'text-info': item.diferencia_bs > 0,
                }"
              >
                {{ item.diferencia_bs }} Bs
              </span>
            </td>
            <td>
              <VChip
                :color="getEstadoBadge(item.estado_conciliacion, item.diferencia_bs).color"
                size="small"
                variant="tonal"
              >
                <VIcon
                  start
                  :icon="getEstadoBadge(item.estado_conciliacion, item.diferencia_bs).icon"
                  size="14"
                />
                {{ getEstadoBadge(item.estado_conciliacion, item.diferencia_bs).text }}
              </VChip>
            </td>
            <td>
              <VChip
                v-if="item.alerta_whatsapp_enviada"
                color="success"
                size="x-small"
                variant="outlined"
              >
                <VIcon start icon="ri-check-line" size="12" />
                Despachada a Grupo
              </VChip>
              <span v-else class="text-caption text-disabled">-</span>
            </td>
            <td class="text-center">
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="ri-split-cells-horizontal"
                @click="abrirDetalle(item)"
              >
                Ver Lado a Lado
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Modal de Carga de Comprobantes -->
    <ModalCargaRecaudacion
      v-model="showUploadModal"
      :sucursales="sucursales"
      @upload-success="fetchRecaudaciones"
    />

    <!-- Diálogo Comparador Lado a Lado (Foto Planilla vs Foto Voucher) -->
    <VDialog
      v-model="showDetailDialog"
      max-width="1100"
    >
      <VCard v-if="selectedRecaudacion">
        <VCardItem class="border-b">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" rounded size="40" class="me-2">
              <VIcon icon="ri-split-cells-horizontal" size="24" />
            </VAvatar>
          </template>
          <VCardTitle>
            Comparador Lado a Lado: {{ selectedRecaudacion.sucursal?.nombre }} - {{ selectedRecaudacion.fecha }}
          </VCardTitle>
          <VCardSubtitle>
            Auditoría fotográfica del sobre de caja manuscrito vs la constancia de depósito bancario
          </VCardSubtitle>
          <template #append>
            <VBtn icon="ri-close-line" variant="text" size="small" @click="showDetailDialog = false" />
          </template>
        </VCardItem>

        <VCardText class="pa-6">
          <VRow>
            <!-- Lado Izquierdo: Planilla Manuscrita -->
            <VCol cols="12" md="6" class="border-e">
              <div class="d-flex align-center justify-space-between mb-3">
                <h5 class="text-h6 font-weight-bold text-primary">
                  1. Planilla Física Manuscrita de Caja
                </h5>
                <VChip color="primary" size="small" label>Sobre de Efectivo</VChip>
              </div>

              <div class="image-box rounded pa-2 border text-center mb-3 bg-surface">
                <img
                  v-if="selectedRecaudacion.planilla?.foto_url"
                  :src="selectedRecaudacion.planilla.foto_url"
                  alt="Planilla"
                  style="max-height: 340px; max-width: 100%; object-fit: contain;"
                  class="rounded"
                >
                <div v-else class="py-12 text-disabled">
                  <VIcon icon="ri-file-warning-line" size="48" class="mb-2" />
                  <div>Sin fotografía de planilla cargada</div>
                </div>
              </div>

              <VList density="compact" class="border rounded">
                <VListItem>
                  <template #prepend><VIcon icon="ri-user-line" class="me-2 text-primary" /></template>
                  <VListItemTitle>Cajero Declarante</VListItemTitle>
                  <template #append>
                    <strong>{{ selectedRecaudacion.planilla?.cajero_nombre || 'N/A' }}</strong>
                  </template>
                </VListItem>

                <VListItem>
                  <template #prepend><VIcon icon="ri-money-dollar-box-line" class="me-2 text-primary" /></template>
                  <VListItemTitle>Ventas Totales Declaradas</VListItemTitle>
                  <template #append>
                    <strong>{{ selectedRecaudacion.planilla?.total_ventas_declaradas_bs || '0.00' }} Bs</strong>
                  </template>
                </VListItem>

                <VListItem>
                  <template #prepend><VIcon icon="ri-wallet-3-line" class="me-2 text-warning" /></template>
                  <VListItemTitle>Gastos Deducidos de Barra</VListItemTitle>
                  <template #append>
                    <strong class="text-warning">-{{ selectedRecaudacion.planilla?.total_gastos_declarados_bs || '0.00' }} Bs</strong>
                  </template>
                </VListItem>

                <VListItem class="bg-primary-subtle">
                  <template #prepend><VIcon icon="ri-mail-check-line" class="me-2 text-primary" /></template>
                  <VListItemTitle class="font-weight-bold">Efectivo Líquido en Sobre</VListItemTitle>
                  <template #append>
                    <strong class="text-h6 text-primary">{{ selectedRecaudacion.monto_sobre_declarado_bs }} Bs</strong>
                  </template>
                </VListItem>
              </VList>
            </VCol>

            <!-- Lado Derecho: Voucher de Banco -->
            <VCol cols="12" md="6">
              <div class="d-flex align-center justify-space-between mb-3">
                <h5 class="text-h6 font-weight-bold text-success">
                  2. Voucher de Depósito Bancario
                </h5>
                <VChip color="success" size="small" label>Banco Oficial</VChip>
              </div>

              <div class="image-box rounded pa-2 border text-center mb-3 bg-surface">
                <img
                  v-if="selectedRecaudacion.voucher?.foto_url"
                  :src="selectedRecaudacion.voucher.foto_url"
                  alt="Voucher"
                  style="max-height: 340px; max-width: 100%; object-fit: contain;"
                  class="rounded"
                >
                <div v-else class="py-12 text-disabled">
                  <VIcon icon="ri-bank-line" size="48" class="mb-2" />
                  <div>Sin fotografía de voucher bancario</div>
                </div>
              </div>

              <VList density="compact" class="border rounded">
                <VListItem>
                  <template #prepend><VIcon icon="ri-building-line" class="me-2 text-success" /></template>
                  <VListItemTitle>Entidad Bancaria</VListItemTitle>
                  <template #append>
                    <strong>{{ selectedRecaudacion.voucher?.banco_nombre || 'N/A' }}</strong>
                  </template>
                </VListItem>

                <VListItem>
                  <template #prepend><VIcon icon="ri-hashtag" class="me-2 text-success" /></template>
                  <VListItemTitle>Nro. Operación</VListItemTitle>
                  <template #append>
                    <code>{{ selectedRecaudacion.voucher?.nro_operacion || 'N/A' }}</code>
                  </template>
                </VListItem>

                <VListItem>
                  <template #prepend><VIcon icon="ri-calendar-event-line" class="me-2 text-success" /></template>
                  <VListItemTitle>Fecha de Acreditación</VListItemTitle>
                  <template #append>
                    <span>{{ selectedRecaudacion.voucher?.fecha_deposito || selectedRecaudacion.fecha }}</span>
                  </template>
                </VListItem>

                <VListItem class="bg-success-subtle">
                  <template #prepend><VIcon icon="ri-check-double-line" class="me-2 text-success" /></template>
                  <VListItemTitle class="font-weight-bold">Importe Depositado en Cuenta</VListItemTitle>
                  <template #append>
                    <strong class="text-h6 text-success">{{ selectedRecaudacion.monto_voucher_banco_bs }} Bs</strong>
                  </template>
                </VListItem>
              </VList>
            </VCol>
          </VRow>

          <!-- Banner Resumen de Discrepancia -->
          <VAlert
            class="mt-6 mb-0"
            :type="selectedRecaudacion.diferencia_bs < 0 ? 'error' : 'success'"
            variant="tonal"
          >
            <div class="d-flex justify-space-between align-center flex-wrap gap-2">
              <div>
                <strong>Resultado de la Conciliación Matemática:</strong>
                <span class="ms-2">
                  Sobre: {{ selectedRecaudacion.monto_sobre_declarado_bs }} Bs &minus; Banco: {{ selectedRecaudacion.monto_voucher_banco_bs }} Bs
                  = <strong>{{ selectedRecaudacion.diferencia_bs }} Bs</strong>
                </span>
              </div>
              <VChip
                v-if="selectedRecaudacion.alerta_whatsapp_enviada"
                color="error"
                variant="elevated"
                size="small"
              >
                🚨 Alerta notificada al Grupo de Recaudaciones
              </VChip>
            </div>
          </VAlert>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.border-s-lg {
  border-left-width: 5px !important;
  border-left-style: solid !important;
}

.bg-primary-subtle {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

.bg-success-subtle {
  background-color: rgba(var(--v-theme-success), 0.08);
}
</style>
