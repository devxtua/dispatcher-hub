// resources/js/shopify/spa.js
import createApp from '@shopify/app-bridge'
import { getSessionToken } from '@shopify/app-bridge-utils'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

/**
 * Чтобы инициализация не выполнялась дважды (HMR/повторные импорты)
 */
if (!window.__SHOPIFY_SPA_BOOTSTRAPPED__) {
  window.__SHOPIFY_SPA_BOOTSTRAPPED__ = true

  const host = new URLSearchParams(location.search).get('host') || ''
  const apiKey = import.meta.env.VITE_SHOPIFY_API_KEY || window.APP_BRIDGE_KEY

  // Если мы не в Shopify iframe (нет host) — просто выходим, ничего не ломаем
  if (host && apiKey) {
    const appBridge = createApp({
      apiKey,
      host,
      forceRedirect: true, // если открыли вне админки — вернёт внутрь Shopify
    })

    // Получаем заголовок с актуальным session token
    const authHeader = async () => {
      const token = await getSessionToken(appBridge)
      return { Authorization: `Bearer ${token}` }
    }

    // 1) AXIOS: подставляем токен в КАЖДЫЙ запрос
    axios.interceptors.request.use(async (config) => {
      const hdr = await authHeader()
      config.headers = {
        'X-Requested-With': 'XMLHttpRequest',
        ...(config.headers || {}),
        ...hdr,
      }
      return config
    })

    // Анти-петля: при 401 пробуем один раз обновить токен и повторить запрос
    axios.interceptors.response.use(
      (r) => r,
      async (error) => {
        const cfg = error?.config || {}
        if (error?.response?.status === 401 && !cfg.__retriedWithNewToken) {
          cfg.__retriedWithNewToken = true
          const hdr = await authHeader()
          cfg.headers = { ...(cfg.headers || {}), ...hdr }
          return axios(cfg)
        }
        return Promise.reject(error)
      }
    )

    // 2) INERTIA: перед каждым визитом добавляем токен в заголовок
    router.on('before', async (visit) => {
      const hdr = await authHeader()
      visit.headers = { ...(visit.headers || {}), ...hdr }
    })

    // Экспортируем в window по желанию (необязательно)
    window.__APP_BRIDGE__ = appBridge
  }
}
