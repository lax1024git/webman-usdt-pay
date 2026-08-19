import { createExportJobApi } from '@/api/export'
import { PATH_URL } from '@/axios/service'
import { useExportProgressStoreWithOut } from '@/store/modules/exportProgress'

export type ExportJobProgress = {
  id: number
  status: string
  percent: number
  processed: number
  total: number
  message?: string
  file_url?: string
  export_type?: string
  export_type_label?: string
}

/**
 * 创建导出任务并弹出可关闭进度窗。
 * 任务在后台继续；完成后可在弹窗下载，或到「导出任务」列表下载。
 */
export async function runCsvExportJob(
  exportType: string,
  filters: Record<string, any>,
  filename: string,
  options?: {
    onProgress?: (job: ExportJobProgress) => void
  }
): Promise<ExportJobProgress> {
  const createRes = (await createExportJobApi({
    export_type: exportType,
    filters
  })) as any
  const job = (createRes?.data ?? createRes) as ExportJobProgress
  const jobId = Number(job?.id || 0)
  if (jobId <= 0) {
    throw new Error('创建导出任务失败')
  }

  options?.onProgress?.(job)

  useExportProgressStoreWithOut().open({
    jobId,
    filename,
    exportType: String(job?.export_type_label || exportType)
  })

  return job
}

/** 相对路径补全为 API 域名，避免打到前端 Vite 源站 */
export function resolveExportFileUrl(url: string): string {
  const raw = String(url || '').trim()
  if (!raw) return ''
  if (/^(https?:|blob:|data:)/i.test(raw)) {
    return raw
  }
  const base = String(PATH_URL || '').replace(/\/$/, '')
  if (raw.startsWith('//')) {
    return `${window.location.protocol}${raw}`
  }
  if (raw.startsWith('/')) {
    return base ? `${base}${raw}` : raw
  }
  return base ? `${base}/${raw}` : `/${raw}`
}

/** 直接跳转下载，避免跨域 fetch（S3 CORS * + credentials 会失败） */
export async function downloadExportFile(url: string, _filename?: string): Promise<void> {
  const resolved = resolveExportFileUrl(url)
  if (!resolved) {
    throw new Error('暂无下载地址')
  }

  const a = document.createElement('a')
  a.href = resolved
  a.target = '_blank'
  a.rel = 'noopener noreferrer'
  a.style.display = 'none'
  document.body.appendChild(a)
  a.click()
  a.remove()
}
