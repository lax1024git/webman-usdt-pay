<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { ElSelect, ElOption } from 'element-plus'
import { getAdminListApi } from '@/api/admin'
import { useI18n } from '@/hooks/web/useI18n'

const props = defineProps<{
  modelValue: unknown
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const { t } = useI18n()
const loading = ref(false)
const options = ref<Array<{ id: number; label: string }>>([])
const selected = ref<number[]>([])

const normalizeIds = (value: unknown): number[] => {
  if (Array.isArray(value)) {
    return value.map((item) => Number(item)).filter((id) => Number.isFinite(id) && id > 0)
  }
  if (typeof value === 'string' && value.trim() !== '') {
    try {
      const parsed = JSON.parse(value)
      if (Array.isArray(parsed)) {
        return normalizeIds(parsed)
      }
    } catch {
      return value
        .split(/[,，\s]+/)
        .map((item) => Number(item))
        .filter((id) => Number.isFinite(id) && id > 0)
    }
  }
  return []
}

watch(
  () => props.modelValue,
  (value) => {
    selected.value = normalizeIds(value)
  },
  { immediate: true }
)

const loadAdmins = async () => {
  loading.value = true
  try {
    const res = await getAdminListApi({ page: 1, limit: 200, status: 1 })
    const items = res.data?.items ?? res.data?.list ?? []
    options.value = items.map((item: any) => ({
      id: Number(item.id),
      label: String(item.nickname || item.username || item.id)
    }))
  } catch {
    options.value = []
  } finally {
    loading.value = false
  }
}

const onChange = (value: number[]) => {
  selected.value = value
  emit('update:modelValue', value)
}

onMounted(loadAdmins)
</script>

<template>
  <ElSelect
    :model-value="selected"
    multiple
    filterable
    clearable
    collapse-tags
    collapse-tags-tooltip
    :loading="loading"
    :placeholder="t('请选择管理员')"
    style="width: 100%"
    @update:model-value="onChange"
  >
    <ElOption
      v-for="item in options"
      :key="item.id"
      :label="item.label"
      :value="item.id"
    />
  </ElSelect>
</template>
