import request from '@/axios'
import type { PresignParams, PresignResult, UploadResult } from './types'

export const getPresignUrlApi = (data: PresignParams) => {
  return request.post<PresignResult>({ url: '/admin/upload/presign', data })
}

export const uploadFileApi = (data: FormData) => {
  return request.post<UploadResult>({
    url: '/api/upload',
    data,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}
