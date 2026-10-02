<script setup>
import { ref, onMounted, computed } from 'vue'
import { axiosIns } from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const gastos = ref([])
const isLoading = ref(false)
const showCreateModal = ref(false)
const showImageDialog = ref(false)
const selectedImage = ref('')

const filtroSucursal = ref(null)
const filtroEstado = ref(null)

const nuevoGasto = ref({
  sucursal_id: 1,
  fecha: new Date().toISOString().substring(0, 10),
  concepto: '',
  categoria: 'otros',
  monto_bs: '',
  foto_base64: null,
})
const previewGastoImg = ref(null)
const isSaving = ref(false)
const snackbar = ref({ show: false, message: '', color: 'success' })

const sucursales = [
  { id: 1, nombre: 'Casa22' },
  { id: 2, nombre: 'Casa Coron' },
  { id: 3, nombre: 'Madan' },
]

const categorias = [
  { title: 'Hielo en bolsa', value: 'hielo' },
  { title: 'Taxi / Rotación Personal', value: 'taxi_personal' },
  { title: 'Insumos de Barra', value: 'insumos_barra' },
  { title: 'Limpieza y Detergentes', value: 'limpieza' },
  { title: 'Mantenimiento Local', value: 'mantenimiento' },
  { title: 'Otros Gastos', value: 'otros' },
]

const fetchGastos = async () => {
  isLoading.value = true
  try {
    const params = {}
    if (filtroSucursal.value) params.sucursal_id = filtroSucursal.value
    if (filtroEstado.value) params.estado_comprobante = filtroEstado.value

    const res = await axiosIns.get('/gastos-caja-chica', { params })
    if (res.data?.success) {
      gastos.value = res.data.data?.data || []
    }
  } catch (err) {
    console.error('Error fetching gastos:', err)
  } finally {
    isLoading.value = false
  }
}

const onFileSelected = (e) => {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => {
    previewGastoImg.value = ev.target.result
    nuevoGasto.value.foto_base64 = ev.target.result.split(',')[1]
  }
  reader.readAsDataURL(file)
}

const guardarGasto = async () => {
  if (!nuevoGasto.value.concepto || !nuevoGasto.value.monto_bs) {
    snackbar.value = { show: true, message: 'Ingresa el concepto y el monto.', color: 'warning' }
    return
  }

  isSaving.value = true
  try {
    const res = await axiosIns.post('/gastos-caja-chica', {
      ...nuevoGasto.value,
      monto_bs: parseFloat(nuevoGasto.value.monto_bs),
    })

    if (res.data?.success) {
      snackbar.value = { show: true, message: 'Gasto registrado correctamente.', color: 'success' }
      showCreateModal.value = false
      nuevoGasto.value.concepto = ''
      nuevoGasto.value.monto_bs = ''
      nuevoGasto.value.foto_base64 = null
      previewGastoImg.value = null
      await fetchGastos()
    }
  } catch (err) {
    snackbar.value = { show: true, message: err.response?.data?.error || err.message, color: 'error' }
  } finally {
    isSaving.value = false
  }
}

const aprobarGasto = async (id) => {
  try {
    const res = await axiosIns.post(`/gastos-caja-chica/${id}/aprobar`)
    if (res.data?.success) {
      snackbar.value = { show: true, message: 'Gasto aprobado.', color: 'success' }
      await fetchGastos()
    }
  } catch (err) {
    snackbar.value = { show: true, message: err.response?.data?.error || err.message, color: 'error' }
  }
}

const cruzarConPlanilla = async () => {
  try {
    const res = await axiosIns.post('/gastos-caja-chica/cruzar-planilla', {
      sucursal_id: filtroSucursal.value || 1,
      fecha: new Date().toISOString().substring(0, 10),
    })
    if (res.data?.success) {
      snackbar.value = {
        show: true,
        message: `Cruce completado: ${res.data.data.total_gastos} gastos analizados.`,
        color: 'success',
      }
      await fetchGastos()
    }
  } catch (err) {
    snackbar.value = { show: true, message: err.response?.data?.error || err.message, color: 'error' }
  }
}

