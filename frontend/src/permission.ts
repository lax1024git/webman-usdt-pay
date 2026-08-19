import router from './router'
import { useAppStoreWithOut } from '@/store/modules/app'
import type { RouteRecordRaw } from 'vue-router'
import { useTitle } from '@/hooks/web/useTitle'
import { useNProgress } from '@/hooks/web/useNProgress'
import { usePermissionStoreWithOut } from '@/store/modules/permission'
import { usePageLoading } from '@/hooks/web/usePageLoading'
import { NO_REDIRECT_WHITE_LIST } from '@/constants'
import { useUserStoreWithOut } from '@/store/modules/user'
import { getMenusApi, getMeApi } from '@/api/login'
import { normalizeRoleSlugs } from '@/utils/role'

const { start, done } = useNProgress()

const { loadStart, loadDone } = usePageLoading()

router.beforeEach(async (to, from, next) => {
  start()
  loadStart()
  const permissionStore = usePermissionStoreWithOut()
  const appStore = useAppStoreWithOut()
  const userStore = useUserStoreWithOut()
  if (userStore.getUserInfo) {
    if (to.path === '/login') {
      next({ path: '/console/dashboard' })
    } else {
      if (permissionStore.getIsAddRouters) {
        next()
        return
      }

      // 开发者可根据实际情况进行修改
      let roleRouters = (userStore.getRoleRouters || []) as AppCustomRouteRecordRaw[] | string[]

      // 是否使用动态路由
      if (appStore.getDynamicRouter) {
        if (appStore.serverDynamicRouter) {
          try {
            const meRes = await getMeApi()
            if (meRes?.data?.permissions) {
              userStore.setPermissions(meRes.data.permissions)
            }
            if (meRes?.data?.roles?.length) {
              const roles = normalizeRoleSlugs(meRes.data.roles)
              userStore.setUserInfo({
                ...userStore.getUserInfo,
                username: meRes.data.username,
                nickname: meRes.data.nickname,
                avatar: meRes.data.avatar,
                roles,
                role: roles.includes('super_admin') ? 'super_admin' : roles[0]
              })
            }
            const res = await getMenusApi()
            if (res?.data) {
              roleRouters = res.data
              userStore.setRoleRouters(res.data)
            }
          } catch {
            // 接口失败时回退到本地缓存的菜单
          }
          await permissionStore.generateRoutes('server', roleRouters as AppCustomRouteRecordRaw[])
        } else {
          await permissionStore.generateRoutes('frontEnd', roleRouters as string[])
        }
      } else {
        await permissionStore.generateRoutes('static')
      }

      permissionStore.getAddRouters.forEach((route) => {
        router.addRoute(route as unknown as RouteRecordRaw) // 动态添加可访问路由表
      })
      const redirectPath = from.query.redirect || to.path
      const redirect = decodeURIComponent(redirectPath as string)
      const nextData = to.path === redirect ? { ...to, replace: true } : { path: redirect }
      permissionStore.setIsAddRouters(true)
      next(nextData)
    }
  } else {
    if (NO_REDIRECT_WHITE_LIST.indexOf(to.path) !== -1) {
      next()
    } else {
      next(`/login?redirect=${to.path}`) // 否则全部重定向到登录页
    }
  }
})

router.afterEach((to) => {
  useTitle(to?.meta)
  done() // 结束Progress
  loadDone()
})
