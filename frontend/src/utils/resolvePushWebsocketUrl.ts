/**
 * 解析后台 webman/push 的浏览器 WebSocket 基址。
 * - 接口可能返回 /wss 或 ws(s)://host/wss
 * - HTTPS 页面强制使用 wss，避免 Mixed Content
 */
export function resolvePushWebsocketUrl(raw?: string): string {
  let url = String(raw || '/wss')
    .trim()
    .replace(/\/+$/, '') || '/wss'

  const pageIsHttps =
    typeof window !== 'undefined' && window.location?.protocol === 'https:'

  // 已是绝对地址
  if (/^wss?:\/\//i.test(url)) {
    if (pageIsHttps && /^ws:\/\//i.test(url)) {
      url = `wss://${url.slice('ws://'.length)}`
    }
    return url
  }

  const wsPath = url.startsWith('/') ? url : `/${url}`
  const apiBase = String(import.meta.env.VITE_API_BASE_PATH || '').trim()

  // 生产同域：HTTPS 下也拼成绝对 wss，避免部分浏览器对相对 WS 路径处理不一致
  if (!apiBase || apiBase === '/' || apiBase === './') {
    if (typeof window !== 'undefined' && window.location?.host) {
      const scheme = pageIsHttps ? 'wss' : 'ws'
      return `${scheme}://${window.location.host}${wsPath}`
    }
    return wsPath
  }

  try {
    const u = new URL(apiBase)
    const scheme = u.protocol === 'https:' || pageIsHttps ? 'wss' : 'ws'
    return `${scheme}://${u.host}${wsPath}`
  } catch {
    return wsPath
  }
}
