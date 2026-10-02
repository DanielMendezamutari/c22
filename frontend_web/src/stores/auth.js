import { defineStore } from 'pinia'
import { axiosIns } from '@/plugins/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('c22_token') || null,
    user: JSON.parse(localStorage.getItem('c22_user') || 'null'),
    permissions: JSON.parse(localStorage.getItem('c22_permissions') || 'null'),
    isLoading: false,
    errorMessage: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isSuperAdmin: (state) => state.user?.rol === 'super_admin' || state.permissions?.es_super_admin,
    isDueno: (state) => state.user?.rol === 'dueno' || state.permissions?.es_dueno,
    isContabilidad: (state) => ['contadora', 'auxiliar_contable'].includes(state.user?.rol) || state.permissions?.es_contabilidad,
    userName: (state) => state.user ? `${state.user.nombre} ${state.user.apellido}` : 'Usuario',
  },

  actions: {
    async login(email, password) {
      this.isLoading = true
      this.errorMessage = null

      try {
        const response = await axiosIns.post('/auth/web/login', {
          email,
          password,
        })

        if (response.data && response.data.success) {
          const { token, usuario, permisos, redirect_to } = response.data.data

          this.token = token
          this.user = usuario
          this.permissions = permisos

          localStorage.setItem('c22_token', token)
          localStorage.setItem('c22_user', JSON.stringify(usuario))
          localStorage.setItem('c22_permissions', JSON.stringify(permisos))

          return { success: true, redirect_to }
        } else {
          throw new Error(response.data?.error || 'Error al iniciar sesión')
        }
      } catch (err) {
        const msg = err.response?.data?.error || err.message || 'Error de conexión con el servidor'
        this.errorMessage = msg
        return { success: false, error: msg }
      } finally {
        this.isLoading = false
      }
    },

    async loginPin(pin) {
      this.isLoading = true
      this.errorMessage = null

      try {
        const response = await axiosIns.post('/auth/login-pin', {
          pin: String(pin).trim(),
        })

        if (response.data && response.data.success) {
          const data = response.data.data
          const token = data.token
          const usuario = data.usuario
          const permisos = data.permisos || {
            es_super_admin: usuario.rol === 'admin' || usuario.rol === 'super_admin',
            es_dueno: ['admin', 'super_admin', 'dueno'].includes(usuario.rol),
            es_contabilidad: ['admin', 'super_admin', 'contadora', 'auxiliar_contable'].includes(usuario.rol),
            puede_forzar_resincronizacion: true,
            puede_ver_logs_en_vivo: true,
            puede_auditar_recaudacion: true,
            puede_aprobar_caja_chica: true,
          }

          this.token = token
          this.user = usuario
          this.permissions = permisos

          localStorage.setItem('c22_token', token)
          localStorage.setItem('c22_user', JSON.stringify(usuario))
          localStorage.setItem('c22_permissions', JSON.stringify(permisos))

          return { success: true, redirect_to: data.redirect_to || '/dashboard/super-admin' }
        } else {
          throw new Error(response.data?.error || 'PIN incorrecto o usuario inactivo')
        }
      } catch (err) {
        const msg = err.response?.data?.error || err.message || 'Error al autenticar PIN'
        this.errorMessage = msg
        return { success: false, error: msg }
      } finally {
        this.isLoading = false
      }
    },

    logout() {
      this.token = null
      this.user = null
      this.permissions = null
      localStorage.removeItem('c22_token')
      localStorage.removeItem('c22_user')
      localStorage.removeItem('c22_permissions')
    },
  },
})
