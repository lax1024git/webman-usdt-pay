import { watch, ref } from 'vue'
import type { RouteMeta } from 'vue-router'
import { isString } from '@/utils/is'
import { useAppStoreWithOut } from '@/store/modules/app'
import { useI18n } from '@/hooks/web/useI18n'
import { resolveMenuTitle } from '@/utils/menuTitle'

export const useTitle = (newTitle?: string | RouteMeta) => {
  const { t } = useI18n()
  const appStore = useAppStoreWithOut()

  const resolved =
    newTitle && typeof newTitle === 'object'
      ? resolveMenuTitle(newTitle, t)
      : newTitle
        ? t(newTitle as string)
        : ''

  const title = ref(resolved ? `${appStore.getTitle} - ${resolved}` : appStore.getTitle)

  watch(
    title,
    (n, o) => {
      if (isString(n) && n !== o && document) {
        document.title = n
      }
    },
    { immediate: true }
  )

  return title
}
