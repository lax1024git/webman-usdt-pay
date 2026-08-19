import type { App, Directive, DirectiveBinding } from 'vue'
import { useUserStoreWithOut } from '@/store/modules/user'
import { normalizeRoleSlugs } from '@/utils/role'

const isSuperAdmin = (): boolean => {
  const userStore = useUserStoreWithOut()
  const userInfo = userStore.getUserInfo as { roles?: unknown; role?: unknown } | undefined
  const roles = normalizeRoleSlugs(
    (userInfo?.roles as Array<string | { slug?: string }>) ||
      (userInfo?.role ? [userInfo.role as string | { slug?: string }] : [])
  )
  return roles.includes('super_admin')
}

const checkPermission = (value: string | string[]): boolean => {
  if (value === undefined || value === null || value === '') {
    return false
  }
  if (isSuperAdmin()) {
    return true
  }
  const slugs = (Array.isArray(value) ? value : [value])
    .map((s) => String(s || '').trim())
    .filter(Boolean)
  if (slugs.length === 0) {
    return false
  }
  const userStore = useUserStoreWithOut()
  const permissions = userStore.getPermissions || []
  return slugs.some((slug) => permissions.includes(slug))
}

const applyPermission = (el: HTMLElement, binding: DirectiveBinding<string | string[]>) => {
  if (checkPermission(binding.value)) {
    return
  }
  // display:none 会被 Element Plus 按钮的 display 样式盖掉，直接移除节点
  el.parentNode?.removeChild(el)
}

const permissionDirective: Directive = {
  mounted(el: Element, binding: DirectiveBinding<string | string[]>) {
    applyPermission(el as HTMLElement, binding)
  }
}

export const setupPermissionDirective = (app: App<Element>) => {
  app.directive('permission', permissionDirective)
}
