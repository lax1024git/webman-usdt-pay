const TOKEN_KEY = 'merchant_token'
const REFRESH_KEY = 'merchant_refresh_token'
const MERCHANT_KEY = 'merchant_info'

export function getMerchantToken(): string {
  return localStorage.getItem(TOKEN_KEY) || ''
}

export function setMerchantToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

export function getMerchantRefreshToken(): string {
  return localStorage.getItem(REFRESH_KEY) || ''
}

export function setMerchantRefreshToken(token: string): void {
  localStorage.setItem(REFRESH_KEY, token)
}

export function getMerchantInfo(): Record<string, any> | null {
  const raw = localStorage.getItem(MERCHANT_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw)
  } catch {
    return null
  }
}

export function setMerchantInfo(info: Record<string, any>): void {
  localStorage.setItem(MERCHANT_KEY, JSON.stringify(info))
}

export function clearMerchantAuth(): void {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(REFRESH_KEY)
  localStorage.removeItem(MERCHANT_KEY)
}

export function isMerchantLoggedIn(): boolean {
  return !!getMerchantToken()
}
