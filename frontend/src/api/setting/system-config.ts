import request from '@/axios'

export interface ConfigFieldOption {
  value: string
  label: string
}

export interface ConfigFieldSchema {
  key: string
  label: string
  type: string
  options?: ConfigFieldOption[]
  help?: string
  default?: unknown
  show_when?: Record<string, string>
  placeholder?: string
  readonly?: boolean
  min?: number
  max?: number
  step?: number
  rows?: number
  accept?: string
  section?: number
}

export interface ConfigTabSchema {
  key: string
  label: string
  fields: ConfigFieldSchema[]
  sections?: { label: string }[]
}

export interface SystemConfigBundle {
  tabs: ConfigTabSchema[]
  options: Record<string, ConfigFieldOption[]>
  values: Record<string, unknown>
  client_ip: string
}

export const getSystemConfigBundleApi = () => {
  return request.get<SystemConfigBundle>({ url: '/admin/system-config' })
}

export const saveSystemConfigApi = (data: Record<string, unknown>) => {
  return request.put({ url: '/admin/system-config', data })
}
