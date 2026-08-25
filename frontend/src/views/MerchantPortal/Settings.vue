<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElAlert, ElButton, ElDialog, ElForm, ElFormItem, ElInput, ElMessage } from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import {
  merchantChangePasswordApi,
  merchantGetSettingsApi,
  merchantResetSecretApi,
  merchantUpdateSettingsApi
} from '@/api/merchantPortal'

const loading = ref(false)
const saving = ref(false)
const changing = ref(false)
const resetting = ref(false)
const resetVisible = ref(false)
const secretVisible = ref(false)
const secretInfo = ref({ api_key: '', api_secret: '' })
const settings = ref({
  notify_url: '',
  ip_whitelist: '',
  api_key: ''
})
const passwordForm = ref({
  old_password: '',
  new_password: ''
})
const resetPassword = ref('')

const loadData = async () => {
  loading.value = true
  try {
    const res = await merchantGetSettingsApi()
    const data = res.data || {}
    settings.value = {
      notify_url: data.notify_url || '',
      ip_whitelist: Array.isArray(data.ip_whitelist) ? data.ip_whitelist.join(',') : '',
      api_key: data.api_key || ''
    }
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    await merchantUpdateSettingsApi({
      notify_url: settings.value.notify_url,
      ip_whitelist: settings.value.ip_whitelist
    })
    ElMessage.success('设置已保存')
    loadData()
  } finally {
    saving.value = false
  }
}

const changePassword = async () => {
  if (!passwordForm.value.old_password || !passwordForm.value.new_password) {
    ElMessage.warning('请填写原密码和新密码')
    return
  }
  changing.value = true
  try {
    await merchantChangePasswordApi(passwordForm.value)
    ElMessage.success('密码修改成功')
    passwordForm.value = { old_password: '', new_password: '' }
  } finally {
    changing.value = false
  }
}

const openResetSecret = () => {
  resetPassword.value = ''
  resetVisible.value = true
}

const submitResetSecret = async () => {
  if (!resetPassword.value) {
    ElMessage.warning('请输入登录密码')
    return
  }
  resetting.value = true
  try {
    const res = await merchantResetSecretApi({ login_password: resetPassword.value })
    secretInfo.value = {
      api_key: res.data?.api_key || settings.value.api_key,
      api_secret: res.data?.api_secret || ''
    }
    resetVisible.value = false
    secretVisible.value = true
    ElMessage.success('Secret 已重置')
  } finally {
    resetting.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="账户设置">
    <ElForm v-loading="loading" label-width="110px" style="max-width: 640px">
      <ElFormItem label="API Key">
        <ElInput v-model="settings.api_key" disabled />
      </ElFormItem>
      <ElFormItem label="API Secret">
        <ElButton type="warning" @click="openResetSecret">重置 Secret</ElButton>
        <span class="ml-2 text-gray-500 text-sm">重置后旧 Secret 立即失效</span>
      </ElFormItem>
      <ElFormItem label="回调地址">
        <ElInput v-model="settings.notify_url" placeholder="https://your-site.com/notify" />
      </ElFormItem>
      <ElFormItem label="IP 白名单">
        <ElInput v-model="settings.ip_whitelist" placeholder="逗号分隔，留空不限制" />
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="saving" @click="saveSettings">保存设置</ElButton>
      </ElFormItem>
    </ElForm>

    <h3 class="mt-6 mb-4 text-16px font-700">修改登录密码</h3>
    <ElForm label-width="110px" style="max-width: 640px">
      <ElFormItem label="原密码">
        <ElInput v-model="passwordForm.old_password" type="password" show-password />
      </ElFormItem>
      <ElFormItem label="新密码">
        <ElInput v-model="passwordForm.new_password" type="password" show-password />
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="changing" @click="changePassword">修改密码</ElButton>
      </ElFormItem>
    </ElForm>

    <ElDialog v-model="resetVisible" title="重置 API Secret" width="420px">
      <ElForm label-width="90px">
        <ElFormItem label="登录密码">
          <ElInput v-model="resetPassword" type="password" show-password placeholder="验证身份" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="resetVisible = false">取消</ElButton>
        <ElButton type="warning" :loading="resetting" @click="submitResetSecret">确认重置</ElButton>
      </template>
    </ElDialog>

    <ElDialog v-model="secretVisible" title="新 API Secret（仅显示一次）" width="520px">
      <ElAlert type="warning" :closable="false" class="mb-4" title="请立即保存 Secret，关闭后无法再次查看" />
      <p><strong>API Key:</strong> {{ secretInfo.api_key }}</p>
      <p class="mt-2"><strong>API Secret:</strong> {{ secretInfo.api_secret }}</p>
    </ElDialog>
  </ContentWrap>
</template>
