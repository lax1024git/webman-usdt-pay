import axios from 'axios'
import { ElMessage } from 'element-plus'
import { CONTENT_TYPE, SUCCESS_CODE } from '@/constants'
import {
  clearMerchantAuth,
  getMerchantRefreshToken,
  getMerchantToken,
  setMerchantRefreshToken,
  setMerchantToken
} from '@/utils/merchantAuth'

const PATH_URL = import.meta.env.VITE_API_BASE_PATH

const client = axios.create({
  baseURL: PATH_URL,
  timeout: 120000,
  headers: { 'Content-Type': CONTENT_TYPE }
})

client.interceptors.request.use((config) => {
  const token = getMerchantToken()
  if (token) {
    config.headers = config.headers || {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

client.interceptors.response.use(
  async (response) => {
    const data = response.data
    if (data?.code === SUCCESS_CODE) {
      return data
    }
    if (data?.code === 40101 || data?.code === 40102) {
      const refreshed = await tryRefresh()
      if (refreshed && response.config) {
        response.config.headers = response.config.headers || {}
        response.config.headers.Authorization = `Bearer ${getMerchantToken()}`
        return client.request(response.config)
      }
      clearMerchantAuth()
      if (!window.location.hash.includes('/merchant-portal/login')) {
        window.location.hash = '#/merchant-portal/login'
      }
    }
    ElMessage.error(data?.msg || '请求失败')
    return Promise.reject(data)
  },
  (error) => {
    ElMessage.error(error?.message || '网络错误')
    return Promise.reject(error)
  }
)

let refreshing = false
async function tryRefresh(): Promise<boolean> {
  if (refreshing) return false
  const refreshToken = getMerchantRefreshToken()
  if (!refreshToken) return false
  refreshing = true
  try {
    const res = await axios.post(`${PATH_URL}/merchant/refresh`, { refresh_token: refreshToken })
    if (res.data?.code === SUCCESS_CODE && res.data?.data?.token) {
      setMerchantToken(res.data.data.token)
      if (res.data.data.refresh_token) {
        setMerchantRefreshToken(res.data.data.refresh_token)
      }
      return true
    }
  } catch {
    // ignore
  } finally {
    refreshing = false
  }
  return false
}

export const merchantLoginApi = (data: { email: string; password: string }) =>
  client.post('/merchant/login', data)

export const merchantLogoutApi = (data: { refresh_token: string }) =>
  client.post('/merchant/logout', data)

export const merchantMeApi = () => client.get('/merchant/me')

export const merchantChangePasswordApi = (data: { old_password: string; new_password: string }) =>
  client.post('/merchant/change-password', data)

export const merchantGetSettingsApi = () => client.get('/merchant/settings')

export const merchantUpdateSettingsApi = (data: Record<string, any>) =>
  client.put('/merchant/settings', data)

export const merchantResetSecretApi = (data: { login_password: string }) =>
  client.post('/merchant/settings/reset-secret', data)

export const merchantDepositListApi = (params: Record<string, any>) =>
  client.get('/merchant/deposits', { params })

export const merchantDepositShowApi = (id: number) => client.get(`/merchant/deposits/${id}`)

export const merchantWithdrawListApi = (params: Record<string, any>) =>
  client.get('/merchant/withdrawals', { params })

export const merchantWithdrawShowApi = (id: number) => client.get(`/merchant/withdrawals/${id}`)

export const merchantBalanceApi = (params?: Record<string, any>) =>
  client.get('/merchant/account/balance', { params })

export const merchantLedgerListApi = (params: Record<string, any>) =>
  client.get('/merchant/account/ledgers', { params })

export const merchantWebhookLogListApi = (params: Record<string, any>) =>
  client.get('/merchant/webhook-logs', { params })

export const merchantRetryWebhookApi = (id: number) =>
  client.post(`/merchant/webhook-logs/${id}/retry`)

export const merchantStatisticsApi = (params?: Record<string, any>) =>
  client.get('/merchant/statistics', { params })
