<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  ElButton,
  ElDialog,
  ElDescriptions,
  ElDescriptionsItem,
  ElInput,
  ElOption,
  ElPagination,
  ElSelect,
  ElTable,
  ElTableColumn,
  ElTag
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { merchantWithdrawListApi, merchantWithdrawShowApi } from '@/api/merchantPortal'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, status: '', order_no: '', out_trade_no: '' })
const detailVisible = ref(false)
const detail = ref<any>(null)

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
    const res = await merchantWithdrawListApi(queryParams.value)
    list.value = res.data.items || []
    total.value = res.data.total || 0
  } finally {
    loading.value = false
  }
}

const openDetail = async (row: any) => {
  const res = await merchantWithdrawShowApi(row.id)
  detail.value = res.data
  detailVisible.value = true
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="出金订单">
    <div class="mb-4 flex gap-2">
      <ElInput v-model="queryParams.order_no" placeholder="平台订单号" clearable style="width: 200px" />
      <ElInput v-model="queryParams.out_trade_no" placeholder="商户单号" clearable style="width: 180px" />
      <ElSelect v-model="queryParams.status" placeholder="状态" clearable style="width: 140px">
        <ElOption v-for="(label, value) in statusMap" :key="value" :label="label" :value="value" />
      </ElSelect>
      <ElButton type="primary" @click="loadData">查询</ElButton>
    </div>
    <ElTable v-loading="loading" :data="list" border stripe>
      <ElTableColumn prop="order_no" label="订单号" min-width="180" />
      <ElTableColumn prop="out_trade_no" label="商户单号" min-width="140" />
      <ElTableColumn prop="withdraw_amount" label="金额" width="110" />
      <ElTableColumn prop="fee_amount" label="手续费" width="110" />
      <ElTableColumn prop="to_address" label="收款地址" min-width="160" show-overflow-tooltip />
      <ElTableColumn label="状态" width="90">
        <template #default="{ row }">
          <ElTag>{{ statusMap[row.status] || row.status }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="tx_hash" label="TxHash" min-width="140" show-overflow-tooltip />
      <ElTableColumn prop="created_at" label="创建时间" width="170" />
      <ElTableColumn label="操作" width="90" fixed="right">
        <template #default="{ row }">
          <ElButton link type="primary" @click="openDetail(row)">详情</ElButton>
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

    <ElDialog v-model="detailVisible" title="出金详情" width="640px">
      <ElDescriptions v-if="detail" :column="2" border>
        <ElDescriptionsItem label="订单号">{{ detail.order_no }}</ElDescriptionsItem>
        <ElDescriptionsItem label="商户单号">{{ detail.out_trade_no }}</ElDescriptionsItem>
        <ElDescriptionsItem label="金额">{{ detail.withdraw_amount }}</ElDescriptionsItem>
        <ElDescriptionsItem label="手续费">{{ detail.fee_amount }}</ElDescriptionsItem>
        <ElDescriptionsItem label="状态">{{ statusMap[detail.status] || detail.status }}</ElDescriptionsItem>
        <ElDescriptionsItem label="驳回原因">{{ detail.reject_reason }}</ElDescriptionsItem>
        <ElDescriptionsItem label="收款地址" :span="2">{{ detail.to_address }}</ElDescriptionsItem>
        <ElDescriptionsItem label="TxHash" :span="2">{{ detail.tx_hash }}</ElDescriptionsItem>
      </ElDescriptions>
    </ElDialog>
  </ContentWrap>
</template>
