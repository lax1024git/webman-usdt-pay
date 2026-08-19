import request from '@/axios'

export const getNotificationListApi = (params: any) =>
  request.get({ url: '/admin/notifications', params })
export const createNotificationApi = (data: any) =>
  request.post({ url: '/admin/notifications', data })
export const markNotificationReadApi = (id: number) =>
  request.put({ url: `/admin/notifications/${id}/read` })
export const markAllNotificationReadApi = () =>
  request.put({ url: '/admin/notifications/read-all' })
export const getNotificationUnreadApi = () =>
  request.get({ url: '/admin/notifications/unread-count' })
export const getPushConfigApi = () => request.get({ url: '/admin/push/config' })
