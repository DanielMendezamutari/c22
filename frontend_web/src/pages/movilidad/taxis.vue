<script setup>
import { ref, onMounted, computed } from 'vue'
import { axiosIns } from '@/plugins/axios'

const registros = ref([])
const resumen = ref({})
const tarifas = ref([])
const isLoading = ref(false)
const showSimularModal = ref(false)

const mensajeInput = ref('')
const isParsing = ref(false)
const snackbar = ref({ show: false, message: '', color: 'success' })

const fetchReporte = async () => {
  isLoading.value = true
  try {
    const res = await axiosIns.get('/taxis/reporte-diario')
    if (res.data?.success) {
      registros.value = res.data.registros || []
      resumen.value = res.data.resumen || {}
    }
  } catch (err) {
    console.error('Error fetching taxis report:', err)
  } finally {
    isLoading.value = false
  }
}

const fetchTarifas = async () => {
  try {
    const res = await axiosIns.get('/taxis/tarifas')
    if (res.data?.success) {
      tarifas.value = res.data.data || []
    }
  } catch (err) {
    console.error('Error fetching tarifas:', err)
  }
}

const procesarMensajeWhatsApp = async () => {
  if (!mensajeInput.value.trim()) {
    snackbar.value = { show: true, message: 'Ingresa un mensaje de WhatsApp para auditar.', color: 'warning' }
    return
  }

  isParsing.value = true
  try {
    const res = await axiosIns.post('/taxis/procesar-mensaje', {
      mensaje: mensajeInput.value,
    })

    if (res.data?.success) {
      snackbar.value = {
        show: true,
        message: 'Mensaje analizado con IA: ' + res.data.data.estado_auditoria,
        color: res.data.data.estado_auditoria === 'conforme' ? 'success' : 'warning',
      }
      showSimularModal.value = false
      mensajeInput.value = ''
      await fetchReporte()
    }
  } catch (err) {
    snackbar.value = { show: true, message: err.response?.data?.error || err.message, color: 'error' }
  } finally {
    isParsing.value = false
  }
}

const setDemoMensaje = (texto) => {
  mensajeInput.value = texto
}

const getEstadoBadge = (estado) => {
  switch (estado) {
    case 'conforme':
      return { color: 'success', text: 'Tarifa Conforme', icon: 'ri-checkbox-circle-line' }
    case 'sobreprecio_detectado':
      return { color: 'error', text: 'Sobreprecio Detectado', icon: 'ri-alert-line' }
    case 'carrera_duplicada':
      return { color: 'warning', text: 'Carrera Duplicada', icon: 'ri-file-copy-line' }
    default:
      return { color: 'secondary', text: estado, icon: 'ri-information-line' }
  }
}

