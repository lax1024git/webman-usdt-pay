/**
 * 后台前端 locale ↔ API / sy_lang_items 语言码
 */
const FRONT_TO_API: Record<string, string> = {
  'zh-CN': 'zh-cn',
  zh: 'zh-cn',
  cn: 'zh-cn',
  en: 'en',
  'en-US': 'en'
}

export function frontLocaleToApi(front?: string): string {
  const key = String(front || 'zh-CN')
  return FRONT_TO_API[key] || 'zh-cn'
}
