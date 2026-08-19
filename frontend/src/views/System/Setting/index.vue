<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElButton, ElForm } from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import ConfigField from './components/ConfigField.vue'
import {
  getSystemConfigBundleApi,
  saveSystemConfigApi,
  type ConfigTabSchema,
  type ConfigFieldSchema
} from '@/api/setting/system-config'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const activeTab = ref('base')
const tabs = ref<ConfigTabSchema[]>([])
const values = ref<Record<string, unknown>>({})
const clientIp = ref('')
const displayText = (value: unknown) => t(String(value || ''))

const sectionTitle = (tab: ConfigTabSchema, field: ConfigFieldSchema, index: number): string => {
  if (field.section === undefined || !tab.sections?.length) {
    return ''
  }
  const prev = tab.fields[index - 1]
  if (prev && prev.section === field.section) {
    return ''
  }
  return tab.sections[field.section]?.label ?? ''
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getSystemConfigBundleApi()
    tabs.value = res.data?.tabs ?? []
    values.value = { ...(res.data?.values ?? {}) }
    clientIp.value = res.data?.client_ip ?? ''
    if (tabs.value.length && !tabs.value.some((t) => t.key === activeTab.value)) {
      activeTab.value = tabs.value[0].key
    }
  } finally {
    loading.value = false
  }
}

const handleSave = async () => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('保存系统参数需验证'))
  } catch {
    return
  }

  saving.value = true
  try {
    const payload: Record<string, unknown> = { ...values.value, google_code: googleCode }
    if (payload.s3_config && typeof payload.s3_config === 'object') {
      const s3 = payload.s3_config as Record<string, unknown>
      payload.s3_config = {
        ...s3,
        proxy: s3.proxy || null
      }
    }
    await saveSystemConfigApi(payload)
    ElMessage.success(t('保存成功'))
    await loadData()
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <ContentWrap :title="t('系统参数')">
    <div v-loading="loading" class="settings-layout">
      <div class="settings-nav">
        <a
          v-for="tab in tabs"
          :key="tab.key"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
          >{{ displayText(tab.label) }}</a
        >
      </div>

      <div class="settings-main">
        <div
          v-for="tab in tabs"
          :key="tab.key"
          v-show="activeTab === tab.key"
          class="setting-block"
        >
          <div class="block-hd">{{ displayText(tab.label) }}</div>
          <div class="block-bd">
            <ElForm label-width="220px" class="system-config__form">
              <template v-for="(field, index) in tab.fields" :key="field.key">
                <ConfigField
                  v-model="values[field.key]"
                  :field="field"
                  :values="values"
                  :show-section-title="sectionTitle(tab, field, index)"
                />
              </template>

              <div v-if="tab.key === 'register' && clientIp" class="system-config__ip">
                <div class="system-config__ip-label">{{ t('您当前 IP：') }}{{ clientIp }}</div>
              </div>
            </ElForm>
          </div>
        </div>

        <div class="config-actions">
          <ElButton
            v-permission="'setting:bundle-save'"
            type="primary"
            :loading="saving"
            @click="handleSave"
          >
            {{ t('保存配置') }}
          </ElButton>
        </div>
      </div>
    </div>
  </ContentWrap>
</template>

<style scoped>
.system-config__form {
  max-width: 100%;
  padding: 8px 4px 8px;
}

.system-config__form :deep(.el-form-item) {
  margin-bottom: 18px;
}

.system-config__ip {
  margin-top: 8px;
  color: var(--el-text-color-secondary);
  font-size: 13px;
}

.system-config__ip-label {
  padding-left: 220px;
}
</style>
