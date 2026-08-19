<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  ElTable,
  ElTableColumn,
  ElPagination,
  ElButton,
  ElSelect,
  ElOption,
  ElDatePicker,
  ElTag,
  ElMessage,
  ElMessageBox,
  ElProgress
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import { deleteExportJobApi, getExportJobListApi } from '@/api/export'
import { downloadExportFile } from '@/utils/exportDownload'
import { formatToDateTime } from '@/utils/dateUtil'

const { t } = useI18n()

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const typeOptions = ref<Array<{ value: string; label: string }>>([])
const queryParams = ref({
  page: 1,
  limit: 20,
  export_type: '',
  status: '',
  date_range: [] as string[]
})

let pollTimer: ReturnType<typeof setInterval> | null = null

const statusMap: Record<string, { label: string; type: '' | 'success' | 'warning' | 'info' | 'danger' }> = {
  pending: { label: '排队中', type: 'info' },
  running: { label: '处理中', type: 'warning' },
  success: { label: '已完成', type: 'success' },
  failed: { label: '失败', type: 'danger' }
}

const hasActiveJobs = computed(() =>
  list.value.some((row) => ['pending', 'running'].includes(String(row?.status || '')))
)

const buildParams = () => {
  const params: Record<string, any> = {
    page: queryParams.value.page,
    limit: queryParams.value.limit
  }
  if (queryParams.value.export_type) params.export_type = queryParams.value.export_type
  if (queryParams.value.status) params.status = queryParams.value.status
  const range = queryParams.value.date_range
  if (Array.isArray(range) && range.length === 2) {
    params.start_date = range[0]
    params.end_date = range[1]
  }
  return params
}

const loadData = async (silent = false) => {
  if (!silent) loading.value = true
  try {
    const res = await getExportJobListApi(buildParams())
    list.value = res.data?.items ?? []
    total.value = Number(res.data?.total ?? 0)
    if (Array.isArray(res.data?.types) && res.data.types.length) {
      typeOptions.value = res.data.types
    }
  } finally {
    if (!silent) loading.value = false
  }
}

const handleSearch = () => {
  queryParams.value.page = 1
  loadData()
}

const handleReset = () => {
  queryParams.value = {
    page: 1,
    limit: 20,
    export_type: '',
    status: '',
    date_range: []
  }
  loadData()
}

const handleDownload = async (row: any) => {
  const url = String(row?.file_url || '')
  if (!url) {
    ElMessage.warning(t('暂无下载地址'))
    return
  }
  const type = String(row?.export_type || 'export')
  try {
    await downloadExportFile(url, `${type}_${row.id || Date.now()}.csv`)
    ElMessage.success(t('开始下载'))
  } catch (e: any) {
    ElMessage.error(e?.message || t('下载失败'))
  }
}

const canDelete = (row: any) => ['success', 'failed'].includes(String(row?.status || ''))

const handleDelete = async (row: any) => {
  if (!canDelete(row)) {
    ElMessage.warning(t('仅可删除已完成或失败的导出任务'))
    return
  }
  try {
    await ElMessageBox.confirm(t('确认删除该导出任务吗？'), t('提示'), { type: 'warning' })
  } catch {
    return
  }
  try {
    await deleteExportJobApi(Number(row.id))
    ElMessage.success(t('删除成功'))
    loadData()
  } catch (e: any) {
    ElMessage.error(e?.message || t('删除失败'))
  }
}

const startPoll = () => {
  stopPoll()
  pollTimer = setInterval(() => {
    if (hasActiveJobs.value) {
      loadData(true)
    }
  }, 3000)
}

const stopPoll = () => {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

onMounted(async () => {
  await loadData()
  startPoll()
})

onUnmounted(stopPoll)
</script>

<template>
  <ContentWrap :title="t('导出任务')">
    <div class="mb-16px flex flex-wrap gap-12px items-center">
      <el-select
        v-model="queryParams.export_type"
        clearable
        :placeholder="t('导出类型')"
        style="width: 180px"
      >
        <el-option
          v-for="item in typeOptions"
          :key="item.value"
          :label="t(item.label)"
          :value="item.value"
        />
      </el-select>
      <el-select
        v-model="queryParams.status"
        clearable
        :placeholder="t('状态')"
        style="width: 140px"
      >
        <el-option :label="t('排队中')" value="pending" />
        <el-option :label="t('处理中')" value="running" />
        <el-option :label="t('已完成')" value="success" />
        <el-option :label="t('失败')" value="failed" />
      </el-select>
      <el-date-picker
        v-model="queryParams.date_range"
        type="daterange"
        value-format="YYYY-MM-DD"
        :start-placeholder="t('开始日期')"
        :end-placeholder="t('结束日期')"
        style="width: 280px"
      />
      <el-button type="primary" @click="handleSearch">{{ t('查询') }}</el-button>
      <el-button @click="handleReset">{{ t('重置') }}</el-button>
      <el-button @click="loadData()">{{ t('刷新') }}</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column :label="t('类型')" min-width="140">
        <template #default="{ row }">
          {{ t(row.export_type_label || row.export_type || '-') }}
        </template>
      </el-table-column>
      <el-table-column :label="t('状态')" width="110">
        <template #default="{ row }">
          <el-tag :type="statusMap[row.status]?.type || 'info'" size="small">
            {{ t(statusMap[row.status]?.label || row.status || '-') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('进度')" width="160">
        <template #default="{ row }">
          <div class="flex items-center gap-8px">
            <el-progress
              :percentage="Number(row.percent || 0)"
              :stroke-width="10"
              style="width: 90px"
              :status="row.status === 'success' ? 'success' : row.status === 'failed' ? 'exception' : undefined"
            />
            <span class="text-12px text-gray-500">{{ row.processed }}/{{ row.total }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="operator_name" :label="t('操作人')" width="120" />
      <el-table-column prop="message" :label="t('说明')" min-width="180" show-overflow-tooltip />
      <el-table-column prop="created_at" :label="t('创建时间')" width="170">
        <template #default="{ row }">{{ row.created_at ? formatToDateTime(row.created_at) : '-' }}</template>
      </el-table-column>
      <el-table-column prop="finished_at" :label="t('完成时间')" width="170" />
      <el-table-column :label="t('操作')" width="150" fixed="right">
        <template #default="{ row }">
          <el-button
            v-if="row.status === 'success' && row.file_url"
            type="primary"
            link
            @click="handleDownload(row)"
          >
            {{ t('下载') }}
          </el-button>
          <el-button
            v-if="canDelete(row)"
            type="danger"
            link
            @click="handleDelete(row)"
          >
            {{ t('删除') }}
          </el-button>
          <span v-if="row.status !== 'success' && !canDelete(row)" class="text-gray-400">-</span>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      class="mt-16px"
      :total="total"
      v-model:page-size="queryParams.limit"
      v-model:current-page="queryParams.page"
      layout="total, prev, pager, next"
      @current-change="loadData()"
      @size-change="loadData()"
    />
  </ContentWrap>
</template>
