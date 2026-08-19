import type { RouteMeta } from 'vue-router'
import { useLocaleStoreWithOut } from '@/store/modules/locale'

type TitleI18n = Record<string, string>

/**
 * 解析菜单/路由标题：优先用接口下发的 titleI18n（按当前语言），否则回退 t(title) / 原文。
 * 改后台菜单中文名或英文映射后无需重新打包前端。
 */
export function resolveMenuTitle(
  meta?: RouteMeta | null,
  t?: (key: string) => string,
  fallback = ''
): string {
  if (!meta) {
    return fallback
  }

  const titleI18n = meta.titleI18n as TitleI18n | undefined
  const localeStore = useLocaleStoreWithOut()
  const locale = localeStore.getCurrentLocale?.lang || 'zh-CN'

  if (titleI18n && typeof titleI18n === 'object') {
    const localized = titleI18n[locale] || titleI18n['zh-CN'] || titleI18n.en
    if (localized) {
      return localized
    }
  }

  const title = (meta.title as string) || fallback
  if (!title) {
    return fallback
  }

  return t ? t(title) : title
}
