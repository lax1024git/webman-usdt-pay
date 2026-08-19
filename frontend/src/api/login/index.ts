import request from '@/axios'
import type { UserLoginType, LoginResult, UserInfo } from './types'
import { transformMenus } from '@/utils/menuTransform'

export const loginApi = (data: UserLoginType): Promise<IResponse<LoginResult>> => {
  return request.post({ url: '/admin/login', data })
}

export const getCaptchaApi = (): Promise<
  IResponse<{ enabled: boolean; key?: string; question?: string }>
> => {
  return request.get({ url: '/admin/captcha' })
}

export const getLoginStatusApi = (
  username: string
): Promise<IResponse<{ captcha_required: boolean; google_auth_bound?: boolean }>> => {
  return request.get({ url: '/admin/login-status', params: { username } })
}

export const refreshTokenApi = (
  refresh_token: string
): Promise<IResponse<{ token: string; expires_in: number; refresh_token?: string }>> => {
  return request.post({ url: '/admin/refresh', data: { refresh_token } })
}

export const loginOutApi = (refresh_token?: string): Promise<IResponse> => {
  return request.post({ url: '/admin/logout', data: { refresh_token } })
}

export const getMeApi = (): Promise<IResponse<UserInfo>> => {
  return request.get({ url: '/admin/me' })
}

export const getServerTimeApi = (): Promise<
  IResponse<{ timestamp: number; datetime: string; timezone: string }>
> => {
  return request.get({ url: '/admin/server-time' })
}

export const getBrandingApi = (): Promise<
  IResponse<{ name?: string; logo?: string; icon?: string }>
> => {
  return request.get({ url: '/admin/branding' })
}

export const getMenusApi = (): Promise<IResponse<AppCustomRouteRecordRaw[]>> => {
  return request.get({ url: '/admin/menus' }).then((res: IResponse<any>) => {
    return {
      ...res,
      data: transformMenus(res.data || [])
    }
  })
}

export const getGoogleAuthSetupApi = (): Promise<
  IResponse<{ secret: string; otpauth_url: string }>
> => {
  return request.get({ url: '/admin/me/google-auth/setup' })
}

export const bindGoogleAuthApi = (code: string): Promise<IResponse> => {
  return request.post({ url: '/admin/me/google-auth/bind', data: { code } })
}

/** 敏感操作前置校验谷歌验证码（提交业务接口时仍会再验一次） */
export const verifyGoogleAuthApi = (google_code: string): Promise<IResponse> => {
  return request.post({ url: '/admin/me/google-auth/verify', data: { google_code } })
}
