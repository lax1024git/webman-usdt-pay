<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElAlert, ElButton, ElInput, ElMessage, ElTag, ElForm, ElFormItem } from 'element-plus'
import QRCode from 'qrcode'
import { useI18n } from '@/hooks/web/useI18n'
import { bindGoogleAuthApi, getGoogleAuthSetupApi, getMeApi } from '@/api/login'

const { t } = useI18n()

const loading = ref(false)
const bound = ref(false)
const setupLoading = ref(false)
const bindLoading = ref(false)
const setupInfo = ref<{ secret: string; qrDataUrl: string } | null>(null)
const bindCode = ref('')

const renderQrCode = async (otpauthUrl: string) => {
  return QRCode.toDataURL(otpauthUrl, {
    width: 200,
    margin: 1,
    errorCorrectionLevel: 'M'
  })
}

const loadStatus = async () => {
  loading.value = true
  try {
    const res = await getMeApi()
    bound.value = !!res.data?.google_auth_bound
  } finally {
    loading.value = false
  }
}

const loadSetup = async () => {
  setupLoading.value = true
  try {
    const res = await getGoogleAuthSetupApi()
    const otpauthUrl = res.data?.otpauth_url || ''
    if (!otpauthUrl) {
      throw new Error(t('绑定信息不完整'))
    }
    setupInfo.value = {
      secret: res.data?.secret || '',
      qrDataUrl: await renderQrCode(otpauthUrl)
    }
  } catch (e: any) {
    setupInfo.value = null
    ElMessage.error(e?.message || t('获取绑定信息失败'))
  } finally {
    setupLoading.value = false
  }
}

const handleBind = async () => {
  const code = bindCode.value.trim()
  if (!/^\d{6}$/.test(code)) {
    ElMessage.warning(t('请输入 6 位谷歌验证码'))
    return
  }
  bindLoading.value = true
  try {
    await bindGoogleAuthApi(code)
    ElMessage.success(t('绑定成功'))
    bindCode.value = ''
    setupInfo.value = null
    await loadStatus()
  } catch (e: any) {
    ElMessage.error(e?.message || t('绑定失败'))
  } finally {
    bindLoading.value = false
  }
}

onMounted(() => {
  loadStatus()
})
</script>

<template>
  <div v-loading="loading" class="max-w-560px">
    <template v-if="bound">
      <ElAlert type="success" :closable="false" show-icon :title="t('您已绑定谷歌验证器')" />
      <p class="mt-12px text-sm text-gray-500">
        {{ t('登录时将要求输入 Google Authenticator 中的 6 位动态验证码。如需解除绑定，请联系管理员在「管理员管理」中清除。') }}
      </p>
    </template>

    <template v-else>
      <ElAlert
        type="info"
        :closable="false"
        show-icon
        :title="t('使用 Google Authenticator 或其他 TOTP 应用扫码绑定')"
        class="mb-16px"
      />

      <div v-if="!setupInfo" class="mb-16px">
        <ElButton type="primary" :loading="setupLoading" @click="loadSetup">
          {{ t('获取绑定二维码') }}
        </ElButton>
      </div>

      <template v-else>
        <div class="flex items-start gap-24px mb-16px">
          <img
            :src="setupInfo.qrDataUrl"
            :alt="t('谷歌验证二维码')"
            width="200"
            height="200"
            class="border border-solid border-[var(--el-border-color)]"
          />
          <div class="flex-1">
            <div class="mb-8px text-sm text-gray-600">{{ t('无法扫码时可手动输入密钥：') }}</div>
            <ElTag type="info" class="break-all whitespace-normal h-auto py-8px px-12px">
              {{ setupInfo.secret }}
            </ElTag>
            <div class="mt-12px text-sm text-gray-500">
              {{ t('扫码或输入密钥后，在下方填写应用中显示的 6 位验证码完成绑定。') }}
            </div>
          </div>
        </div>

        <ElForm label-width="100px" @submit.prevent>
          <ElFormItem :label="t('验证码')" required>
            <ElInput
              v-model="bindCode"
              maxlength="6"
              :placeholder="t('请输入 6 位验证码')"
              style="max-width: 240px"
              @keydown.enter="handleBind"
            />
          </ElFormItem>
          <ElFormItem>
            <ElButton type="primary" :loading="bindLoading" @click="handleBind">{{
              t('确认绑定')
            }}</ElButton>
            <ElButton class="ml-12px" @click="loadSetup">{{ t('刷新二维码') }}</ElButton>
          </ElFormItem>
        </ElForm>
      </template>
    </template>
  </div>
</template>
