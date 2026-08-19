interface BackendMenu {
  id: number
  name: string
  slug?: string
  type?: string
  path: string
  icon?: string
  component?: string
  sort?: number
  /** 接口下发的多语言标题，key 为 locale（zh-CN / en） */
  i18n?: Record<string, string>
  children?: BackendMenu[]
}

const iconMap: Record<string, string> = {
  dashboard: 'vi-ant-design:dashboard-filled',
  document: 'vi-clarity:document-solid',
  edit: 'vi-ant-design:edit-outlined',
  folder: 'vi-ant-design:folder-outlined',
  setting: 'vi-ant-design:setting-outlined',
  user: 'vi-ant-design:user-outlined',
  peoples: 'vi-ant-design:team-outlined',
  lock: 'vi-ant-design:lock-outlined',
  tools: 'vi-ant-design:tool-outlined',
  documentation: 'vi-clarity:document-solid',
  bell: 'vi-ant-design:bell-outlined'
}

const componentMap: Record<string, string> = {
  'views/dashboard/index': 'views/Dashboard/Index',
  'views/Dashboard/Index': 'views/Dashboard/Index',
  'views/system/admin/index': 'views/System/Admin/index',
  'views/system/role/index': 'views/System/Role/index',
  'views/system/permission/index': 'views/System/Permission/index',
  'views/system/setting/index': 'views/System/Setting/index',
  'views/system/log/index': 'views/System/Log/index',
  'views/system/ipWhitelist/index': 'views/System/IpWhitelist/index',
  'views/system/dict/index': 'views/System/Dict/index',
  'views/system/lang/index': 'views/System/Lang/index',
  'views/system/lang-text/index': 'views/System/LangText/index',
  'views/system/notification/index': 'views/System/Notification/index',
  'views/system/export/index': 'views/System/Export/index'
}

function toRouteName(slug: string, path: string, id: number): string {
  const source = slug || path
  const name = source
    .replace(/^\//, '')
    .split(/[-_/]/)
    .filter(Boolean)
    .map((s) => s.charAt(0).toUpperCase() + s.slice(1))
    .join('')
  return name || `Menu${id}`
}

function resolveComponent(component: string): string {
  return componentMap[component] || component
}

function buildMeta(menu: BackendMenu, alwaysShow = false) {
  const titleI18n =
    menu.i18n && typeof menu.i18n === 'object'
      ? menu.i18n
      : {
          'zh-CN': menu.name,
          en: menu.name
        }

  return {
    title: menu.name,
    titleI18n,
    icon: iconMap[menu.icon || ''] || 'vi-ant-design:appstore-outlined',
    alwaysShow
  }
}

/** 从完整路径提取相对父级的子路径 */
function toChildPath(parentPath: string, fullPath: string): string {
  if (fullPath.startsWith(parentPath + '/')) {
    return fullPath.slice(parentPath.length + 1)
  }
  return fullPath.replace(/^\//, '')
}

function transformLeafChild(menu: BackendMenu, parentPath: string): AppCustomRouteRecordRaw {
  const childPath = toChildPath(parentPath, menu.path)
  const routeName = toRouteName(menu.slug || '', menu.path, menu.id)

  if (menu.component === 'views/dashboard/index') {
    return {
      path: 'dashboard',
      name: 'Dashboard',
      component: resolveComponent(menu.component),
      redirect: '',
      meta: { ...buildMeta(menu), affix: true }
    }
  }

  return {
    path: childPath,
    name: routeName,
    component: resolveComponent(menu.component || ''),
    redirect: '',
    meta: buildMeta(menu)
  }
}

function transformNode(menu: BackendMenu): AppCustomRouteRecordRaw {
  const hasChildren = !!(menu.children && menu.children.length > 0)

  if (hasChildren) {
    const children = menu.children!.map((child) => transformLeafChild(child, menu.path))
    const firstChildFullPath = menu.children![0]?.path || ''
    const redirect = firstChildFullPath.startsWith('/')
      ? firstChildFullPath === '/console/dashboard'
        ? '/console/dashboard'
        : firstChildFullPath
      : `${menu.path}/${children[0]?.path || ''}`.replace(/\/\//g, '/')

    return {
      path: menu.path,
      name: toRouteName(menu.slug || '', menu.path, menu.id),
      component: '#',
      redirect,
      meta: buildMeta(menu, true),
      children
    }
  }

  return {
    path: menu.path,
    name: toRouteName(menu.slug || '', menu.path, menu.id),
    component: resolveComponent(menu.component || ''),
    redirect: '',
    meta: buildMeta(menu)
  }
}

/** 将后端菜单树转为 vue-element-plus-admin 路由树（保留父子层级） */
export function transformMenus(menus: BackendMenu[]): AppCustomRouteRecordRaw[] {
  return menus.map((menu) => transformNode(menu))
}

/** 登录后默认跳转路径 */
export function getDefaultRoutePath(routes: AppCustomRouteRecordRaw[]): string {
  for (const route of routes) {
    if (route.redirect && typeof route.redirect === 'string' && route.redirect.startsWith('/')) {
      return route.redirect
    }
  }
  return '/console/dashboard'
}
