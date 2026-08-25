import { createRouter, createWebHashHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import type { App } from 'vue'
import { Layout } from '@/utils/routerHelper'
import { NO_RESET_WHITE_LIST } from '@/constants'

export const constantRouterMap: AppRouteRecordRaw[] = [
  {
    path: '/',
    component: Layout,
    redirect: '/console/dashboard',
    name: 'Root',
    meta: {
      hidden: true
    }
  },
  {
    path: '/redirect',
    component: Layout,
    name: 'RedirectWrap',
    children: [
      {
        path: '/redirect/:path(.*)',
        name: 'Redirect',
        component: () => import('@/views/Redirect/Redirect.vue'),
        meta: {}
      }
    ],
    meta: {
      hidden: true,
      noTagsView: true
    }
  },
  {
    path: '/login',
    component: () => import('@/views/Login/Login.vue'),
    name: 'Login',
    meta: {
      hidden: true,
      title: 'router.login',
      noTagsView: true
    }
  },
  {
    path: '/merchant-portal/login',
    component: () => import('@/views/MerchantPortal/Login.vue'),
    name: 'MerchantPortalLogin',
    meta: {
      hidden: true,
      title: '商户登录',
      noTagsView: true
    }
  },
  {
    path: '/merchant-portal',
    component: () => import('@/views/MerchantPortal/Layout.vue'),
    name: 'MerchantPortal',
    redirect: '/merchant-portal/dashboard',
    meta: {
      hidden: true,
      title: '商户门户'
    },
    children: [
      {
        path: 'dashboard',
        name: 'MerchantPortalDashboard',
        component: () => import('@/views/MerchantPortal/Dashboard.vue'),
        meta: { title: '概览' }
      },
      {
        path: 'deposits',
        name: 'MerchantPortalDeposit',
        component: () => import('@/views/MerchantPortal/Deposit.vue'),
        meta: { title: '入金订单' }
      },
      {
        path: 'withdrawals',
        name: 'MerchantPortalWithdraw',
        component: () => import('@/views/MerchantPortal/Withdraw.vue'),
        meta: { title: '出金订单' }
      },
      {
        path: 'ledgers',
        name: 'MerchantPortalLedger',
        component: () => import('@/views/MerchantPortal/Ledger.vue'),
        meta: { title: '资金流水' }
      },
      {
        path: 'webhook-logs',
        name: 'MerchantPortalWebhook',
        component: () => import('@/views/MerchantPortal/WebhookLog.vue'),
        meta: { title: '回调日志' }
      },
      {
        path: 'settings',
        name: 'MerchantPortalSettings',
        component: () => import('@/views/MerchantPortal/Settings.vue'),
        meta: { title: '账户设置' }
      }
    ]
  },
  {
    path: '/personal',
    component: Layout,
    redirect: '/personal/personal-center',
    name: 'Personal',
    meta: {
      title: 'router.personal',
      hidden: true,
      canTo: true
    },
    children: [
      {
        path: 'personal-center',
        component: () => import('@/views/Personal/PersonalCenter/PersonalCenter.vue'),
        name: 'PersonalCenter',
        meta: {
          title: 'router.personalCenter',
          hidden: true,
          canTo: true
        }
      }
    ]
  },
  {
    path: '/404',
    component: () => import('@/views/Error/404.vue'),
    name: 'NoFind',
    meta: {
      hidden: true,
      title: '404',
      noTagsView: true
    }
  }
]

/** 菜单由后端动态下发，静态异步路由已清空 */
export const asyncRouterMap: AppRouteRecordRaw[] = []

const router = createRouter({
  history: createWebHashHistory(),
  strict: true,
  routes: constantRouterMap as RouteRecordRaw[],
  scrollBehavior: () => ({ left: 0, top: 0 })
})

export const resetRouter = (): void => {
  router.getRoutes().forEach((route) => {
    const { name } = route
    if (name && !NO_RESET_WHITE_LIST.includes(name as string)) {
      router.hasRoute(name) && router.removeRoute(name)
    }
  })
}

export const setupRouter = (app: App<Element>) => {
  app.use(router)
}

export default router
