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
  ElTag
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { getPayPlatformListApi, updatePayPlatformApi } from '@/api/pay'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20 })
const dialogVisible = ref(false)
const editing = ref<any>(null)
const form = ref<any>({})

const loadData = async () => {
  loading.value = true
  try {
    const res = await getPayPlatformListApi(queryParams.value)
    list.value = res.data.items
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

const openEdit = (row: any) => {
  editing.value = row
  form.value = { ...row }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('更新通道需验证')
  } catch {
    return
  }
  await updatePayPlatformApi(editing.value.id, { ...form.value, google_code: googleCode })
  ElMessage.success('更新成功')
  dialogVisible.value = false
  loadData()
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="支付通道">
    <ElTable v-loading="loading" :data="list" border stripe>
      <ElTableColumn prop="code" label="编码" width="140" />
      <ElTableColumn prop="name" label="名称" width="140" />
      <ElTableColumn prop="chain" label="链" width="80" />
      <ElTableColumn prop="min_deposit_amount" label="最小入金" width="110" />
      <ElTableColumn prop="max_deposit_amount" label="最大入金" width="110" />
      <ElTableColumn prop="deposit_confirmations" label="确认数" width="80" />
      <ElTableColumn label="状态" width="80">
        <template #default="{ row }">
          <ElTag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '关闭' }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <ElButton link type="primary" @click="openEdit(row)">编辑</ElButton>
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

    <ElDialog v-model="dialogVisible" title="编辑通道" width="560px">
      <ElForm label-width="120px">
        <ElFormItem label="名称"><ElInput v-model="form.name" /></ElFormItem>
        <ElFormItem label="最小入金"><ElInput v-model="form.min_deposit_amount" /></ElFormItem>
        <ElFormItem label="最大入金"><ElInput v-model="form.max_deposit_amount" /></ElFormItem>
        <ElFormItem label="最小出金"><ElInput v-model="form.min_withdraw_amount" /></ElFormItem>
        <ElFormItem label="最大出金"><ElInput v-model="form.max_withdraw_amount" /></ElFormItem>
        <ElFormItem label="入金确认数"><ElInput v-model="form.deposit_confirmations" /></ElFormItem>
        <ElFormItem label="超时(秒)"><ElInput v-model="form.deposit_expire_seconds" /></ElFormItem>
        <ElFormItem label="金额匹配"><ElInput v-model="form.amount_match_mode" placeholder="exact/tolerant/actual" /></ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" @click="handleSubmit">保存</ElButton>
      </template>
    </ElDialog>
  </ContentWrap>
</template>
