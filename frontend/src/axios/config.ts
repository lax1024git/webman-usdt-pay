import { AxiosResponse, InternalAxiosRequestConfig } from './types'
import { ElMessage } from 'element-plus'
import qs from 'qs'
import { SUCCESS_CODE, TRANSFORM_REQUEST_DATA } from '@/constants'
import { useUserStoreWithOut } from '@/store/modules/user'
import { useLocaleStoreWithOut } from '@/store/modules/locale'
import { frontLocaleToApi } from '@/utils/apiLocale'
import { objToFormData } from '@/utils'
import { refreshTokenApi } from '@/api/login'

let isRefreshing = false
let refreshQueue: Array<(token: string) => void> = []

const enqueueRefresh = (callback: (token: string) => void) => {
  refreshQueue.push(callback)
}

const flushRefreshQueue = (token: string) => {
  refreshQueue.forEach((callback) => callback(token))
  refreshQueue = []
}

const tryRefreshToken = async (): Promise<string | null> => {
  const userStore = useUserStoreWithOut()
  const refreshToken = userStore.getRefreshToken
  if (!refreshToken) {
    return null
  }

  try {
    const res = await refreshTokenApi(refreshToken)
    const newToken = res.data?.token
    if (!newToken) {
      return null
    }
    userStore.setToken(newToken)
    if (res.data?.refresh_token) {
      userStore.setRefreshToken(res.data.refresh_token)
    }
    return newToken
  } catch {
    return null
  }
}

const defaultRequestInterceptors = (config: InternalAxiosRequestConfig) => {
  if (
    config.method === 'post' &&
    config.headers['Content-Type'] === 'application/x-www-form-urlencoded'
  ) {
    config.data = qs.stringify(config.data)
  } else if (
    TRANSFORM_REQUEST_DATA &&
    config.method === 'post' &&
    config.headers['Content-Type'] === 'multipart/form-data' &&
    !(config.data instanceof FormData)
  ) {
    config.data = objToFormData(config.data)
  }
  if (config.method === 'get' && config.params) {
    let url = config.url as string
    url += '?'
    const keys = Object.keys(config.params)
    for (const key of keys) {
      if (config.params[key] !== void 0 && config.params[key] !== null) {
        url += `${key}=${encodeURIComponent(config.params[key])}&`
      }
    }
    url = url.substring(0, url.length - 1)
    config.params = {}
    config.url = url
  }

  // 让后端 app_lang / sy_lang_items 按当前后台语言翻译接口文案
  try {
    const localeStore = useLocaleStoreWithOut()
    const apiLang = frontLocaleToApi(localeStore.getCurrentLocale?.lang)
    config.headers = config.headers || {}
    config.headers['X-App-Lang'] = apiLang
    if (config.method === 'get') {
      const url = String(config.url || '')
      if (!/[?&]lang=/.test(url)) {
        config.url = url.includes('?') ? `${url}&lang=${encodeURIComponent(apiLang)}` : `${url}?lang=${encodeURIComponent(apiLang)}`
      }
    } else {
      const url = String(config.url || '')
      if (!/[?&]lang=/.test(url)) {
        config.url = url.includes('?') ? `${url}&lang=${encodeURIComponent(apiLang)}` : `${url}?lang=${encodeURIComponent(apiLang)}`
      }
    }
  } catch {
    // pinia 未就绪时忽略
  }

  return config
}

const defaultResponseInterceptors = async (response: AxiosResponse) => {
  if (response?.config?.responseType === 'blob') {
    return response
  } else if (response.data.code === SUCCESS_CODE) {
    return response.data
  } else {
    const msg = response?.data?.msg || response?.data?.message || '请求失败'
    const code = response?.data?.code
    const originalConfig = response.config as InternalAxiosRequestConfig & { _retry?: boolean }

    if (
      (code === 40102 || code === 40103) &&
      !originalConfig._retry &&
      !originalConfig.url?.includes('/admin/refresh')
    ) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          enqueueRefresh((token) => {
            originalConfig.headers = originalConfig.headers || {}
            originalConfig.headers.Authorization = `Bearer ${token}`
            originalConfig._retry = true
            import('./service').then(({ default: service }) => {
              service.request(originalConfig).then(resolve).catch(reject)
            })
          })
        })
      }

      originalConfig._retry = true
      isRefreshing = true
      const newToken = await tryRefreshToken()
      isRefreshing = false

      if (newToken) {
        flushRefreshQueue(newToken)
        originalConfig.headers = originalConfig.headers || {}
        originalConfig.headers.Authorization = `Bearer ${newToken}`
        const { default: service } = await import('./service')
        return service.request(originalConfig)
      }
    }

    ElMessage.error(msg)
    if (code === 401 || code === 40101 || code === 40102 || code === 40103) {
      const userStore = useUserStoreWithOut()
      userStore.logout()
    }
    return Promise.reject(new Error(msg))
  }
}

export { defaultResponseInterceptors, defaultRequestInterceptors }
