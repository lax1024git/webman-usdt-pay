import request from '@/axios'

export const getRoleListApi = (params: Record<string, any>) => {
  return request.get({ url: '/admin/roles', params })
}

export const createRoleApi = (data: Record<string, any>) => {
  return request.post({ url: '/admin/roles', data })
}

export const updateRoleApi = (id: number, data: Record<string, any>) => {
  return request.put({ url: `/admin/roles/${id}`, data })
}

export const deleteRoleApi = (id: number, data?: { google_code?: string }) => {
  return request.delete({
    url: `/admin/roles/${id}`,
    data,
    params: data?.google_code ? { google_code: data.google_code } : undefined
  })
}

export const getRolePermissionsApi = (id: number) => {
  return request.get<{ permission_ids: number[] }>({ url: `/admin/roles/${id}/permissions` })
}

export const assignRolePermissionsApi = (
  id: number,
  permission_ids: number[],
  extra?: { google_code?: string }
) => {
  return request.put({
    url: `/admin/roles/${id}/permissions`,
    data: { permission_ids, ...extra }
  })
}
