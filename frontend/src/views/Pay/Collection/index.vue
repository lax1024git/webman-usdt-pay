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
import { getPayCollectionListApi, retryPayCollectionApi, triggerPayCollectionApi } from '@/api/pay'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, status: '', from_address: '' })

const statusMap: Record<string, string> = {
  pending: '待处理',
  broadcasting: '广播中',
  success: '成功',
  failed: '失败'
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getPayCollectionListApi(queryParams.value)
    list.value = res.data.items || []
    total.value = res.data.total || 0
  } finally {
    loading.value = false
  }
}

const handleTrigger = async () => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('触发归集需验证')
  } catch {
    return
  }
  const res = await triggerPayCollectionApi({ google_code: googleCode })
  ElMessage.success(res.msg || `已入队 ${res.data?.queued ?? 0} 笔`)
  loadData()
}

const handleRetry = async (row: any) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('重试归集需验证')
  } catch {
    return
  }
  await retryPayCollectionApi(row.id, { google_code: googleCode })
  ElMessage.success('已重新入队')
  loadData()
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="归集任务">
    <div class="mb-4 flex gap-2">
      <ElInput v-model="queryParams.from_address" placeholder="来源地址" clearable style="width: 220px" />
      <ElSelect v-model="queryParams.status" placeholder="状态" clearable style="width: 140px">
        <ElOption v-for="(label, value) in statusMap" :key="value" :label="label" :value="value" />
      </ElSelect>
      <ElButton type="primary" @click="loadData">查询</ElButton>
      <ElButton type="warning" @click="handleTrigger">扫描并归集</ElButton>
    </div>
    <p class="mb-4 text-gray-500 text-sm">
      仅归集 HD 入金地址上的 USDT。请先开启 PAY_COLLECTION_ENABLED，并保证子地址有足够 TRX/能量。
    </p>
    <ElTable v-loading="loading" :data="list" border stripe>
      <ElTableColumn prop="id" label="ID" width="80" />
      <ElTableColumn prop="from_address" label="来源" min-width="180" show-overflow-tooltip />
      <ElTableColumn prop="to_address" label="热钱包" min-width="180" show-overflow-tooltip />
      <ElTableColumn prop="amount" label="金额" width="120" />
      <ElTableColumn label="状态" width="100">
        <template #default="{ row }">
          <ElTag>{{ statusMap[row.status] || row.status }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="tx_hash" label="TxHash" min-width="140" show-overflow-tooltip />
      <ElTableColumn prop="error_message" label="失败原因" min-width="160" show-overflow-tooltip />
      <ElTableColumn prop="created_at" label="创建时间" width="170" />
      <ElTableColumn label="操作" width="90" fixed="right">
        <template #default="{ row }">
          <ElButton v-if="row.status === 'failed'" link type="primary" @click="handleRetry(row)">重试</ElButton>
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
