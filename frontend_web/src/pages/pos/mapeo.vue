<script setup>
import { ref, onMounted, computed } from 'vue'
import { axiosIns } from '@/plugins/axios'

const mapeos = ref([])
const catalogoProductos = ref([])
const catalogoCombos = ref([])
const sucursales = ref([])
const isLoading = ref(false)
const isSaving = ref(false)
const searchQuery = ref('')
const selectedSucursalId = ref(null)
const selectedFilterEstado = ref('todos')

// Modal de Vinculación
const showModal = ref(false)
const modalData = ref({
  id: null,
  sucursal_id: null,
  pos_producto_id: '',
  pos_nombre_producto: '',
  tipo_vinculacion: 'combo', // 'combo' | 'producto'
  c22_producto_id: null,
  c22_combo_id: null,
})

// Alerta / Snack
const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})

const fetchMapeos = async () => {
  isLoading.value = true
  try {
    const params = {}
    if (selectedSucursalId.value) params.sucursal_id = selectedSucursalId.value

    const res = await axiosIns.get('/pos/mapeo-productos', { params })
    if (res.data?.success) {
      mapeos.value = res.data.data?.mapeos || []
      catalogoProductos.value = res.data.data?.catalogo_productos || []
      catalogoCombos.value = res.data.data?.catalogo_combos || []
      sucursales.value = res.data.data?.sucursales || []

      if (!selectedSucursalId.value && sucursales.value.length > 0) {
        selectedSucursalId.value = sucursales.value[0].id
      }
    }
  } catch (err) {
    console.error('Error fetching mapeos:', err)
    mostrarNotificacion('Error cargando los mapeos del POS', 'error')
  } finally {
    isLoading.value = false
  }
}

const mostrarNotificacion = (msg, color = 'success') => {
  snackbar.value = {
    show: true,
    text: msg,
    color,
  }
}

const abrirModalNuevo = () => {
  modalData.value = {
    id: null,
    sucursal_id: selectedSucursalId.value || (sucursales.value[0]?.id ?? 1),
    pos_producto_id: '',
    pos_nombre_producto: '',
    tipo_vinculacion: 'combo',
    c22_producto_id: null,
    c22_combo_id: null,
  }
  showModal.value = true
}

const abrirModalEditar = (item) => {
  const esCombo = !!item.c22_combo_id
  modalData.value = {
    id: item.id,
    sucursal_id: item.sucursal_id,
    pos_producto_id: item.pos_producto_id,
    pos_nombre_producto: item.pos_nombre_producto,
    tipo_vinculacion: esCombo ? 'combo' : (item.c22_producto_id ? 'producto' : 'combo'),
    c22_producto_id: item.c22_producto_id,
    c22_combo_id: item.c22_combo_id,
  }
  showModal.value = true
}

const guardarMapeo = async () => {
  if (!modalData.value.pos_producto_id) {
    mostrarNotificacion('Debe especificar el ID de Producto en el POS', 'error')
    return
  }

  if (modalData.value.tipo_vinculacion === 'producto' && !modalData.value.c22_producto_id) {
    mostrarNotificacion('Seleccione el Producto equivalente de C22', 'error')
    return
  }

  if (modalData.value.tipo_vinculacion === 'combo' && !modalData.value.c22_combo_id) {
    mostrarNotificacion('Seleccione la Receta/Combo de C22', 'error')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      sucursal_id: modalData.value.sucursal_id,
      pos_producto_id: modalData.value.pos_producto_id,
      pos_nombre_producto: modalData.value.pos_nombre_producto || modalData.value.pos_producto_id,
      c22_producto_id: modalData.value.tipo_vinculacion === 'producto' ? modalData.value.c22_producto_id : null,
      c22_combo_id: modalData.value.tipo_vinculacion === 'combo' ? modalData.value.c22_combo_id : null,
    }

    const res = await axiosIns.post('/pos/mapeo-productos', payload)
    if (res.data?.success) {
      mostrarNotificacion('Mapeo guardado y transacciones recalculadas con éxito', 'success')
      showModal.value = false
      fetchMapeos()
    } else {
      mostrarNotificacion(res.data?.error || 'No se pudo guardar el mapeo', 'error')
    }
  } catch (err) {
    console.error('Error guardando mapeo:', err)
    mostrarNotificacion(err.response?.data?.error || 'Error al guardar el mapeo', 'error')
  } finally {
    isSaving.value = false
  }
}

