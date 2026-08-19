import { uploadFileApi } from '@/api/upload'
import type { PresignParams } from '@/api/upload/types'

export interface ApiUploadOptions {
  type?: PresignParams['type']
}

export async function uploadViaApi(file: File, options: ApiUploadOptions = {}) {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('type', options.type ?? 'image')

  const res = await uploadFileApi(formData)

  return res.data
}
