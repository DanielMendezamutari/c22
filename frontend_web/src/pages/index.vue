<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

onMounted(() => {
  if (authStore.isAuthenticated) {
    router.replace('/dashboard/super-admin')
  } else {
    router.replace('/login')
  }
})
</script>

<template>
  <div class="d-flex align-center justify-center" style="min-height: 400px;">
    <VProgressCircular
      indeterminate
      color="primary"
      size="48"
    />
  </div>
</template>
