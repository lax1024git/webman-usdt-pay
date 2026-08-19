export interface S3UploadProps {
  modelValue?: string
  type?: 'image' | 'document' | 'video' | 'file'
  accept?: string
  disabled?: boolean
  showFileList?: boolean
  limit?: number
  compact?: boolean
}

export interface S3UploadEmits {
  (e: 'update:modelValue', value: string): void
  (e: 'success', value: { url: string; filename: string; size: number }): void
  (e: 'error', error: Error): void
}
