import request from '@/axios'

export const getLangTextListApi = (params?: Record<string, any>) => {
  return request.get({ url: '/admin/lang-texts', params })
}

export const getLangTextDetailApi = (id: number) => {
  return request.get({ url: `/admin/lang-texts/${id}` })
}

export const saveLangTextApi = (data: Record<string, any>) => {
  return request.post({ url: '/admin/lang-texts', data })
}

export const deleteLangTextApi = (id: number) => {
  return request.delete({ url: `/admin/lang-texts/${id}` })
}

export const exportLangTextApi = () => {
  return request.post({ url: '/admin/lang-texts/export' })
}

export const translateLangTextApi = (id: number, data?: { overwrite?: number }) => {
  return request.post({ url: `/admin/lang-texts/${id}/translate`, data: data || {} })
}

export const translateLangTextPreviewApi = (data: {
  title: string
  overwrite?: number
  existing?: Record<string, string>
}) => {
  return request.post({ url: '/admin/lang-texts/translate', data })
}

export const importLangTextApi = (data: FormData) => {
  return request.post({
    url: '/admin/lang-texts/import',
    data,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}
