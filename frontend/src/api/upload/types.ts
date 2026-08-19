export interface PresignParams {
  filename: string
  mime_type: string
  type: 'image' | 'document' | 'video' | 'file'
}

export interface PresignResult {
  upload_url: string
  key: string
  url: string
  expires_in: number
}

export interface UploadResult {
  url: string
  key: string
  filename: string
  size: number
}