// Estadísticas computadas
const totalItems = computed(() => mapeos.value.length)
const totalMapeados = computed(() => mapeos.value.filter(m => m.estado === 'mapeado').length)
const totalPendientes = computed(() => mapeos.value.filter(m => m.estado === 'pendiente_mapeo').length)

const tokenSucursalActual = computed(() => {
  const suc = sucursales.value.find(s => s.id === selectedSucursalId.value)
  return suc?.token_acceso || 'Token no generado'
})

const copiarToken = () => {
  navigator.clipboard.writeText(tokenSucursalActual.value)
  mostrarNotificacion('Token copiado al portapapeles', 'info')
}

// Filtro de lista
const mapeosFiltrados = computed(() => {
  return mapeos.value.filter(m => {
    // Filtro por sucursal
    if (selectedSucursalId.value && m.sucursal_id !== selectedSucursalId.value) {
      return false
    }

    // Filtro por estado
    if (selectedFilterEstado.value === 'mapeados' && m.estado !== 'mapeado') return false
    if (selectedFilterEstado.value === 'pendientes' && m.estado !== 'pendiente_mapeo') return false

    // Búsqueda de texto
    if (!searchQuery.value) return true
    const q = searchQuery.value.toLowerCase()
    return (
      (m.pos_nombre_producto && m.pos_nombre_producto.toLowerCase().includes(q)) ||
      (m.pos_producto_id && m.pos_producto_id.toString().includes(q)) ||
      (m.c22_producto_nombre && m.c22_producto_nombre.toLowerCase().includes(q)) ||
      (m.c22_combo_nombre && m.c22_combo_nombre.toLowerCase().includes(q))
    )
  })
})

onMounted(() => {
  fetchMapeos()
})
</script>

