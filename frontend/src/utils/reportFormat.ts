export const formatReportMoney = (val?: string | number | null) => {
  const num = Number(val ?? 0)
  if (Number.isNaN(num)) return '0.00'
  return num.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export const formatReportCount = (val?: string | number | null) => {
  const num = Number(val ?? 0)
  if (Number.isNaN(num)) return '0'
  return num.toLocaleString('zh-CN')
}

export const defaultReportDateRange = (days = 30) => {
  const end = new Date()
  const start = new Date(Date.now() - days * 86400000)
  const toDate = (d: Date) => d.toISOString().slice(0, 10)
  return { start_date: toDate(start), end_date: toDate(end) }
}
