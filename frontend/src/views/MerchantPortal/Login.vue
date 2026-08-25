<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElButton, ElForm, ElFormItem, ElInput, ElMessage } from 'element-plus'
import { merchantLoginApi } from '@/api/merchantPortal'
import { setMerchantInfo, setMerchantRefreshToken, setMerchantToken } from '@/utils/merchantAuth'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const form = reactive({
  email: '',
  password: ''
})

const handleLogin = async () => {
  if (!form.email || !form.password) {
    ElMessage.warning('请输入邮箱和密码')
    return
  }
  loading.value = true
  try {
    const res = await merchantLoginApi({ email: form.email, password: form.password })
    setMerchantToken(res.data.token)
    setMerchantRefreshToken(res.data.refresh_token)
    setMerchantInfo(res.data.merchant || {})
    ElMessage.success('登录成功')
    const redirect = (route.query.redirect as string) || '/merchant-portal/dashboard'
    router.replace(redirect)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="portal-login">
    <div class="portal-card">
      <h2>商户门户登录</h2>
      <p class="hint">使用管理端为商户配置的登录邮箱和密码</p>
      <ElForm @submit.prevent="handleLogin">
        <ElFormItem>
          <ElInput v-model="form.email" placeholder="登录邮箱" size="large" />
        </ElFormItem>
        <ElFormItem>
          <ElInput
            v-model="form.password"
            type="password"
            placeholder="密码"
            size="large"
            show-password
            @keyup.enter="handleLogin"
          />
        </ElFormItem>
        <ElButton type="primary" size="large" class="w-full" :loading="loading" @click="handleLogin">
          登录
        </ElButton>
      </ElForm>
    </div>
  </div>
</template>

<style scoped lang="less">
.portal-login {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100%;
  background: #050816;
}

.portal-card {
  width: 420px;
  padding: 32px;
  border-radius: 12px;
  background: #111827;
  color: #e5e7eb;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
}

h2 {
  margin: 0 0 8px;
  font-size: 22px;
}

.hint {
  margin: 0 0 24px;
  color: #9ca3af;
  font-size: 13px;
}

.w-full {
  width: 100%;
}
</style>
