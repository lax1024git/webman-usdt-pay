<script setup lang="ts">
import { computed } from 'vue'
import {
  ElFormItem,
  ElInput,
  ElInputNumber,
  ElRadioGroup,
  ElRadio,
  ElSelect,
  ElOption,
  ElDivider
} from 'element-plus'
import { S3Upload } from '@/components/S3Upload'
import S3ConfigForm from './S3ConfigForm.vue'
import AdminMultiSelectForm from './AdminMultiSelectForm.vue'
import type { ConfigFieldSchema } from '@/api/setting/system-config'
import { useI18n } from '@/hooks/web/useI18n'

const props = defineProps<{
  field: ConfigFieldSchema
  modelValue: unknown
  values: Record<string, unknown>
  showSectionTitle?: string
}>()

const { t } = useI18n()
const displayText = (value: unknown) => t(String(value || ''))

const emit = defineEmits<{
  'update:modelValue': [value: unknown]
}>()

const visible = computed(() => {
  const when = props.field.show_when
  if (!when) {
    return true
  }
  return Object.entries(when).every(
    ([key, expected]) => String(props.values[key] ?? '') === String(expected)
  )
})

const uploadType = computed(() => {
  const accept = props.field.accept ?? 'image'
  if (accept === 'video') return 'video'
  if (accept === 'image') return 'image'
  if (accept === 'audio' || accept === 'file') return 'file'
  return 'file'
})

const uploadAccept = computed(() => {
  const accept = props.field.accept ?? 'image'
  if (accept === 'video') return 'video/mp4,video/webm,video/*'
  if (accept === 'audio') return 'audio/*,.mp3,.wav,.ogg,.m4a'
  if (accept === 'image') return 'image/jpeg,image/png,image/gif,image/webp'
  if (accept === 'file') return '.apk,.ipa,.mobileconfig,.plist,.zip,application/octet-stream'
  return '*/*'
})

const updateValue = (value: unknown) => emit('update:modelValue', value)

/** 文本类控件：对象/数组转 JSON，避免 [object Object] */
const textModelValue = computed(() => {
  const v = props.modelValue
  if (v === null || v === undefined) return ''
  if (typeof v === 'string') return v
  if (typeof v === 'number' || typeof v === 'boolean') return String(v)
  try {
    return JSON.stringify(v)
  } catch {
    return ''
  }
})
</script>

<template>
  <template v-if="visible">
    <ElDivider v-if="showSectionTitle" content-position="left">{{
      displayText(showSectionTitle)
    }}</ElDivider>

    <ElFormItem :label="displayText(field.label)">
      <template v-if="field.type === 'radio'">
        <ElRadioGroup :model-value="String(modelValue ?? '')" @update:model-value="updateValue">
          <ElRadio v-for="opt in field.options ?? []" :key="opt.value" :value="opt.value">
            {{ displayText(opt.label) }}
          </ElRadio>
        </ElRadioGroup>
      </template>

      <template v-else-if="field.type === 'select'">
        <ElSelect
          :model-value="String(modelValue ?? '')"
          filterable
          style="width: 100%"
          @update:model-value="updateValue"
        >
          <ElOption
            v-for="opt in field.options ?? []"
            :key="opt.value"
            :label="displayText(opt.label)"
            :value="opt.value"
          />
        </ElSelect>
      </template>

      <template v-else-if="field.type === 'number'">
        <ElInputNumber
          :model-value="Number(modelValue ?? field.default ?? 0)"
          :min="field.min"
          :max="field.max"
          :step="field.step ?? 1"
          style="width: 100%"
          @update:model-value="updateValue"
        />
      </template>

      <template v-else-if="field.type === 'textarea'">
        <ElInput
          type="textarea"
          :rows="field.rows ?? 3"
          :model-value="textModelValue"
          :placeholder="displayText(field.placeholder)"
          @update:model-value="updateValue"
        />
      </template>

      <template v-else-if="field.type === 'upload'">
        <S3Upload
          :model-value="String(modelValue ?? '')"
          :type="uploadType"
          :accept="uploadAccept"
          @update:model-value="updateValue"
        />
      </template>

      <template v-else-if="field.type === 's3_config'">
        <S3ConfigForm
          :model-value="(modelValue as any) ?? field.default"
          @update:model-value="updateValue"
        />
      </template>

      <template v-else-if="field.type === 'password'">
        <ElInput
          type="password"
          show-password
          :model-value="String(modelValue ?? '')"
          :readonly="field.readonly"
          :placeholder="field.placeholder"
          @update:model-value="updateValue"
        />
      </template>

      <template v-else-if="field.type === 'admin_multi_select'">
        <AdminMultiSelectForm :model-value="modelValue" @update:model-value="updateValue" />
      </template>

      <template v-else-if="field.type === 'json'">
        <ElInput
          type="textarea"
          :rows="field.rows ?? 8"
          :model-value="
            typeof modelValue === 'string' ? modelValue : JSON.stringify(modelValue ?? {}, null, 2)
          "
          :placeholder="field.placeholder ? displayText(field.placeholder) : 'JSON'"
          @update:model-value="updateValue"
        />
      </template>

      <template v-else>
        <ElInput
          :model-value="textModelValue"
          :readonly="field.readonly"
          :placeholder="field.placeholder"
          @update:model-value="updateValue"
        />
      </template>

      <div v-if="field.help" class="config-field__help">{{ displayText(field.help) }}</div>
    </ElFormItem>
  </template>
</template>

<style scoped>
.config-field__help {
  width: 100%;
  margin-top: 6px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
  white-space: normal;
  word-break: break-word;
}
</style>
