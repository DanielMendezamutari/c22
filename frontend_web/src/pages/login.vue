<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useGenerateImageVariant } from '@/@core/composable/useGenerateImageVariant'
import authV2LoginIllustrationBorderedDark from '@images/pages/auth-v2-login-illustration-bordered-dark.png'
import authV2LoginIllustrationBorderedLight from '@images/pages/auth-v2-login-illustration-bordered-light.png'
import authV2LoginIllustrationDark from '@images/pages/auth-v2-login-illustration-dark.png'
import authV2LoginIllustrationLight from '@images/pages/auth-v2-login-illustration-light.png'
import authV2LoginMaskDark from '@images/pages/auth-v2-login-mask-dark.png'
import authV2LoginMaskLight from '@images/pages/auth-v2-login-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({ meta: { layout: 'blank' } })

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  email: '',
  password: '',
  remember: true,
})

const isPasswordVisible = ref(false)
const errorMessage = ref('')
const isSubmitting = ref(false)

const authV2LoginMask = useGenerateImageVariant(authV2LoginMaskLight, authV2LoginMaskDark)
const authV2LoginIllustration = useGenerateImageVariant(
  authV2LoginIllustrationLight,
  authV2LoginIllustrationDark,
  authV2LoginIllustrationBorderedLight,
  authV2LoginIllustrationBorderedDark,
  true,
)

const setDemoCredentials = (email, password) => {
  form.value.email = email
  form.value.password = password
}

const onSubmit = async () => {
  errorMessage.value = ''
  if (!form.value.email || !form.value.password) {
    errorMessage.value = 'Por favor ingresa tu correo y contraseña.'
    return
  }

  isSubmitting.value = true
  try {
    const result = await authStore.login(form.value.email, form.value.password)
    if (result.success) {
      router.push(result.redirect_to || '/dashboard/super-admin')
    } else {
      errorMessage.value = result.error || 'Credenciales inválidas'
    }
  } catch (err) {
    errorMessage.value = err.message || 'Error inesperado al conectar con el servidor'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <RouterLink to="/">
    <div class="app-logo auth-logo">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="app-logo-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    no-gutters
    class="auth-wrapper"
  >
    <VCol
      md="8"
      class="d-none d-md-flex align-center justify-center position-relative"
    >
      <div class="d-flex align-center justify-center pa-10">
        <img
          :src="authV2LoginIllustration"
          class="auth-illustration w-100"
          alt="auth-illustration"
        >
      </div>
      <VImg
        :src="authV2LoginMask"
        class="d-none d-md-flex auth-footer-mask"
        alt="auth-mask"
      />
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
      style="background-color: rgb(var(--v-theme-surface));"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-5 pa-lg-7"
      >
        <VCardText>
          <div class="d-flex align-center mb-2">
            <VAvatar
              color="primary"
              variant="tonal"
              size="42"
              class="me-3"
            >
              <VIcon
                icon="ri-shield-keyhole-line"
                size="24"
              />
            </VAvatar>
            <div>
              <h4 class="text-h4 mb-0">
                Punto Frío C22
              </h4>
              <span class="text-caption text-medium-emphasis">Consola de Control en Tiempo Real</span>
            </div>
          </div>

          <p class="text-body-2 mb-4">
            Ingresa con tus credenciales autorizadas de Super Usuario, Dueño o Contabilidad.
          </p>

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
        </VCardText>

        <VCardText>
          <VForm @submit.prevent="onSubmit">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <VTextField
                  v-model="form.email"
                  autofocus
                  label="Correo Electrónico"
                  type="email"
                  placeholder="daniel@puntofrio.com"
                  prepend-inner-icon="ri-mail-line"
                  required
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  label="Contraseña"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  prepend-inner-icon="ri-lock-password-line"
                  :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                  required
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center justify-space-between flex-wrap my-4 gap-x-2">
                  <VCheckbox
                    v-model="form.remember"
                    label="Recordar sesión"
                    density="compact"
                  />
                  <span class="text-caption text-primary">Sincronización 1 min</span>
                </div>

                <!-- login button -->
                <VBtn
                  block
                  type="submit"
                  size="large"
                  color="primary"
                  :loading="isSubmitting"
                  class="mb-4"
                >
                  <VIcon
                    start
                    icon="ri-login-box-line"
                  />
                  Iniciar Sesión
                </VBtn>
              </VCol>

              <!-- Accesos rápidos de desarrollo/prueba -->
              <VCol cols="12">
                <VDivider class="my-2">
                  <span class="text-caption text-disabled px-2">Acceso Rápido por Rol</span>
                </VDivider>

                <div class="d-flex flex-wrap gap-2 justify-center mt-2">
                  <VChip
                    color="primary"
                    variant="tonal"
                    size="small"
                    class="cursor-pointer"
                    @click="setDemoCredentials('daniel@puntofrio.com', '123456')"
                  >
                    <VIcon
                      start
                      icon="ri-code-s-slash-line"
                      size="14"
                    />
                    Super Admin (Daniel)
                  </VChip>

                  <VChip
                    color="success"
                    variant="tonal"
                    size="small"
                    class="cursor-pointer"
                    @click="setDemoCredentials('dueno@puntofrio.com', '123456')"
                  >
                    <VIcon
                      start
                      icon="ri-user-star-line"
                      size="14"
                    />
                    Dueño Ejecutivo
                  </VChip>

                  <VChip
                    color="info"
                    variant="tonal"
                    size="small"
                    class="cursor-pointer"
                    @click="setDemoCredentials('contadora@puntofrio.com', '123456')"
                  >
                    <VIcon
                      start
                      icon="ri-calculator-line"
                      size="14"
                    />
                    Contabilidad
                  </VChip>
                </div>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth.scss";

.cursor-pointer {
  cursor: pointer;
  transition: transform 0.15s ease;
  &:hover {
    transform: translateY(-2px);
  }
}
</style>
