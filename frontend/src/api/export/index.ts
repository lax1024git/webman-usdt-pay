import request from '@/axios'

export const getExportJobListApi = (params: Record<string, any>) => {
  return request.get({ url: '/admin/exports', params })
}

export const createExportJobApi = (data: {
  export_type: string
  filters?: Record<string, any>
}) => {
  return request.post({ url: '/admin/exports', data })
}

export const getExportJobApi = (id: number) => {
  return request.get({ url: `/admin/exports/${id}` })
}

export const deleteExportJobApi = (id: number) => {
  return request.delete({ url: `/admin/exports/${id}` })
}