onMounted(() => {
  fetchReporte()
  fetchTarifas()
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
              icon="ri-taxi-line"
              size="28"
            />
          </VAvatar>
          <div>
            <h3 class="text-h5 font-weight-bold mb-0">
              Supervisión de Taxis, Rotación de Chicas &amp; Comisiones (IA)
            </h3>
            <span class="text-caption text-medium-emphasis">
              Auditoría inteligente de mensajes de WhatsApp contra tarifario paramétrico y control de duplicidad
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            color="primary"
            prepend-icon="ri-chat-voice-line"
            @click="showSimularModal = true"
          >
            Auditar Mensaje WhatsApp
          </VBtn>

          <VBtn
            variant="tonal"
            icon="ri-refresh-line"
            :loading="isLoading"
            @click="fetchReporte"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- Resumen KPIs de Movilidad -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard class="border-s-lg border-primary">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="primary" variant="tonal" size="44" rounded>
              <VIcon icon="ri-roadster-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Carreras Realizadas</span>
              <h4 class="text-h5 font-weight-bold">{{ resumen.total_carreras || 0 }}</h4>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="border-s-lg border-success">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="success" variant="tonal" size="44" rounded>
              <VIcon icon="ri-money-dollar-circle-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Total Gasto en Taxis</span>
              <h4 class="text-h5 font-weight-bold text-success">{{ resumen.total_gasto_bs || 0 }} Bs</h4>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="border-s-lg border-error">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="error" variant="tonal" size="44" rounded>
              <VIcon icon="ri-alert-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Sobreprecios Detectados</span>
              <h4 class="text-h5 font-weight-bold text-error">{{ resumen.total_sobreprecio_bs || 0 }} Bs</h4>
              <span class="text-caption text-error">{{ resumen.alertas_sobreprecio || 0 }} alertas</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="border-s-lg border-warning">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="warning" variant="tonal" size="44" rounded>
              <VIcon icon="ri-file-copy-2-line" size="24" />
            </VAvatar>
            <div>
              <span class="text-caption text-disabled">Carreras Duplicadas</span>
              <h4 class="text-h5 font-weight-bold text-warning">{{ resumen.carreras_duplicadas || 0 }}</h4>
              <span class="text-caption text-warning">Mismo tramo &lt;30m</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tarifario de Referencia entre Casas -->
    <VCard class="mb-6">
      <VCardItem class="border-b py-3">
        <VCardTitle class="text-subtitle-1 font-weight-bold">
          Tarifario Estándar de Referencia entre Casas (Tramos Oficiales)
        </VCardTitle>
      </VCardItem>
      <VCardText class="pa-4">
        <div class="d-flex flex-wrap gap-4">
          <VChip color="primary" variant="tonal">
            <strong>Casa22 ↔ Casa Coron:</strong> 15 Bs (máx. 20 Bs)
          </VChip>
          <VChip color="primary" variant="tonal">
            <strong>Casa22 ↔ Madan:</strong> 15 Bs (máx. 20 Bs)
          </VChip>
          <VChip color="primary" variant="tonal">
            <strong>Casa Coron ↔ Madan:</strong> 20 Bs (máx. 25 Bs)
          </VChip>
        </div>
      </VCardText>
    </VCard>

    <!-- Tabla de Traslados de Taxis -->
    <VCard>
      <VCardItem class="border-b">
        <VCardTitle>Registro Diario de Movilidad y Rotación</VCardTitle>
        <VCardSubtitle>Carreras extraídas de los mensajes del grupo de WhatsApp con IA</VCardSubtitle>
      </VCardItem>

      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th>HORA</th>
            <th>TRAMO (ORIGEN &rarr; DESTINO)</th>
            <th>PERSONAL / CHICAS</th>
            <th>MONTO COBRADO</th>
            <th>TARIFA REF.</th>
            <th>SOBREPRECIO</th>
            <th>ESTADO AUDITORÍA</th>
            <th>MENSAJE ORIGINAL</th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="registros.length === 0 && !isLoading">
            <td colspan="8" class="text-center py-6 text-disabled">
              No se han procesado carreras para hoy. Presiona "+ Auditar Mensaje WhatsApp" para enviar el primer reporte.
            </td>
          </tr>

          <tr
            v-for="item in registros"
            :key="item.id"
          >
            <td>
              <span class="text-caption">{{ new Date(item.fecha_hora).toLocaleTimeString() }}</span>
            </td>
            <td>
              <strong>{{ item.origen_texto }}</strong> &rarr; <strong>{{ item.destino_texto }}</strong>
            </td>
            <td>
              <span class="text-body-2">{{ item.personal_trasladado || 'N/A' }}</span>
              <span v-if="item.cantidad_pasajeros > 1" class="text-caption text-disabled ms-1">
                ({{ item.cantidad_pasajeros }} pers.)
              </span>
            </td>
            <td>
              <strong class="text-h6" style="font-size: 14px;">{{ item.monto_cobrado_bs }} Bs</strong>
            </td>
            <td>
              <span class="text-caption text-disabled">{{ item.tarifa_referencia_bs || 15 }} Bs</span>
            </td>
            <td>
              <span
                v-if="item.sobreprecio_detectado_bs > 0"
                class="text-error font-weight-bold"
              >
                +{{ item.sobreprecio_detectado_bs }} Bs
              </span>
              <span v-else class="text-success text-caption">0.00 Bs</span>
            </td>
            <td>
              <VChip
                :color="getEstadoBadge(item.estado_auditoria).color"
                size="small"
                variant="tonal"
              >
                <VIcon start :icon="getEstadoBadge(item.estado_auditoria).icon" size="14" />
                {{ getEstadoBadge(item.estado_auditoria).text }}
              </VChip>
            </td>
            <td>
              <span class="text-caption text-disabled text-truncate d-inline-block" style="max-width: 200px;">
                {{ item.mensaje_original_whatsapp || '-' }}
              </span>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Modal de Ingesta de Mensaje WhatsApp -->
    <VDialog
      v-model="showSimularModal"
      max-width="600"
    >
      <VCard>
        <VCardItem class="border-b">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" rounded size="40" class="me-2">
              <VIcon icon="ri-chat-smile-2-line" size="24" />
            </VAvatar>
          </template>
          <VCardTitle>Auditoría de Mensaje de WhatsApp (NLP)</VCardTitle>
          <VCardSubtitle>Pega el texto enviado por el chofer, encargado o supervisora al grupo</VCardSubtitle>
        </VCardItem>

        <VCardText class="pa-4">
          <VTextarea
            v-model="mensajeInput"
            label="Mensaje de WhatsApp"
            placeholder="Ej: Taxi de casa22 a madan fueron sofia y lucia cobro 35 bs"
            rows="4"
            class="mb-3"
          />

          <div class="text-caption text-disabled mb-2">Mensajes de prueba rápida:</div>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <VChip
              size="x-small"
              variant="tonal"
              color="success"
              @click="setDemoMensaje('Taxi de Casa22 a Madan fue Sofia cobro 15 bs')"
            >
              15 Bs (Tarifa Normal)
            </VChip>

            <VChip
              size="x-small"
              variant="tonal"
              color="error"
              @click="setDemoMensaje('Movilidad Casa22 a Madan Sofia cobro 35 bs por lluvia')"
            >
              35 Bs (Sobreprecio Inflado)
            </VChip>

            <VChip
              size="x-small"
              variant="tonal"
              color="warning"
              @click="setDemoMensaje('Carrera Coron a Madan 20bs Maria y Carla')"
            >
              20 Bs (Coron a Madan)
            </VChip>
          </div>
        </VCardText>

        <VCardActions class="border-t pa-4">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="showSimularModal = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="isParsing" @click="procesarMensajeWhatsApp">
            <VIcon start icon="ri-brain-line" />
            Analizar con IA
          </VBtn>
        </VCardActions>
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
</style>
