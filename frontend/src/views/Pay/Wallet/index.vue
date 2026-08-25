<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  ElButton,
  ElCard,
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
import { getPayHotWalletApi, getPayWalletListApi } from '@/api/pay'

const loading = ref(false)
const listLoading = ref(false)
const status = ref<any>({})
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, type: '', status: '', address: '' })

const statusMap: Record<number, string> = {
  0: '禁用',
  1: '可用',
  2: '已分配',
  3: '已归集'
}

const loadHot = async () => {
  loading.value = true
  try {
    const res = await getPayHotWalletApi()
    status.value = res.data || {}
  } finally {
    loading.value = false
  }
}

const loadList = async () => {
  listLoading.value = true
  try {
    const res = await getPayWalletListApi(queryParams.value)
    list.value = res.data.items || []
    total.value = res.data.total || 0
  } finally {
    listLoading.value = false
  }
}

const loadAll = async () => {
  await Promise.all([loadHot(), loadList()])
}

onMounted(loadAll)
</script>

<template>
  <ContentWrap title="钱包管理">
    <ElButton class="mb-4" :loading="loading" @click="loadAll">刷新</ElButton>
    <ElCard v-loading="loading" class="mb-4" shadow="never">
      <ElDescriptions :column="2" border>
        <ElDescriptionsItem label="配置状态">
          <ElTag :type="status.configured ? 'success' : 'danger'">
            {{ status.configured ? '已配置' : '未配置' }}
          </ElTag>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="地址">{{ status.address || '-' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="USDT 余额">
          {{ status.usdt_balance ?? '0.000000' }}
          <ElTag v-if="status.usdt_low" class="ml-2" type="danger" size="small">低于阈值</ElTag>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="TRX 余额">
          {{ status.trx_balance ?? '0.000000' }}
          <ElTag v-if="status.trx_low" class="ml-2" type="danger" size="small">低于阈值</ElTag>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="USDT 告警阈值">{{ status.usdt_min ?? '-' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="TRX 告警阈值">{{ status.trx_min ?? '-' }}</ElDescriptionsItem>
      </ElDescriptions>
      <p class="mt-4 text-gray-500 text-sm">
        请在 .env 配置 TRON_HOT_WALLET_ADDRESS 与私钥。加密私钥：
        <code>php webman pay:encrypt-key &lt;hex&gt;</code>
        ；阈值：PAY_HOT_WALLET_USDT_MIN / PAY_HOT_WALLET_TRX_MIN
      </p>
    </ElCard>

    <div class="mb-4 flex gap-2">
      <ElInput v-model="queryParams.address" placeholder="地址" clearable style="width: 220px" />
      <ElSelect v-model="queryParams.type" placeholder="类型" clearable style="width: 140px">
        <ElOption label="入金" value="deposit" />
        <ElOption label="热钱包" value="hot" />
        <ElOption label="冷钱包" value="cold" />
      </ElSelect>
      <ElSelect v-model="queryParams.status" placeholder="状态" clearable style="width: 140px">
        <ElOption v-for="(label, value) in statusMap" :key="value" :label="label" :value="Number(value)" />
      </ElSelect>
      <ElButton type="primary" @click="loadList">查询</ElButton>
    </div>

    <ElTable v-loading="listLoading" :data="list" border stripe>
      <ElTableColumn prop="id" label="ID" width="80" />
      <ElTableColumn prop="address" label="地址" min-width="220" show-overflow-tooltip />
      <ElTableColumn prop="type" label="类型" width="100" />
      <ElTableColumn prop="derivation_index" label="派生索引" width="100" />
      <ElTableColumn prop="order_id" label="绑定订单" width="110" />
      <ElTableColumn prop="balance" label="余额缓存" width="120" />
      <ElTableColumn label="状态" width="100">
        <template #default="{ row }">
          <ElTag>{{ statusMap[row.status] ?? row.status }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="updated_at" label="更新时间" width="170" />
    </ElTable>
    <ElPagination
      class="mt-4"
      v-model:current-page="queryParams.page"
      v-model:page-size="queryParams.limit"
      :total="total"
      layout="total, prev, pager, next"
      @current-change="loadList"
    />
  </ContentWrap>
</template>
