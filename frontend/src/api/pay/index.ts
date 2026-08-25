import request from '@/axios'

export const getPayMerchantListApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/merchants', params })

export const createPayMerchantApi = (data: Record<string, any>) =>
  request.post({ url: '/admin/pay/merchants', data })

export const updatePayMerchantApi = (id: number, data: Record<string, any>) =>
  request.put({ url: `/admin/pay/merchants/${id}`, data })

export const resetPayMerchantSecretApi = (id: number, data: { google_code: string }) =>
  request.post({ url: `/admin/pay/merchants/${id}/reset-secret`, data })

export const getPayPlatformListApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/platforms', params })

export const updatePayPlatformApi = (id: number, data: Record<string, any>) =>
  request.put({ url: `/admin/pay/platforms/${id}`, data })

export const getPayDepositListApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/deposits', params })

export const manualCreditDepositApi = (id: number, data: Record<string, any>) =>
  request.post({ url: `/admin/pay/deposits/${id}/manual-credit`, data })

export const getPayWithdrawListApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/withdrawals', params })

export const approvePayWithdrawApi = (id: number, data: { google_code: string }) =>
  request.post({ url: `/admin/pay/withdrawals/${id}/approve`, data })

export const rejectPayWithdrawApi = (id: number, data: { reject_reason: string; google_code: string }) =>
  request.post({ url: `/admin/pay/withdrawals/${id}/reject`, data })

export const retryPayWithdrawApi = (id: number, data: { google_code: string }) =>
  request.post({ url: `/admin/pay/withdrawals/${id}/retry-broadcast`, data })

export const getPayHotWalletApi = () =>
  request.get({ url: '/admin/pay/wallets/hot-balance' })

export const getPayWalletListApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/wallets', params })

export const getPayBlacklistListApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/blacklists', params })

export const createPayBlacklistApi = (data: Record<string, any>) =>
  request.post({ url: '/admin/pay/blacklists', data })

export const deletePayBlacklistApi = (id: number, data: { google_code: string }) =>
  request.delete({ url: `/admin/pay/blacklists/${id}`, data })

export const getPayReportSummaryApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/reports/summary', params })

export const getPayReportDailyApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/reports/daily', params })

export const getPayReportMerchantApi = (id: number, params: Record<string, any>) =>
  request.get({ url: `/admin/pay/reports/merchant/${id}`, params })

export const getPayWebhookLogListApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/webhook-logs', params })

export const retryPayWebhookApi = (id: number) =>
  request.post({ url: `/admin/pay/webhook-logs/${id}/retry` })

export const getPayCollectionListApi = (params: Record<string, any>) =>
  request.get({ url: '/admin/pay/collections', params })

export const triggerPayCollectionApi = (data: Record<string, any>) =>
  request.post({ url: '/admin/pay/collections/trigger', data })

export const retryPayCollectionApi = (id: number, data: Record<string, any>) =>
  request.post({ url: `/admin/pay/collections/${id}/retry`, data })
