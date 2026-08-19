import request from '@/axios'

export const getDictListApi = (params: any) => request.get({ url: '/admin/dicts', params })
export const createDictApi = (data: any) => request.post({ url: '/admin/dicts', data })
export const updateDictApi = (id: number, data: any) =>
  request.put({ url: `/admin/dicts/${id}`, data })
export const deleteDictApi = (id: number, data?: { google_code?: string }) =>
  request.delete({
    url: `/admin/dicts/${id}`,
    data,
    params: data?.google_code ? { google_code: data.google_code } : undefined
  })
export const getDictItemsApi = (id: number) => request.get({ url: `/admin/dicts/${id}/items` })
export const saveDictItemsApi = (
  id: number,
  items: any[],
  extra?: { google_code?: string }
) => request.put({ url: `/admin/dicts/${id}/items`, data: { items, ...extra } })
export const getDictByCodeApi = (code: string) => request.get({ url: `/admin/dicts/code/${code}` })
