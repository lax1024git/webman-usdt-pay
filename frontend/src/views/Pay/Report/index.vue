<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  ElButton,
  ElCard,
  ElDatePicker,
  ElDescriptions,
  ElDescriptionsItem,
  ElTable,
  ElTableColumn,
  ElTag
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { getPayReportDailyApi, getPayReportSummaryApi } from '@/api/pay'
import { formatToDate } from '@/utils/dateUtil'

const loading = ref(false)
const summary = ref<any>({
  deposit_count: 0,
  deposit_amount: '0',
  deposit_net: '0',
  withdraw_count: 0,
  withdraw_amount: '0',
  withdraw_payout: '0',
  net_inflow: '0'
})
const merchantStats = ref<any[]>([])
const dailyItems = ref<any[]>([])

const dateRange = ref<[string, string]>([
  formatToDate(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)),
  formatToDate(new Date())
])

const requestParams = computed(() => ({
  date_from: dateRange.value?.[0],
  date_to: dateRange.value?.[1]
}))

const loadData = async () => {
  loading.value = true
  try {
    const [summaryRes, dailyRes] = await Promise.all([
      getPayReportSummaryApi(requestParams.value),
      getPayReportDailyApi(requestParams.value)
    ])
    summary.value = summaryRes.data?.summary || summary.value
    merchantStats.value = summaryRes.data?.merchant_stats || []
    dailyItems.value = dailyRes.data?.items || []
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="运营报表（充付概览）">
    <div class="mb-4 flex gap-2 items-center">
      <ElDatePicker
        v-model="dateRange"
        type="daterange"
        range-separator="至"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        value-format="YYYY-MM-DD"
      />
      <ElButton type="primary" :loading="loading" @click="loadData">查询</ElButton>
    </div>

    <ElCard v-loading="loading" shadow="never" class="mb-4">
      <ElDescriptions :column="3" border>
        <ElDescriptionsItem label="入金笔数">{{ summary.deposit_count }}</ElDescriptionsItem>
        <ElDescriptionsItem label="入金金额">{{ summary.deposit_amount ?? '0' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="入金净额">{{ summary.deposit_net ?? '0' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="出金笔数">{{ summary.withdraw_count }}</ElDescriptionsItem>
        <ElDescriptionsItem label="出金申请额">{{ summary.withdraw_amount ?? '0' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="净流入">{{ summary.net_inflow ?? '0' }}</ElDescriptionsItem>
      </ElDescriptions>
    </ElCard>

    <ElCard v-loading="loading" shadow="never" class="mb-4" header="按日明细">
      <ElTable :data="dailyItems" border stripe>
        <ElTableColumn prop="date" label="日期" width="120" />
        <ElTableColumn prop="deposit_count" label="入金笔数" width="100" />
        <ElTableColumn prop="deposit_amount" label="入金金额" width="140" />
        <ElTableColumn prop="deposit_net" label="入金净额" width="140" />
        <ElTableColumn prop="withdraw_count" label="出金笔数" width="100" />
        <ElTableColumn prop="withdraw_amount" label="出金金额" width="140" />
        <ElTableColumn prop="net_inflow" label="净流入" width="140" />
      </ElTable>
    </ElCard>

    <ElCard v-loading="loading" shadow="never" header="商户汇总">
      <ElTable :data="merchantStats" border stripe>
        <ElTableColumn prop="merchant_no" label="商户号" min-width="140" />
        <ElTableColumn prop="merchant_name" label="商户名称" min-width="160" />
        <ElTableColumn prop="deposit_count" label="入金笔数" width="100" />
        <ElTableColumn prop="deposit_net" label="入金净额" width="140" />
        <ElTableColumn prop="withdraw_count" label="出金笔数" width="100" />
        <ElTableColumn prop="withdraw_amount" label="出金申请额" width="140" />
        <ElTableColumn prop="net_inflow" label="净流入" width="140" />
        <ElTableColumn label="状态" width="120">
          <template #default="{ row }">
            <ElTag :type="String(row.net_inflow ?? '').startsWith('-') ? 'danger' : 'success'">
              {{ String(row.net_inflow ?? '').startsWith('-') ? '净流出' : '净流入' }}
            </ElTag>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>
  </ContentWrap>
</template>