<template>
  <div class="mapeo-pos-container">
    <!-- Header -->
    <VRow class="mb-4 align-center">
      <VCol cols="12" md="8">
        <h2 class="text-h4 font-weight-bold text-primary mb-1">
          <VIcon icon="ri-git-merge-line" class="me-2" />
          Mapeo y Homologación POS RestoTech
        </h2>
        <p class="text-subtitle-1 text-medium-emphasis mb-0">
          Vincule los productos y combos de la caja de cada sucursal con las recetas y botellas de Casa22.
        </p>
      </VCol>
      <VCol cols="12" md="4" class="text-md-end">
        <VBtn
          color="primary"
          prepend-icon="ri-add-line"
          class="me-2 elevation-2"
          @click="abrirModalNuevo"
        >
          Nuevo Mapeo
        </VBtn>
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="ri-refresh-line"
          :loading="isLoading"
          @click="fetchMapeos"
        >
          Actualizar
        </VBtn>
      </VCol>
    </VRow>

    <!-- KPI Cards -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1" class="rounded-xl border">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="primary" variant="tonal" rounded="lg" size="48">
              <VIcon icon="ri-shopping-cart-2-line" size="28" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">Total Ítems POS</div>
              <div class="text-h5 font-weight-bold">{{ totalItems }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1" class="rounded-xl border">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="success" variant="tonal" rounded="lg" size="48">
              <VIcon icon="ri-check-double-line" size="28" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">Mapeados a Receta</div>
              <div class="text-h5 font-weight-bold text-success">{{ totalMapeados }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1" class="rounded-xl border">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="warning" variant="tonal" rounded="lg" size="48">
              <VIcon icon="ri-alert-line" size="28" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">Pendientes de Mapeo</div>
              <div class="text-h5 font-weight-bold text-warning">{{ totalPendientes }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1" class="rounded-xl border bg-var-theme-surface">
          <VCardText class="py-2">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption font-weight-bold text-primary">Token X-Branch-Token</span>
              <VBtn size="x-small" variant="text" icon="ri-file-copy-line" color="primary" @click="copiarToken" />
            </div>
            <div class="text-truncate text-caption font-mono bg-light-primary pa-1 rounded">
              {{ tokenSucursalActual }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filtros y Tabla -->
    <VCard elevation="2" class="rounded-xl">
      <VCardItem class="pb-2">
        <VRow dense align="center">
          <VCol cols="12" sm="4" md="3">
            <VSelect
              v-model="selectedSucursalId"
              :items="sucursales"
              item-title="nombre"
              item-value="id"
              label="Sucursal"
              density="compact"
              variant="outlined"
              prepend-inner-icon="ri-building-line"
              @update:model-value="fetchMapeos"
            />
          </VCol>

          <VCol cols="12" sm="4" md="3">
            <VSelect
              v-model="selectedFilterEstado"
              :items="[
                { title: 'Todos los ítems', value: 'todos' },
                { title: 'Solo Mapeados', value: 'mapeados' },
                { title: 'Solo Pendientes', value: 'pendientes' }
              ]"
              item-title="title"
              item-value="value"
              label="Estado de Mapeo"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" sm="4" md="6">
            <VTextField
              v-model="searchQuery"
              placeholder="Buscar por código POS, nombre o receta C22..."
              prepend-inner-icon="ri-search-line"
              density="compact"
              variant="outlined"
              clearable
            />
          </VCol>
        </VRow>
      </VCardItem>

      <VDivider />

      <!-- Tabla de Mapeos -->
      <VTable density="comfortable" hover>
        <thead>
          <tr>
            <th class="text-left font-weight-bold">ID POS</th>
            <th class="text-left font-weight-bold">Nombre en RestoTech</th>
            <th class="text-left font-weight-bold">Sucursal</th>
            <th class="text-left font-weight-bold">Equivalencia en Casa22</th>
            <th class="text-center font-weight-bold">Estado</th>
            <th class="text-center font-weight-bold">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="isLoading">
            <td colspan="6" class="text-center py-6">
              <VProgressCircular indeterminate color="primary" class="me-2" />
              <span>Cargando catálogo de mapeo...</span>
            </td>
          </tr>
          <tr v-else-if="mapeosFiltrados.length === 0">
            <td colspan="6" class="text-center py-6 text-medium-emphasis">
              <VIcon icon="ri-inbox-line" size="36" class="d-block mx-auto mb-2 text-disabled" />
              No se encontraron ítems que coincidan con el filtro actual.
            </td>
          </tr>
          <tr v-for="item in mapeosFiltrados" :key="item.id">
            <td class="font-weight-medium font-mono text-primary">
              #{{ item.pos_producto_id }}
            </td>
            <td>
              <div class="font-weight-bold">{{ item.pos_nombre_producto }}</div>
            </td>
            <td>
              <VChip size="small" variant="tonal" color="info">
                {{ item.sucursal_nombre }}
              </VChip>
            </td>
            <td>
              <div v-if="item.c22_combo_id" class="d-flex align-center gap-1">
                <VChip size="small" color="purple" variant="tonal">
                  <VIcon icon="ri-drinks-line" size="14" class="me-1" />
                  Combo C22
                </VChip>
                <span class="font-weight-medium ms-1">{{ item.c22_combo_nombre }}</span>
              </div>
              <div v-else-if="item.c22_producto_id" class="d-flex align-center gap-1">
                <VChip size="small" color="blue" variant="tonal">
                  <VIcon icon="ri-drop-line" size="14" class="me-1" />
                  Producto C22
                </VChip>
                <span class="font-weight-medium ms-1">{{ item.c22_producto_nombre }}</span>
              </div>
              <div v-else class="text-medium-emphasis text-caption font-italic">
                Sin vinculación asignada
              </div>
            </td>
            <td class="text-center">
              <VChip
                size="small"
                :color="item.estado === 'mapeado' ? 'success' : 'warning'"
                variant="flat"
              >
                {{ item.estado === 'mapeado' ? 'Mapeado' : 'Pendiente' }}
              </VChip>
            </td>
            <td class="text-center">
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="ri-edit-line"
                @click="abrirModalEditar(item)"
              >
                Vincular
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Diálogo Modal de Vinculación -->
    <VDialog v-model="showModal" max-width="600" persistent>
      <VCard class="rounded-xl">
        <VCardTitle class="pa-4 bg-primary text-white d-flex align-center justify-space-between">
          <span class="font-weight-bold">
            <VIcon icon="ri-links-line" class="me-2" />
            Vincular Ítem del POS a Receta Casa22
          </span>
          <VBtn icon="ri-close-line" variant="text" color="white" @click="showModal = false" />
        </VCardTitle>

        <VCardText class="pa-6">
          <VRow dense>
            <VCol cols="12" md="6">
              <VSelect
                v-model="modalData.sucursal_id"
                :items="sucursales"
                item-title="nombre"
                item-value="id"
                label="Sucursal *"
                variant="outlined"
                density="comfortable"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="modalData.pos_producto_id"
                label="ID de Producto en POS RestoTech *"
                variant="outlined"
                density="comfortable"
                placeholder="Ej. 101"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="modalData.pos_nombre_producto"
                label="Nombre tal como figura en POS *"
                variant="outlined"
                density="comfortable"
                placeholder="Ej. COMBO RED LABEL + 4 RED BULL"
              />
            </VCol>

            <VCol cols="12" class="my-2">
              <div class="text-subtitle-2 mb-2 font-weight-bold">¿Cómo debe descontarse en el balance físico de barra?</div>
              <VRadioGroup v-model="modalData.tipo_vinculacion" inline>
                <VRadio label="Combo con Desglose (varias botellas/latas)" value="combo" color="purple" />
                <VRadio label="Producto Simple (1 a 1)" value="producto" color="blue" />
              </VRadioGroup>
            </VCol>

            <VCol v-if="modalData.tipo_vinculacion === 'combo'" cols="12">
              <VSelect
                v-model="modalData.c22_combo_id"
                :items="catalogoCombos"
                item-title="nombre"
                item-value="id"
                label="Seleccione el Combo / Receta de C22 *"
                variant="outlined"
                density="comfortable"
                placeholder="Busque la receta configurada..."
              />
              <div class="text-caption text-medium-emphasis mt-1">
                Al asociarlo a un combo, cada venta en caja descontará automáticamente las botellas y latas de la receta.
              </div>
            </VCol>

            <VCol v-if="modalData.tipo_vinculacion === 'producto'" cols="12">
              <VSelect
                v-model="modalData.c22_producto_id"
                :items="catalogoProductos"
                item-title="nombre"
                item-value="id"
                label="Seleccione el Producto del Catálogo C22 *"
                variant="outlined"
                density="comfortable"
                placeholder="Seleccione botella o producto..."
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4 bg-var-theme-surface border-t d-flex justify-end gap-2">
          <VBtn variant="outlined" color="secondary" @click="showModal = false">
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="ri-save-line"
            :loading="isSaving"
            @click="guardarMapeo"
          >
            Guardar Mapeo
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar de notificaciones -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top end"
      :timeout="3500"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.mapeo-pos-container {
  padding: 1.5rem;
}
.font-mono {
  font-family: monospace;
}
</style>
