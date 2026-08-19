import request from '@/axios'

export const getIpWhitelistListApi = (params: Record<string, any>) => {
  return request.get({ url: '/admin/ip-whitelists', params })
}

export const createIpWhitelistApi = (data: Record<string, any>) => {
  return request.post({ url: '/admin/ip-whitelists', data })
}

export const updateIpWhitelistApi = (id: number, data: Record<string, any>) => {
  return request.put({ url: `/admin/ip-whitelists/${id}`, data })
}

export const deleteIpWhitelistApi = (id: number, data?: { google_code?: string }) => {
  return request.delete({
    url: `/admin/ip-whitelists/${id}`,
    data,
    params: data?.google_code ? { google_code: data.google_code } : undefined
  })
}
