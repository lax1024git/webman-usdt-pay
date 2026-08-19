import request from '@/axios'

export const getLangListApi = (params?: Record<string, any>) => {
  return request.get({ url: '/admin/langs', params })
}

export const getLangOptionsApi = (enabledOnly = true) => {
  return request.get({ url: '/admin/langs/options', params: { enabled_only: enabledOnly ? 1 : 0 } })
}

export const getLangDetailApi = (id: number) => {
  return request.get({ url: `/admin/langs/${id}` })
}

export const createLangApi = (data: Record<string, any>) => {
  return request.post({ url: '/admin/langs', data })
}

export const updateLangApi = (id: number, data: Record<string, any>) => {
  return request.put({ url: `/admin/langs/${id}`, data })
}

export const deleteLangApi = (id: number) => {
  return request.delete({ url: `/admin/langs/${id}` })
}
