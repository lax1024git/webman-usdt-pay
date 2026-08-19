<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElTable, ElTableColumn, ElPagination, ElButton, ElDialog } from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import { getLogListApi } from '@/api/log'
import { formatToDateTime } from '@/utils/dateUtil'

const { t } = useI18n()

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, module: '' })

const dialogVisible = ref(false)
const currentRequestData = ref<any>(null)

const ACTION_LABELS: Record<string, string> = {
  create: '创建',
  update: '更新',
  delete: '删除',
  login_success: '登录成功',
  login_failed: '登录失败'
}

const formatLogAction = (action?: string) => {
  const key = String(action ?? '')
  return ACTION_LABELS[key] ? t(ACTION_LABELS[key]) : key || '-'
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getLogListApi(queryParams.value)
    list.value = res.data.items
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

const openRequestData = (row: any) => {
  currentRequestData.value = row?.request_data ?? null
  dialogVisible.value = true
}

onMounted(loadData)
</script>

<template>
  <ContentWrap :title="t('操作日志')">
    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="admin_name" :label="t('操作人')" width="120" />
      <el-table-column prop="module" :label="t('模块')" width="120" />
      <el-table-column prop="action" :label="t('动作')" width="120">
        <template #default="scope">
          {{ formatLogAction(scope.row?.action) }}
        </template>
      </el-table-column>
      <el-table-column prop="description" :label="t('描述')" min-width="220" />
      <el-table-column prop="ip" label="IP" width="140" />
      <el-table-column prop="created_at" :label="t('时间')" width="180">
        <template #default="{ row }">{{ row.created_at ? formatToDateTime(row.created_at) : '-' }}</template>
      </el-table-column>
      <el-table-column :label="t('操作')" width="120">
        <template #default="scope">
          <el-button
            v-if="scope?.row"
            type="primary"
            link
            size="small"
            @click="openRequestData(scope.row)"
          >
            {{ t('请求参数') }}
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      class="mt-16px"
      :total="total"
      v-model:page-size="queryParams.limit"
      v-model:current-page="queryParams.page"
      layout="total, prev, pager, next"
      @change="loadData"
    />

    <el-dialog v-model="dialogVisible" :title="t('参数')" width="720px">
      <pre v-if="currentRequestData" style="white-space: pre-wrap; word-break: break-word">{{
        JSON.stringify(currentRequestData, null, 2)
      }}</pre>
      <div v-else>{{ t('暂无请求参数') }}</div>
      <template #footer>
        <el-button type="primary" @click="dialogVisible = false">{{ t('确定') }}</el-button>
      </template>
    </el-dialog>
  </ContentWrap>
</template>
