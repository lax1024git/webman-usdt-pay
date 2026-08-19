import { i18n } from '@/plugins/vueI18n'
import { useI18n as useVueI18n } from 'vue-i18n'

type I18nGlobalTranslation = {
  (key: string): string
  (key: string, locale: string): string
  (key: string, locale: string, list: unknown[]): string
  (key: string, locale: string, named: Record<string, unknown>): string
  (key: string, list: unknown[]): string
  (key: string, named: Record<string, unknown>): string
}

type I18nTranslator = (messageKey: string, ...parameters: unknown[]) => string

type LocaleMessageObject = Record<string, unknown>

const hasBusinessKeyChars = (key: string) => /[\u4e00-\u9fff]/.test(key)

const getKey = (namespace: string | undefined, key: string) => {
  if (!namespace) {
    return key
  }
  if (key.startsWith(namespace)) {
    return key
  }
  return `${namespace}.${key}`
}

const getMessageKey = (namespace: string | undefined, key: string) => {
  if (!namespace && key.startsWith('dict.')) {
    return key
  }
  if (!namespace && hasBusinessKeyChars(key)) {
    return `dict.${key}`
  }
  return getKey(namespace, key)
}

const getCurrentLocale = (): string => {
  const locale = i18n.global.locale as string | { value: string }
  if (typeof locale === 'string') {
    return locale
  }
  return locale.value
}

const getDictMessageFromLocale = (locale: string, dictKey: string): string | undefined => {
  const messages = i18n.global.getLocaleMessage(locale) as LocaleMessageObject
  const dict = messages.dict as LocaleMessageObject | undefined
  const value = dict?.[dictKey]

  return typeof value === 'string' ? value : undefined
}

const getDictMessage = (messageKey: string): string | undefined => {
  if (!messageKey.startsWith('dict.')) {
    return undefined
  }

  const dictKey = messageKey.slice(5)
  const locales = [
    getCurrentLocale(),
    'zh-CN',
    'en',
    ...(i18n.global.availableLocales as string[])
  ].filter((locale, index, arr) => locale && arr.indexOf(locale) === index)

  for (const locale of locales) {
    const value = getDictMessageFromLocale(locale, dictKey)
    if (value !== undefined) {
      return value
    }
  }

  return undefined
}

/** 对 dict 文案做 vue-i18n 风格的命名/列表插值 */
const interpolateMessage = (message: string, parameters: unknown[]): string => {
  if (parameters.length === 0 || !message.includes('{')) {
    return message
  }

  let params: unknown = parameters[0]
  // t(key, locale, named|list)
  if (typeof params === 'string' && parameters.length >= 2) {
    params = parameters[1]
  }

  if (Array.isArray(params)) {
    return message.replace(/\{(\d+)\}/g, (_, index: string) => {
      const value = params[Number(index)]
      return value === undefined || value === null ? `{${index}}` : String(value)
    })
  }

  if (params && typeof params === 'object') {
    const named = params as Record<string, unknown>
    return message.replace(/\{(\w+)\}/g, (_, name: string) => {
      const value = named[name]
      return value === undefined || value === null ? `{${name}}` : String(value)
    })
  }

  return message
}

export const useI18n = (
  namespace?: string
): {
  t: I18nGlobalTranslation
} => {
  const normalFn = {
    t: (key: string) => {
      return getKey(namespace, key)
    }
  }

  if (!i18n) {
    return normalFn
  }

  let translate: I18nTranslator
  let methods: object

  try {
    const compositionI18n = useVueI18n({ useScope: 'global' })
    translate = compositionI18n.t as I18nTranslator
    methods = compositionI18n
  } catch {
    translate = i18n.global.t as I18nTranslator
    methods = i18n.global
  }

  const tFn = ((key: string, ...parameters: unknown[]) => {
    if (!key) return ''

    const messageKey = getMessageKey(namespace, key)
    const dictMessage = getDictMessage(messageKey)

    if (dictMessage !== undefined) {
      return interpolateMessage(dictMessage, parameters)
    }

    const translated = translate(messageKey, ...parameters)
    const fallbackDictMessage = getDictMessage(translated)

    return fallbackDictMessage !== undefined
      ? interpolateMessage(fallbackDictMessage, parameters)
      : translated
  }) as I18nGlobalTranslation

  return {
    ...methods,
    t: tFn
  }
}

export const t = (key: string) => key
