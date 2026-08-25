<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  ElMessage,
  ElTable,
  ElTableColumn,
  ElButton,
  ElPagination,
  ElDialog,
  ElForm,
  ElFormItem,
  ElInput,
  ElTag,
  ElSelect,
  ElOption
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { getPayWithdrawListApi, approvePayWithdrawApi, rejectPayWithdrawApi, retryPayWithdrawApi } from '@/api/pay'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'
import { runCsvExportJob } from '@/utils/exportDownload'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, status: '', order_no: '' })
const rejectVisible = ref(false)
const rejectTarget = ref<any>(null)
const rejectReason = ref('')

const statusMap: Record<string, string> = {
  pending: '待处理',
  reviewing: '待审核',
  approved: '已通过',
  paying: '出款中',
  success: '成功',
  rejected: '已驳回',
  failed: '失败',
  cancelled: '已取消'
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getPayWithdrawListApi(queryParams.value)
    list.value = res.data.items
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

const handleApprove = async (row: any) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('审核通过需验证')
  } catch {
    return
  }
  await approvePayWithdrawApi(row.id, { google_code: googleCode })
  ElMessage.success('审核通过')
  loadData()
}

const openReject = (row: any) => {
  rejectTarget.value = row
  rejectReason.value = ''
  rejectVisible.value = true
}

const submitReject = async () => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('驳回需验证')
  } catch {
    return
  }
  await rejectPayWithdrawApi(rejectTarget.value.id, {
    reject_reason: rejectReason.value,
    google_code: googleCode
  })
  ElMessage.success('已驳回')
  rejectVisible.value = false
  loadData()
}

const handleRetry = async (row: any) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('重试广播需验证')
  } catch {
    return
  }
  await retryPayWithdrawApi(row.id, { google_code: googleCode })
  ElMessage.success('已加入广播队列')
  loadData()
}

const handleExport = async () => {
  await runCsvExportJob('pay_withdrawals', queryParams.value, '出金订单.csv')
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="出金订单">
    <div class="mb-4 flex gap-2">
      <ElInput v-model="queryParams.order_no" placeholder="订单号" clearable style="width: 200px" />
      <ElSelect v-model="queryParams.status" placeholder="状态" clearable style="width: 140px">
        <ElOption v-for="(label, value) in statusMap" :key="value" :label="label" :value="value" />
      </ElSelect>
      <ElButton type="primary" @click="loadData">查询</ElButton>
      <ElButton @click="handleExport">导出</ElButton>
    </div>
    <ElTable v-loading="loading" :data="list" border stripe>
      <ElTableColumn prop="order_no" label="订单号" min-width="180" />
      <ElTableColumn prop="out_trade_no" label="商户单号" min-width="140" />
      <ElTableColumn prop="withdraw_amount" label="申请金额" width="110" />
      <ElTableColumn prop="payout_amount" label="实付" width="110" />
      <ElTableColumn prop="to_address" label="目标地址" min-width="160" show-overflow-tooltip />
      <ElTableColumn label="状态" width="90">
        <template #default="{ row }">
          <ElTag>{{ statusMap[row.status] || row.status }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <template v-if="row.status === 'reviewing'">
            <ElButton link type="success" @click="handleApprove(row)">通过</ElButton>
            <ElButton link type="danger" @click="openReject(row)">驳回</ElButton>
          </template>
          <ElButton v-if="row.status === 'failed'" link type="warning" @click="handleRetry(row)">重试</ElButton>
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

    <ElDialog v-model="rejectVisible" title="驳回出金" width="480px">
      <ElForm label-width="90px">
        <ElFormItem label="驳回原因"><ElInput v-model="rejectReason" type="textarea" /></ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="rejectVisible = false">取消</ElButton>
        <ElButton type="danger" @click="submitReject">确认驳回</ElButton>
      </template>
    </ElDialog>
  </ContentWrap>
</template>
