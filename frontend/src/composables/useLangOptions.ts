import { ref, computed } from 'vue'
import { getLangOptionsApi } from '@/api/lang'

export interface LangOption {
  id: number
  title: string
  lang: string
  switch_enabled?: number
  status?: number
}

/** 接口返回的全部启用语言（含不可切换） */
const allLangOptions = ref<LangOption[]>([])
let loaded = false

/** 前端允许用户切换的语言（status 启用 + switch_enabled） */
export function isSwitchableLang(item: LangOption): boolean {
  return Number(item.status ?? 1) === 1 && Number(item.switch_enabled) === 1
}

export function useLangOptions() {
  const loadLangOptions = async (force = false) => {
    if (loaded && !force) {
      return allLangOptions.value
    }
    const res = await getLangOptionsApi(true)
    allLangOptions.value = res.data ?? []
    loaded = true
    return allLangOptions.value
  }

  /** 后台选择语言时默认只用可切换语言 */
  const switchableLangOptions = computed(() => allLangOptions.value.filter(isSwitchableLang))

  return {
    /** @deprecated 请优先用 switchableLangOptions；现等同于可切换语言列表 */
    langOptions: switchableLangOptions,
    switchableLangOptions,
    allLangOptions,
    loadLangOptions,
    isSwitchableLang
  }
}