const verImagen = (url) => {
  selectedImage.value = url
  showImageDialog.value = true
}

const totalGastosBs = computed(() => {
  return gastos.value.reduce((s, g) => s + parseFloat(g.monto_bs || 0), 0).toFixed(2)
})

const totalObservadosBs = computed(() => {
  return gastos.value
    .filter(g => g.estado_comprobante === 'observado_sin_comprobante')
    .reduce((s, g) => s + parseFloat(g.monto_bs || 0), 0)
    .toFixed(2)
})

onMounted(() => {
  fetchGastos()
})
</script>

<template>
  <div>
    <!-- Encabezado -->
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
              icon="ri-wallet-3-line"
              size="28"
            />
          </VAvatar>
          <div>
            <h3 class="text-h5 font-weight-bold mb-0">
              Control de Caja Chica &amp; Deducciones de Barra
            </h3>
            <span class="text-caption text-medium-emphasis">
              Auditoría de compras menores, hielo y taxis deducidos de las ventas en efectivo
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            color="primary"
            prepend-icon="ri-add-line"
            @click="showCreateModal = true"
          >
            Registrar Gasto
          </VBtn>

          <VBtn
            color="warning"
            variant="tonal"
            prepend-icon="ri-split-cells-vertical"
            @click="cruzarConPlanilla"
          >
            Cruzar con Planilla
          </VBtn>

          <VBtn
            variant="tonal"
            icon="ri-refresh-line"
            :loading="isLoading"
            @click="fetchGastos"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- Resumen KPIs -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="4">
        <VCard class="border-s-lg border-primary">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="primary" variant="tonal" size="44" rounded>
              <VIcon icon="ri-coins-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Total Gastos Registrados</span>
              <h4 class="text-h5 font-weight-bold">{{ totalGastosBs }} Bs</h4>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4">
        <VCard class="border-s-lg border-error">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="error" variant="tonal" size="44" rounded>
              <VIcon icon="ri-file-warning-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Observados sin Comprobante</span>
              <h4 class="text-h5 font-weight-bold text-error">{{ totalObservadosBs }} Bs</h4>
              <span class="text-caption text-error">Deducción retenida al cajero</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4">
        <VCard class="border-s-lg border-success">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="success" variant="tonal" size="44" rounded>
              <VIcon icon="ri-shield-check-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Aprobados con Respaldo</span>
              <h4 class="text-h5 font-weight-bold text-success">
                {{ gastos.filter(g => g.estado_comprobante === 'aprobado_con_foto').length }} ítems
              </h4>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Gastos de Caja Chica -->
    <VCard>
      <VCardItem class="border-b">
        <VCardTitle>Movimientos de Caja Chica</VCardTitle>
        <VCardSubtitle>Semáforo de comprobantes de respaldo fotográfico</VCardSubtitle>
      </VCardItem>

      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th>FECHA</th>
            <th>SUCURSAL</th>
            <th>CONCEPTO</th>
            <th>CATEGORÍA</th>
            <th>MONTO (BS)</th>
            <th>FOTO RECIBO</th>
            <th>ESTADO AUDITORÍA</th>
            <th class="text-center">ACCIONES</th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="gastos.length === 0 && !isLoading">
            <td colspan="8" class="text-center py-6 text-disabled">
              No hay gastos registrados.
            </td>
          </tr>

          <tr
            v-for="item in gastos"
            :key="item.id"
          >
            <td>{{ item.fecha }}</td>
            <td>
              <VChip size="small" variant="tonal" color="primary">
                {{ item.sucursal?.nombre || 'Sucursal' }}
              </VChip>
            </td>
            <td>
              <span class="font-weight-medium">{{ item.concepto }}</span>
            </td>
            <td>
              <span class="text-caption text-medium-emphasis">{{ item.categoria }}</span>
            </td>
            <td>
              <strong class="text-h6" style="font-size: 14px;">{{ item.monto_bs }} Bs</strong>
            </td>
            <td>
              <div v-if="item.foto_comprobante_url">
                <VAvatar
                  size="36"
                  rounded
                  class="cursor-pointer border"
                  @click="verImagen(item.foto_comprobante_url)"
                >
                  <img :src="item.foto_comprobante_url" alt="Recibo">
                </VAvatar>
              </div>
              <VChip
                v-else
                color="error"
                size="x-small"
                variant="outlined"
              >
                Sin Foto
              </VChip>
            </td>
            <td>
              <VChip
                v-if="item.estado_comprobante === 'aprobado_con_foto'"
                color="success"
                size="small"
                variant="tonal"
              >
                <VIcon start icon="ri-check-double-line" size="14" />
                Aprobado con Foto
              </VChip>

              <VChip
                v-else-if="item.estado_comprobante === 'observado_sin_comprobante'"
                color="error"
                size="small"
                variant="tonal"
              >
                <VIcon start icon="ri-alarm-warning-line" size="14" />
                Observado (Sin Comprobante)
              </VChip>

              <VChip
                v-else
                color="warning"
                size="small"
                variant="tonal"
              >
                Pendiente Revisión
              </VChip>
            </td>
            <td class="text-center">
              <VBtn
                v-if="item.estado_comprobante !== 'aprobado_con_foto'"
                size="x-small"
                color="success"
                variant="tonal"
                prepend-icon="ri-check-line"
                @click="aprobarGasto(item.id)"
              >
                Aprobar
              </VBtn>
              <span v-else class="text-caption text-success">
                <VIcon icon="ri-checkbox-circle-fill" size="16" />
              </span>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Modal Registro de Gasto -->
    <VDialog
      v-model="showCreateModal"
      max-width="500"
    >
      <VCard>
        <VCardItem class="border-b">
          <VCardTitle>Nuevo Gasto de Caja Chica</VCardTitle>
          <VCardSubtitle>Adjunta la foto del recibo para aprobación inmediata</VCardSubtitle>
        </VCardItem>

        <VCardText class="pa-4">
          <VRow>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="nuevoGasto.sucursal_id"
                :items="sucursales"
                item-title="nombre"
                item-value="id"
                label="Sucursal"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="nuevoGasto.fecha"
                type="date"
                label="Fecha"
                density="compact"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="nuevoGasto.concepto"
                label="Concepto del Gasto"
                placeholder="Ej. 2 Bolsas de Hielo para Barra"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="nuevoGasto.categoria"
                :items="categorias"
                item-title="title"
                item-value="value"
                label="Categoría"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="nuevoGasto.monto_bs"
                type="number"
                label="Monto en Bs"
                placeholder="0.00"
                density="compact"
              />
            </VCol>

            <VCol cols="12">
              <div
                class="pa-4 text-center border-dashed rounded cursor-pointer"
                @click="$refs.fotoInput.click()"
              >
                <input
                  ref="fotoInput"
                  type="file"
                  accept="image/*"
                  class="d-none"
                  @change="onFileSelected"
                >
                <div v-if="!previewGastoImg">
                  <VIcon icon="ri-camera-line" size="32" color="primary" />
                  <div class="text-caption font-weight-medium">Subir foto de comprobante/recibo</div>
                </div>
                <div v-else>
                  <img :src="previewGastoImg" style="max-height: 120px;" class="rounded mb-1">
                  <div><VBtn size="x-small" color="error" variant="text">Cambiar</VBtn></div>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="border-t pa-4">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="showCreateModal = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="isSaving" @click="guardarGasto">Guardar Gasto</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo Zoom Imagen -->
    <VDialog v-model="showImageDialog" max-width="700">
      <VCard class="pa-2 text-center">
        <img :src="selectedImage" style="max-width: 100%; max-height: 80vh; object-fit: contain;">
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top right">
      {{ snackbar.message }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.border-s-lg {
  border-left-width: 5px !important;
  border-left-style: solid !important;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
