import type { RouteMeta } from 'vue-router'
import { Icon } from '@/components/Icon'
import { useI18n } from '@/hooks/web/useI18n'
import { useLocaleStore } from '@/store/modules/locale'
import { resolveMenuTitle } from '@/utils/menuTitle'

export const useRenderMenuTitle = () => {
  const localeStore = useLocaleStore()

  const renderMenuTitle = (meta: RouteMeta) => {
    const { t } = useI18n()
    // 依赖当前语言，切换语言时菜单标题随之更新
    void localeStore.getCurrentLocale.lang
    const title = resolveMenuTitle(meta, t, 'Please set title')

    return meta.icon ? (
      <>
        <Icon icon={meta.icon}></Icon>
        <span class="v-menu__title overflow-hidden overflow-ellipsis whitespace-nowrap">
          {title}
        </span>
      </>
    ) : (
      <span class="v-menu__title overflow-hidden overflow-ellipsis whitespace-nowrap">
        {title}
      </span>
    )
  }

  return {
    renderMenuTitle
  }
}
