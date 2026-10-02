import axios from 'axios'
import { router } from '@/plugins/1.router'

// Base URL predeterminada con fallback dinámico (Hosting de producción o servidor local)
const getBaseUrl = () => {
  if (import.meta.env.VITE_API_BASE_URL) {
    return import.meta.env.VITE_API_BASE_URL
  }

  const customUrl = localStorage.getItem('c22_api_url')
  if (customUrl) {
    return customUrl.endsWith('/') ? `${customUrl}api/v1` : `${customUrl}/api/v1`
  }

  // Si estamos en localhost usar ruta local o dominio c22.ribersoft.com
  if (typeof window !== 'undefined' && window.location.hostname === 'c22.ribersoft.com') {
    return 'https://c22.ribersoft.com/api/v1'
  }

  return 'https://c22.ribersoft.com/api/v1'
}

const axiosIns = axios.create({
  baseURL: getBaseUrl(),
  timeout: 30000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// ℹ️ Interceptor de Request: Inyectar Bearer JWT Token
axiosIns.interceptors.request.use(config => {
  const token = localStorage.getItem('c22_token')
  if (token) {
    config.headers = config.headers || {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
}, error => {
  return Promise.reject(error)
})

// ℹ️ Interceptor de Response: Manejo global de 401 y redirección a login
axiosIns.interceptors.response.use(
  response => response,
  error => {
    const { config, response } = error

    // Si responde 401 y no es la petición de login, limpiar sesión y redirigir
    if (response && response.status === 401 && !config.url.includes('/auth/web/login')) {
      localStorage.removeItem('c22_token')
      localStorage.removeItem('c22_user')
      localStorage.removeItem('c22_permissions')

      if (router && router.currentRoute.value.path !== '/login') {
        router.push('/login')
      }
    }

    return Promise.reject(error)
  },
)

export { axiosIns }

export default function (app) {
  app.config.globalProperties.$axios = axiosIns
  app.provide('axios', axiosIns)
}
