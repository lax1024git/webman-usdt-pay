import type { App } from 'vue'
import { createI18n } from 'vue-i18n'
import { useLocaleStoreWithOut } from '@/store/modules/locale'
import type { I18n, I18nOptions } from 'vue-i18n'
import { setHtmlPageLang } from './helper'

export let i18n: ReturnType<typeof createI18n>

const createI18nOptions = async (): Promise<I18nOptions> => {
  const localeStore = useLocaleStoreWithOut()
  const locale = localeStore.getCurrentLocale
  const localeMap = localeStore.getLocaleMap

  // 预加载全部本地语言包（界面文案用本地 JS 键值对）
  const messages: Record<string, any> = {}
  for (const item of localeMap) {
    const mod = await import(`../../locales/${item.lang}.ts`)
    messages[item.lang] = mod.default ?? {}
  }

  setHtmlPageLang(locale.lang)
  localeStore.setCurrentLocale({
    lang: locale.lang
  })

  return {
    legacy: false,
    locale: locale.lang,
    fallbackLocale: 'zh-CN',
    messages,
    availableLocales: localeMap.map((v) => v.lang),
    sync: true,
    silentTranslationWarn: true,
    missingWarn: false,
    silentFallbackWarn: true
  }
}

export const setupI18n = async (app: App<Element>) => {
  const options = await createI18nOptions()
  i18n = createI18n(options) as I18n
  app.use(i18n)
}
