import request from '@/axios'

export const getLogListApi = (params: Record<string, any>) => {
  return request.get({ url: '/admin/logs', params })
}
