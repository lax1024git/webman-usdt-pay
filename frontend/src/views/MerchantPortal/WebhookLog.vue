<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  ElButton,
  ElInput,
  ElMessage,
  ElOption,
  ElPagination,
  ElSelect,
  ElTable,
  ElTableColumn,
  ElTag
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { merchantRetryWebhookApi, merchantWebhookLogListApi } from '@/api/merchantPortal'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, order_no: '', status: '' })

const loadData = async () => {
  loading.value = true
  try {
    const res = await merchantWebhookLogListApi(queryParams.value)
    list.value = res.data.items || []
    total.value = res.data.total || 0
  } finally {
    loading.value = false
  }
}

const handleRetry = async (row: any) => {
  await merchantRetryWebhookApi(row.id)
  ElMessage.success('已加入重试队列')
  loadData()
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="回调日志">
    <div class="mb-4 flex gap-2">
      <ElInput v-model="queryParams.order_no" placeholder="订单号" clearable style="width: 200px" />
      <ElSelect v-model="queryParams.status" placeholder="状态" clearable style="width: 140px">
        <ElOption label="成功" value="success" />
        <ElOption label="失败" value="failed" />
        <ElOption label="待处理" value="pending" />
      </ElSelect>
      <ElButton type="primary" @click="loadData">查询</ElButton>
    </div>
    <ElTable v-loading="loading" :data="list" border stripe>
      <ElTableColumn prop="order_no" label="订单号" min-width="160" />
      <ElTableColumn prop="event" label="事件" width="140" />
      <ElTableColumn prop="request_url" label="URL" min-width="180" show-overflow-tooltip />
      <ElTableColumn prop="response_code" label="HTTP" width="80" />
      <ElTableColumn label="状态" width="90">
        <template #default="{ row }">
          <ElTag :type="row.status === 'success' ? 'success' : 'danger'">{{ row.status }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="created_at" label="时间" width="170" />
      <ElTableColumn label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <ElButton v-if="row.status !== 'success'" link type="primary" @click="handleRetry(row)">
            重试
          </ElButton>
        </template>
      </ElTableColumn>
    </ElTable>
    <ElPagination
      class="mt-4"
      v-model:current-page="queryParams.page"
      v-model:page-size="queryParams.limit"
      :total="total"
      layout="total, prev, pager, next"
      @current-change="loadData"
    />
  </ContentWrap>
</template>
