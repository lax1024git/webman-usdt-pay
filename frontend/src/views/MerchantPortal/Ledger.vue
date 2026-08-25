<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  ElButton,
  ElInput,
  ElOption,
  ElPagination,
  ElSelect,
  ElTable,
  ElTableColumn
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { merchantLedgerListApi } from '@/api/merchantPortal'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, biz_type: '', order_no: '' })

const bizMap: Record<string, string> = {
  deposit: '入金入账',
  withdraw_freeze: '出金冻结',
  withdraw_unfreeze: '出金解冻',
  withdraw_success: '出金成功',
  fee: '手续费'
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await merchantLedgerListApi(queryParams.value)
    list.value = res.data.items || []
    total.value = res.data.total || 0
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="资金流水">
    <div class="mb-4 flex gap-2">
      <ElInput v-model="queryParams.order_no" placeholder="订单号" clearable style="width: 200px" />
      <ElSelect v-model="queryParams.biz_type" placeholder="类型" clearable style="width: 160px">
        <ElOption v-for="(label, value) in bizMap" :key="value" :label="label" :value="value" />
      </ElSelect>
      <ElButton type="primary" @click="loadData">查询</ElButton>
    </div>
    <ElTable v-loading="loading" :data="list" border stripe>
      <ElTableColumn prop="order_no" label="订单号" min-width="170" />
      <ElTableColumn label="类型" width="120">
        <template #default="{ row }">
          {{ bizMap[row.biz_type] || row.biz_type }}
        </template>
      </ElTableColumn>
      <ElTableColumn prop="change_amount" label="变动金额" width="130" />
      <ElTableColumn prop="available_after" label="变动后可用" width="130" />
      <ElTableColumn prop="frozen_after" label="变动后冻结" width="130" />
      <ElTableColumn prop="remark" label="备注" min-width="140" show-overflow-tooltip />
      <ElTableColumn prop="created_at" label="时间" width="170" />
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
