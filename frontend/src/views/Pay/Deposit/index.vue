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
import { getPayDepositListApi, manualCreditDepositApi } from '@/api/pay'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'
import { runCsvExportJob } from '@/utils/exportDownload'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, status: '', order_no: '' })
const manualVisible = ref(false)
const manualTarget = ref<any>(null)
const manualForm = ref({ paid_amount: '', tx_hash: '' })

const statusMap: Record<string, string> = {
  pending: '待支付',
  detecting: '确认中',
  success: '成功',
  expired: '已过期',
  failed: '失败',
  manual: '待补单'
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getPayDepositListApi(queryParams.value)
    list.value = res.data.items
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

const openManual = (row: any) => {
  manualTarget.value = row
  manualForm.value = { paid_amount: row.amount, tx_hash: row.tx_hash || '' }
  manualVisible.value = true
}

const submitManual = async () => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('人工补单需验证')
  } catch {
    return
  }
  await manualCreditDepositApi(manualTarget.value.id, { ...manualForm.value, google_code: googleCode })
  ElMessage.success('补单成功')
  manualVisible.value = false
  loadData()
}

const handleExport = async () => {
  await runCsvExportJob('pay_deposits', queryParams.value, '入金订单.csv')
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="入金订单">
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
      <ElTableColumn prop="amount" label="金额" width="110" />
      <ElTableColumn prop="deposit_address" label="收款地址" min-width="160" show-overflow-tooltip />
      <ElTableColumn label="状态" width="90">
        <template #default="{ row }">
          <ElTag>{{ statusMap[row.status] || row.status }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="tx_hash" label="TxHash" min-width="140" show-overflow-tooltip />
      <ElTableColumn label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <ElButton
            v-if="['pending', 'manual', 'detecting'].includes(row.status)"
            link
            type="primary"
            @click="openManual(row)"
          >
            补单
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

    <ElDialog v-model="manualVisible" title="人工补单" width="480px">
      <ElForm label-width="100px">
        <ElFormItem label="到账金额"><ElInput v-model="manualForm.paid_amount" /></ElFormItem>
        <ElFormItem label="Tx Hash"><ElInput v-model="manualForm.tx_hash" /></ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="manualVisible = false">取消</ElButton>
        <ElButton type="primary" @click="submitManual">确认入账</ElButton>
      </template>
    </ElDialog>
  </ContentWrap>
</template>
