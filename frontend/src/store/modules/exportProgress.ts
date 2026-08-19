import { defineStore } from 'pinia'
import { store } from '../index'

export type ExportProgressItem = {
  jobId: number
  filename: string
  exportType: string
  visible: boolean
}

interface ExportProgressState {
  items: ExportProgressItem[]
}

export const useExportProgressStore = defineStore('exportProgress', {
  state: (): ExportProgressState => ({
    items: []
  }),
  actions: {
    open(payload: { jobId: number; filename?: string; exportType?: string }) {
      const jobId = Number(payload.jobId || 0)
      if (jobId <= 0) return

      const exists = this.items.find((item) => item.jobId === jobId)
      if (exists) {
        exists.visible = true
        if (payload.filename) exists.filename = payload.filename
        return
      }

      this.items.push({
        jobId,
        filename: payload.filename || `export_${jobId}.csv`,
        exportType: payload.exportType || '',
        visible: true
      })
    },
    close(jobId: number) {
      const item = this.items.find((row) => row.jobId === jobId)
      if (item) {
        item.visible = false
      }
    },
    remove(jobId: number) {
      this.items = this.items.filter((row) => row.jobId !== jobId)
    }
  }
})

export const useExportProgressStoreWithOut = () => {
  return useExportProgressStore(store)
}
