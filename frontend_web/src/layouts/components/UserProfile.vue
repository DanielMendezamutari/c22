<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import avatar1 from '@images/avatars/avatar-1.png'

const router = useRouter()
const authStore = useAuthStore()

const userName = computed(() => {
  if (authStore.user?.nombre) {
    return `${authStore.user.nombre} ${authStore.user.apellido || ''}`.trim()
  }
  return 'Usuario Autorizado'
})

const userRole = computed(() => {
  const rol = authStore.user?.rol
  switch (rol) {
    case 'super_admin':
    case 'admin':
      return 'Super Administrador'
    case 'dueno':
      return 'Dueño Ejecutivo'
    case 'contadora':
      return 'Contabilidad Central'
    case 'auxiliar_contable':
      return 'Auxiliar Contable'
    default:
      return 'Usuario Operativo'
  }
})

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <VBadge
    dot
    bordered
    location="bottom right"
    offset-x="2"
    offset-y="2"
    color="success"
    class="user-profile-badge"
  >
    <VAvatar
      class="cursor-pointer"
      size="38"
    >
      <VImg :src="avatar1" />

      <!-- Menú Desplegable de Perfil -->
      <VMenu
        activator="parent"
        width="240"
        location="bottom end"
        offset="15px"
      >
        <VList>
          <!-- Encabezado con datos reales del usuario -->
          <VListItem class="px-4 py-2">
            <div class="d-flex gap-x-3 align-center">
              <VAvatar size="40">
                <VImg :src="avatar1" />
              </VAvatar>

              <div class="overflow-hidden">
                <div class="text-subtitle-2 font-weight-bold text-high-emphasis text-truncate">
                  {{ userName }}
                </div>
                <div class="text-caption text-primary font-weight-medium">
                  {{ userRole }}
                </div>
              </div>
            </div>
          </VListItem>

          <VDivider class="my-1" />

          <!-- Opciones limpias y corporativas -->
          <VListItem
            to="/dashboard/super-admin"
            class="px-4"
          >
            <template #prepend>
              <VIcon
                icon="ri-dashboard-line"
                size="20"
                class="me-2"
              />
            </template>
            <VListItemTitle>Consola de Control</VListItemTitle>
          </VListItem>

          <VListItem
            to="/auditoria/recaudaciones"
            class="px-4"
          >
            <template #prepend>
              <VIcon
                icon="ri-file-shield-line"
                size="20"
                class="me-2"
              />
            </template>
            <VListItemTitle>Auditoría de Planillas</VListItemTitle>
          </VListItem>

          <VDivider class="my-2" />

          <!-- Botón de Cerrar Sesión -->
          <VListItem class="px-4">
            <VBtn
              block
              color="error"
              variant="tonal"
              size="small"
              prepend-icon="ri-logout-box-r-line"
              @click="handleLogout"
            >
              Cerrar Sesión
            </VBtn>
          </VListItem>
        </VList>
      </VMenu>
    </VAvatar>
  </VBadge>
</template>

<style lang="scss">
.user-profile-badge {
  &.v-badge--bordered.v-badge--dot .v-badge__badge::after {
    color: rgb(var(--v-theme-background));
  }
}
</style>
