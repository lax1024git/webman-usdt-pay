export interface UserLoginType {
  username: string
  password: string
  captcha_key?: string
  captcha_answer?: string
  google_code?: string
}

export interface LoginResult {
  token: string
  refresh_token: string
  expires_in: number
  user: UserInfo
}

export interface UserInfo {
  id: number
  username: string
  nickname: string
  avatar: string
  roles: Array<string | { id: number; name: string; slug: string }>
  permissions?: string[]
  google_auth_bound?: boolean
  /** 系统配置：后台敏感操作是否要求谷歌验证 */
  admin_google_auth_required?: boolean
}

export interface UserType {
  username: string
  password?: string
  captcha_answer?: string
  google_code?: string
  role: string
  roles?: string[]
  roleId: string
  nickname?: string
  avatar?: string
  permissions?: string[]
}
