<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElDialog, ElProgress, ElButton, ElMessage } from 'element-plus'
import { useI18n } from '@/hooks/web/useI18n'
import { getExportJobApi } from '@/api/export'
import { downloadExportFile } from '@/utils/exportDownload'
import { useExportProgressStore } from '@/store/modules/exportProgress'

const props = defineProps<{
  jobId: number
  filename: string
  exportType?: string
}>()

const { t } = useI18n()
const router = useRouter()
const progressStore = useExportProgressStore()

const visible = computed({
  get: () => progressStore.items.find((item) => item.jobId === props.jobId)?.visible ?? false,
  set: (val: boolean) => {
    if (!val) progressStore.close(props.jobId)
  }
})

const status = ref('pending')
const percent = ref(0)
const processed = ref(0)
const total = ref(0)
const message = ref('')
const fileUrl = ref('')
const typeLabel = ref(props.exportType || '')
const skipBackgroundTip = ref(false)

let timer: ReturnType<typeof setInterval> | null = null

const statusText = computed(() => {
  if (status.value === 'success') return t('导出完成')
  if (status.value === 'failed') return t('导出失败')
  if (status.value === 'running') return t('导出处理中')
  return t('导出排队中')
})

const progressStatus = computed(() => {
  if (status.value === 'success') return 'success'
  if (status.value === 'failed') return 'exception'
  return undefined
})

const stopPoll = () => {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

const applyJob = (job: any) => {
  status.value = String(job?.status || 'pending')
  percent.value = Number(job?.percent || 0)
  processed.value = Number(job?.processed || 0)
  total.value = Number(job?.total || 0)
  message.value = String(job?.message || '')
  fileUrl.value = String(job?.file_url || '')
  if (job?.export_type_label) {
    typeLabel.value = String(job.export_type_label)
  }
}

const pollOnce = async () => {
  try {
    const res = await getExportJobApi(props.jobId)
    applyJob(res?.data ?? res)
    if (status.value === 'success' || status.value === 'failed') {
      stopPoll()
    }
  } catch (e: any) {
    status.value = 'failed'
    message.value = e?.message || t('查询导出进度失败')
    stopPoll()
  }
}

const startPoll = () => {
  stopPoll()
  pollOnce()
  timer = setInterval(pollOnce, 2000)
}

const dismiss = (options?: { tip?: boolean; goList?: boolean }) => {
  skipBackgroundTip.value = options?.tip === false
  stopPoll()
  visible.value = false
  if (options?.goList) {
    router.push('/system-config/exports')
  }
}

const downloading = ref(false)

const handleDownload = async () => {
  if (!fileUrl.value) {
    ElMessage.warning(t('暂无下载地址'))
    return
  }
  downloading.value = true
  try {
    await downloadExportFile(fileUrl.value, props.filename)
    ElMessage.success(t('开始下载'))
  } catch (e: any) {
    ElMessage.error(e?.message || t('下载失败'))
  } finally {
    downloading.value = false
  }
}

watch(visible, (val, oldVal) => {
  if (val) {
    startPoll()
    return
  }
  if (oldVal && !val) {
    stopPoll()
    if (
      !skipBackgroundTip.value &&
      status.value !== 'success' &&
      status.value !== 'failed'
    ) {
      ElMessage.info(t('导出任务继续在后台处理，完成后可在「导出任务」中下载'))
    }
    // 延迟移除，等 dialog 动画结束
    setTimeout(() => progressStore.remove(props.jobId), 300)
  }
})

onMounted(() => {
  if (visible.value) startPoll()
})

onUnmounted(stopPoll)
</script>

<template>
  <el-dialog
    v-model="visible"
    :title="t('导出进度')"
    width="460px"
    :close-on-click-modal="false"
    append-to-body
    destroy-on-close
  >
    <div>
      <div class="mb-12px text-14px">
        <span class="text-gray-500">{{ t('类型') }}：</span>
        {{ typeLabel ? t(typeLabel) : jobId }}
        <span class="ml-12px text-gray-500">#{{ jobId }}</span>
      </div>
      <div class="mb-8px font-medium">{{ statusText }}</div>
      <el-progress :percentage="percent" :status="progressStatus" :stroke-width="14" />
      <div class="mt-8px text-12px text-gray-500">
        {{ processed }}/{{ total }}
        <span v-if="message" class="ml-8px">{{ message }}</span>
      </div>
      <div v-if="status === 'success'" class="mt-12px text-13px text-gray-600">
        {{ t('可直接下载，或稍后在「导出任务」列表中下载') }}
      </div>
      <div v-else-if="status === 'failed'" class="mt-12px text-13px text-red-500">
        {{ message || t('导出失败') }}
      </div>
      <div v-else class="mt-12px text-13px text-gray-500">
        {{ t('可关闭此窗口，任务会在后台继续，完成后到「导出任务」下载') }}
      </div>
    </div>

    <template #footer>
      <el-button @click="dismiss({ tip: false, goList: true })">{{ t('去任务列表') }}</el-button>
      <el-button
        v-if="status === 'success' && fileUrl"
        type="success"
        :loading="downloading"
        @click="handleDownload"
      >
        {{ t('下载') }}
      </el-button>
      <el-button type="primary" @click="dismiss({ tip: true })">
        {{ status === 'success' || status === 'failed' ? t('关闭') : t('后台运行') }}
      </el-button>
    </template>
  </el-dialog>
</template>
