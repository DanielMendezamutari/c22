<script setup>
import { ref } from 'vue'
import { axiosIns } from '@/plugins/axios'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  sucursales: {
    type: Array,
    default: () => [
      { id: 1, nombre: 'Casa22' },
      { id: 2, nombre: 'Casa Coron' },
      { id: 3, nombre: 'Madan' },
    ],
  },
})

const emit = defineEmits(['update:modelValue', 'upload-success'])

const tipoDocumento = ref('planilla') // 'planilla' | 'voucher'
const sucursalId = ref(1)
const fecha = ref(new Date().toISOString().substring(0, 10))
const fileInput = ref(null)
const previewImage = ref(null)
const base64Image = ref(null)
const isLoading = ref(false)
const errorMessage = ref('')

// Campos manuales opcionales
const totalVentas = ref('')
const totalGastos = ref('')
const montoSobre = ref('')
const montoDeposito = ref('')
const nroOperacion = ref('')
const bancoNombre = ref('Banco Unión')

const onFileSelected = (event) => {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (e) => {
    previewImage.value = e.target.result
    base64Image.value = e.target.result.split(',')[1]
  }
  reader.readAsDataURL(file)
}

const resetForm = () => {
  previewImage.value = null
  base64Image.value = null
  totalVentas.value = ''
  totalGastos.value = ''
  montoSobre.value = ''
  montoDeposito.value = ''
  nroOperacion.value = ''
  errorMessage.value = ''
}

const procesarDocumento = async () => {
  if (!base64Image.value) {
    errorMessage.value = 'Por favor selecciona o arrastra una fotografía del documento.'
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    let endpoint = ''
    let payload = {}

    if (tipoDocumento.value === 'planilla') {
      endpoint = '/recaudaciones/procesar-planilla'
      payload = {
        sucursal_id: sucursalId.value,
        fecha_operativa: fecha.value,
        foto_base64: base64Image.value,
        total_ventas_manual: totalVentas.value ? parseFloat(totalVentas.value) : null,
        total_gastos_manual: totalGastos.value ? parseFloat(totalGastos.value) : null,
        monto_sobre_manual: montoSobre.value ? parseFloat(montoSobre.value) : null,
      }
    } else {
      endpoint = '/recaudaciones/procesar-voucher'
      payload = {
        sucursal_id: sucursalId.value,
        fecha: fecha.value,
        foto_base64: base64Image.value,
        monto_manual: montoDeposito.value ? parseFloat(montoDeposito.value) : null,
        nro_operacion: nroOperacion.value || null,
        banco_nombre: bancoNombre.value,
      }
    }

    const res = await axiosIns.post(endpoint, payload)
    if (res.data?.success) {
      emit('upload-success', res.data.data)
      emit('update:modelValue', false)
      resetForm()
    } else {
      throw new Error(res.data?.error || 'Error al procesar con IA')
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.error || err.message || 'Error al comunicar con la API'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="650"
    persistent
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <VCard>
      <VCardItem class="border-b">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="40"
            class="me-2"
          >
            <VIcon
              icon="ri-brain-line"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>Ingesta y Auditoría de Comprobantes con Gemini Vision</VCardTitle>
        <VCardSubtitle>Carga fotos de planillas físicas manuscritas o vouchers bancarios para análisis OCR.</VCardSubtitle>
        <template #append>
          <VBtn
            icon="ri-close-line"
            variant="text"
            size="small"
            @click="emit('update:modelValue', false)"
          />
        </template>
      </VCardItem>

      <VCardText class="pt-4">
        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          closable
          class="mb-4"
          @click:close="errorMessage = ''"
        >
          {{ errorMessage }}
        </VAlert>

        <VRow>
          <VCol cols="12">
            <VBtnToggle
              v-model="tipoDocumento"
              mandatory
              color="primary"
              variant="outlined"
              class="w-100 d-flex"
            >
              <VBtn
                value="planilla"
                class="flex-grow-1"
              >
                <VIcon
                  start
                  icon="ri-file-list-3-line"
                />
                1. Planilla Manuscrita (Sobre)
              </VBtn>
              <VBtn
                value="voucher"
                class="flex-grow-1"
              >
                <VIcon
                  start
                  icon="ri-bank-card-line"
                />
                2. Voucher Depósito Banco
              </VBtn>
            </VBtnToggle>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSelect
              v-model="sucursalId"
              :items="sucursales"
              item-title="nombre"
              item-value="id"
              label="Sucursal"
              density="compact"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="fecha"
              type="date"
              label="Fecha Operativa"
              density="compact"
            />
          </VCol>

          <!-- Zona de Subida y Previsualización de Imagen -->
          <VCol cols="12">
            <div
              class="upload-dropzone pa-6 text-center rounded-lg border-dashed cursor-pointer"
              @click="$refs.fileInput.click()"
            >
              <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="d-none"
                @change="onFileSelected"
              >

              <div v-if="!previewImage">
                <VIcon
                  icon="ri-camera-lens-line"
                  size="48"
                  color="primary"
                  class="mb-2"
                />
                <div class="text-body-1 font-weight-medium">
                  Toca aquí para capturar o subir la fotografía
                </div>
                <span class="text-caption text-disabled">Soporta JPG, PNG, WEBP (hasta 10MB)</span>
              </div>

              <div
                v-else
                class="preview-container"
              >
                <img
                  :src="previewImage"
                  alt="Vista previa"
                  class="rounded preview-img mb-2"
                  style="max-height: 200px; max-width: 100%; object-fit: contain;"
                >
                <div>
                  <VBtn
                    size="small"
                    color="error"
                    variant="text"
                    @click.stop="previewImage = null; base64Image = null"
                  >
                    Cambiar Fotografía
                  </VBtn>
                </div>
              </div>
            </div>
          </VCol>

          <!-- Campos auxiliares opcionales para Planilla -->
          <template v-if="tipoDocumento === 'planilla'">
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="totalVentas"
                type="number"
                label="Ventas Declaradas (Bs)"
                placeholder="Opcional (OCR)"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="totalGastos"
                type="number"
                label="Gastos Declarados (Bs)"
                placeholder="Opcional (OCR)"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="montoSobre"
                type="number"
                label="Monto en Sobre (Bs)"
                placeholder="Opcional (OCR)"
                density="compact"
              />
            </VCol>
          </template>

          <!-- Campos auxiliares opcionales para Voucher -->
          <template v-else>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="bancoNombre"
                label="Banco"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="nroOperacion"
                label="Nro. Operación"
                placeholder="Ej. DEP-89312"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="montoDeposito"
                type="number"
                label="Monto Depositado (Bs)"
                placeholder="Opcional (OCR)"
                density="compact"
              />
            </VCol>
          </template>
        </VRow>
      </VCardText>

      <VCardActions class="border-t pa-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :loading="isLoading"
          :disabled="!base64Image"
          @click="procesarDocumento"
        >
          <VIcon
            start
            icon="ri-sparkling-fill"
          />
          Analizar con Gemini Vision
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.upload-dropzone {
  border: 2px dashed rgba(var(--v-theme-primary), 0.4);
  background-color: rgba(var(--v-theme-primary), 0.03);
  transition: all 0.2s ease;
}

.upload-dropzone:hover {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.08);
}
</style>
