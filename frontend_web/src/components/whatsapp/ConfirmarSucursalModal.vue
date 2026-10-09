<script setup>
import { ref, onMounted } from 'vue'
import { axiosIns } from '@/plugins/axios'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  mensaje: {
    type: Object,
    default: () => null,
  },
})

const emit = defineEmits(['update:modelValue', 'confirmado'])

const isSubmitting = ref(false)
const sucursales = ref([
  { id: 1, nombre: 'Casa22', codigo: 'C22', color: 'primary' },
  { id: 2, nombre: 'Casa Coron', codigo: 'CORON', color: 'success' },
  { id: 3, nombre: 'Madan', codigo: 'MADAN', color: 'info' },
])

const confirmar = async (sucursalId) => {
  if (!props.mensaje?.id) return
  isSubmitting.value = true
  try {
    const res = await axiosIns.post('/whatsapp/confirmar-sucursal', {
      inbound_id: props.mensaje.id,
      sucursal_id: sucursalId,
    })
    if (res.data?.success) {
      emit('confirmado', { inboundId: props.mensaje.id, sucursalId })
      emit('update:modelValue', false)
    }
  } catch (err) {
    console.error('Error al confirmar sucursal:', err)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    persistent
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <VCard class="rounded-xl">
      <VCardItem class="bg-amber-lighten-5 py-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon icon="ri-question-line" color="warning" size="28" class="me-2" />
            <div>
              <div class="text-subtitle-1 font-weight-bold text-amber-darken-4">
                Confirmar Sucursal del Comprobante
              </div>
              <div class="text-caption text-medium-emphasis">
                Resolución Rápida en 1 Clic
              </div>
            </div>
          </div>
          <VBtn icon="ri-close-line" variant="text" size="small" @click="emit('update:modelValue', false)" />
        </div>
      </VCardItem>

      <VCardText class="pa-4">
        <div v-if="mensaje" class="mb-4">
          <div v-if="mensaje.media_path" class="text-center mb-3">
            <VImg
              :src="mensaje.media_path.startsWith('http') ? mensaje.media_path : `/${mensaje.media_path}`"
              max-height="200"
              class="rounded-lg border mx-auto bg-grey-lighten-4"
              cover
            />
          </div>

          <div class="d-flex justify-space-between py-1 text-body-2">
            <span class="text-medium-emphasis">Emisor WhatsApp:</span>
            <span class="font-weight-medium">{{ mensaje.sender_name || 'Encargada' }} (+{{ mensaje.sender_phone }})</span>
          </div>

          <div v-if="mensaje.raw_text" class="d-flex justify-space-between py-1 text-body-2">
            <span class="text-medium-emphasis">Nota / Caption:</span>
            <span class="font-italic">"{{ mensaje.raw_text }}"</span>
          </div>
        </div>

        <VDivider class="my-3" />

        <div class="text-center mb-2">
          <div class="text-subtitle-2 font-weight-bold mb-1">
            ¿A qué sucursal pertenece esta planilla o voucher?
          </div>
          <div class="text-caption text-medium-emphasis mb-3">
            Haz clic en la casa correspondiente para asociarla de inmediato:
          </div>
        </div>

        <div class="d-flex flex-column gap-2">
          <VBtn
            v-for="sucursal in sucursales"
            :key="sucursal.id"
            :color="sucursal.color"
            variant="tonal"
            size="large"
            class="rounded-lg text-start justify-space-between"
            :loading="isSubmitting"
            @click="confirmar(sucursal.id)"
          >
            <span class="d-flex align-center">
              <VIcon icon="ri-store-2-line" class="me-2" />
              <strong>{{ sucursal.nombre }}</strong> ({{ sucursal.codigo }})
            </span>
            <VIcon icon="ri-arrow-right-s-line" />
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>
