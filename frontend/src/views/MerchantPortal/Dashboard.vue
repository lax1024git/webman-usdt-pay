<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  ElButton,
  ElCard,
  ElDatePicker,
  ElDescriptions,
  ElDescriptionsItem
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { merchantBalanceApi, merchantMeApi, merchantStatisticsApi } from '@/api/merchantPortal'
import { formatToDate } from '@/utils/dateUtil'
import { setMerchantInfo } from '@/utils/merchantAuth'

const loading = ref(false)
const me = ref<any>({})
const balance = ref({ currency: 'USDT', chain: 'TRC20', available: '0', frozen: '0' })
const stats = ref<any>({
  deposit_count: 0,
  deposit_amount: '0',
  deposit_net: '0',
  deposit_fee: '0',
  withdraw_count: 0,
  withdraw_amount: '0',
  withdraw_fee: '0',
  net_inflow: '0'
})
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
    const [meRes, balanceRes, statsRes] = await Promise.all([
      merchantMeApi(),
      merchantBalanceApi(),
      merchantStatisticsApi(requestParams.value)
    ])
    me.value = meRes.data || {}
    if (me.value.name) {
      setMerchantInfo({
        id: me.value.id,
        merchant_no: me.value.merchant_no,
        name: me.value.name
      })
    }
    balance.value = balanceRes.data || balance.value
    stats.value = statsRes.data || stats.value
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="账户概览">
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

    <div class="grid mb-4">
      <ElCard shadow="never">
        <div class="label">可用余额</div>
        <div class="value">{{ balance.available }} {{ balance.currency }}</div>
      </ElCard>
      <ElCard shadow="never">
        <div class="label">冻结余额</div>
        <div class="value">{{ balance.frozen }} {{ balance.currency }}</div>
      </ElCard>
      <ElCard shadow="never">
        <div class="label">净流入</div>
        <div class="value">{{ stats.net_inflow }}</div>
      </ElCard>
    </div>

    <ElCard v-loading="loading" shadow="never">
      <ElDescriptions :column="3" border>
        <ElDescriptionsItem label="商户号">{{ me.merchant_no }}</ElDescriptionsItem>
        <ElDescriptionsItem label="商户名称">{{ me.name }}</ElDescriptionsItem>
        <ElDescriptionsItem label="登录邮箱">{{ me.login_email }}</ElDescriptionsItem>
        <ElDescriptionsItem label="入金笔数">{{ stats.deposit_count }}</ElDescriptionsItem>
        <ElDescriptionsItem label="入金金额">{{ stats.deposit_amount }}</ElDescriptionsItem>
        <ElDescriptionsItem label="入金净额">{{ stats.deposit_net }}</ElDescriptionsItem>
        <ElDescriptionsItem label="出金笔数">{{ stats.withdraw_count }}</ElDescriptionsItem>
        <ElDescriptionsItem label="出金金额">{{ stats.withdraw_amount }}</ElDescriptionsItem>
        <ElDescriptionsItem label="手续费">{{ stats.deposit_fee }} / {{ stats.withdraw_fee }}</ElDescriptionsItem>
      </ElDescriptions>
    </ElCard>
  </ContentWrap>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.label {
  color: var(--el-text-color-secondary);
  font-size: 13px;
}
.value {
  margin-top: 8px;
  font-size: 22px;
  font-weight: 700;
}
</style>
