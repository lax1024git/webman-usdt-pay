<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMenu, ElMenuItem, ElButton, ElMessage } from 'element-plus'
import {
  merchantLogoutApi
} from '@/api/merchantPortal'
import {
  clearMerchantAuth,
  getMerchantInfo,
  getMerchantRefreshToken
} from '@/utils/merchantAuth'

const route = useRoute()
const router = useRouter()
const merchant = computed(() => getMerchantInfo())
const active = computed(() => route.path)

const menus = [
  { path: '/merchant-portal/dashboard', label: '概览' },
  { path: '/merchant-portal/deposits', label: '入金订单' },
  { path: '/merchant-portal/withdrawals', label: '出金订单' },
  { path: '/merchant-portal/ledgers', label: '资金流水' },
  { path: '/merchant-portal/webhook-logs', label: '回调日志' },
  { path: '/merchant-portal/settings', label: '账户设置' }
]

const handleLogout = async () => {
  const refreshToken = getMerchantRefreshToken()
  try {
    if (refreshToken) {
      await merchantLogoutApi({ refresh_token: refreshToken })
    }
  } catch {
    // ignore
  }
  clearMerchantAuth()
  ElMessage.success('已退出')
  router.replace('/merchant-portal/login')
}
</script>

<template>
  <div class="merchant-layout">
    <header class="merchant-header">
      <div class="merchant-brand">商户门户</div>
      <div class="merchant-user">
        <span>{{ merchant?.name || '商户' }}</span>
        <span v-if="merchant?.merchant_no" class="merchant-no">{{ merchant.merchant_no }}</span>
        <ElButton link type="primary" @click="handleLogout">退出</ElButton>
      </div>
    </header>
    <div class="merchant-body">
      <aside class="merchant-aside">
        <ElMenu :default-active="active" router>
          <ElMenuItem v-for="item in menus" :key="item.path" :index="item.path">
            {{ item.label }}
          </ElMenuItem>
        </ElMenu>
      </aside>
      <main class="merchant-main">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped lang="less">
.merchant-layout {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  background: var(--el-bg-color-page, #f5f7fa);
}

.merchant-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
  padding: 0 20px;
  background: #111827;
  color: #fff;
  flex-shrink: 0;
}

.merchant-brand {
  font-size: 16px;
  font-weight: 700;
}

.merchant-user {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}

.merchant-no {
  opacity: 0.7;
}

.merchant-body {
  display: flex;
  min-height: 0;
  flex: 1;
}

.merchant-aside {
  width: 200px;
  background: #fff;
  border-right: 1px solid var(--el-border-color-light);
  overflow: auto;
}

.merchant-main {
  flex: 1;
  overflow: auto;
  padding: 16px;
}
</style>
