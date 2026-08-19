import { getPresignUrlApi } from '@/api/upload'
import type { PresignParams } from '@/api/upload/types'

export interface S3UploadOptions {
  type?: PresignParams['type']
  onProgress?: (percent: number) => void
}

export async function uploadToS3(file: File, options: S3UploadOptions = {}) {
  const type = options.type ?? 'image'
  const mimeType = file.type || 'application/octet-stream'

  const presignRes = await getPresignUrlApi({
    filename: file.name,
    mime_type: mimeType,
    type
  })

  const { upload_url, url } = presignRes.data

  await new Promise<void>((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('PUT', upload_url, true)
    xhr.setRequestHeader('Content-Type', mimeType)

    if (options.onProgress) {
      xhr.upload.onprogress = (event) => {
        if (!event.lengthComputable) {
          return
        }
        options.onProgress?.(Math.round((event.loaded / event.total) * 100))
      }
    }

    xhr.onload = () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve()
        return
      }
      reject(new Error(`S3 上传失败: ${xhr.status}`))
    }

    xhr.onerror = () => reject(new Error('S3 上传失败'))
    xhr.send(file)
  })

  return {
    url,
    filename: file.name,
    size: file.size
  }
}
