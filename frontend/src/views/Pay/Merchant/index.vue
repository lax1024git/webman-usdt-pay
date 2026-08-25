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
  ElOption,
  ElAlert
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import {
  getPayMerchantListApi,
  createPayMerchantApi,
  updatePayMerchantApi,
  resetPayMerchantSecretApi
} from '@/api/pay'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, keyword: '' })
const dialogVisible = ref(false)
const secretDialogVisible = ref(false)
const secretInfo = ref({ api_key: '', api_secret: '' })
const editingId = ref<number | null>(null)
const form = ref({
  name: '',
  login_email: '',
  login_password: '',
  notify_url: '',
  ip_whitelist: '',
  deposit_fee_rate: '0',
  withdraw_fee_rate: '0',
  auto_withdraw_max: '1000',
  status: 1,
  remark: ''
})

const loadData = async () => {
  loading.value = true
  try {
    const res = await getPayMerchantListApi(queryParams.value)
    list.value = res.data.items
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  editingId.value = null
  form.value = {
    name: '',
    login_email: '',
    login_password: '',
    notify_url: '',
    ip_whitelist: '',
    deposit_fee_rate: '0.01',
    withdraw_fee_rate: '0.005',
    auto_withdraw_max: '1000',
    status: 1,
    remark: ''
  }
  dialogVisible.value = true
}

const openEdit = (row: any) => {
  editingId.value = row.id
  form.value = {
    name: row.name,
    login_email: row.login_email || '',
    login_password: '',
    notify_url: row.notify_url || '',
    ip_whitelist: (row.ip_whitelist || []).join(','),
    deposit_fee_rate: row.deposit_fee_rate || '0',
    withdraw_fee_rate: row.withdraw_fee_rate || '0',
    auto_withdraw_max: row.auto_withdraw_max || '0',
    status: row.status,
    remark: row.remark || ''
  }
  dialogVisible.value = true
}

const parseIpList = (raw: string) =>
  raw
    .split(/[,，\s]+/)
    .map((s) => s.trim())
    .filter(Boolean)

const handleSubmit = async () => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(editingId.value ? '编辑商户需验证' : '新增商户需验证')
  } catch {
    return
  }
  const payload: any = {
    ...form.value,
    ip_whitelist: parseIpList(form.value.ip_whitelist),
    google_code: googleCode
  }
  if (!payload.login_password) {
    delete payload.login_password
  }
  try {
    if (editingId.value) {
      await updatePayMerchantApi(editingId.value, payload)
      ElMessage.success('更新成功')
    } else {
      const res = await createPayMerchantApi(payload)
      secretInfo.value = {
        api_key: res.data.api_key || res.data.merchant?.api_key,
        api_secret: res.data.api_secret
      }
      secretDialogVisible.value = true
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch {
    /* axios 已提示 */
  }
}

const handleResetSecret = async (row: any) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('重置密钥需验证')
  } catch {
    return
  }
  const res = await resetPayMerchantSecretApi(row.id, { google_code: googleCode })
  secretInfo.value = res.data
  secretDialogVisible.value = true
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="商户管理">
    <div class="mb-4">
      <ElButton type="primary" @click="openCreate">新增商户</ElButton>
    </div>
    <ElTable v-loading="loading" :data="list" border stripe>
      <ElTableColumn prop="merchant_no" label="商户号" width="120" />
      <ElTableColumn prop="name" label="名称" min-width="120" />
      <ElTableColumn prop="login_email" label="门户邮箱" min-width="180" show-overflow-tooltip />
      <ElTableColumn prop="api_key" label="API Key" min-width="200" show-overflow-tooltip />
      <ElTableColumn prop="deposit_fee_rate" label="入金费率" width="100" />
      <ElTableColumn prop="withdraw_fee_rate" label="出金费率" width="100" />
      <ElTableColumn label="状态" width="80">
        <template #default="{ row }">
          <ElTag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '禁用' }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <ElButton link type="primary" @click="openEdit(row)">编辑</ElButton>
          <ElButton link type="warning" @click="handleResetSecret(row)">重置密钥</ElButton>
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

    <ElDialog v-model="dialogVisible" :title="editingId ? '编辑商户' : '新增商户'" width="520px">
      <ElForm label-width="110px">
        <ElFormItem label="名称" required><ElInput v-model="form.name" /></ElFormItem>
        <ElFormItem label="门户邮箱"><ElInput v-model="form.login_email" placeholder="商户门户登录邮箱" /></ElFormItem>
        <ElFormItem label="门户密码">
          <ElInput
            v-model="form.login_password"
            type="password"
            show-password
            :placeholder="editingId ? '留空则不修改' : '至少 6 位'"
          />
        </ElFormItem>
        <ElFormItem label="回调地址"><ElInput v-model="form.notify_url" /></ElFormItem>
        <ElFormItem label="IP 白名单"><ElInput v-model="form.ip_whitelist" placeholder="逗号分隔，留空不限制" /></ElFormItem>
        <ElFormItem label="入金费率"><ElInput v-model="form.deposit_fee_rate" /></ElFormItem>
        <ElFormItem label="出金费率"><ElInput v-model="form.withdraw_fee_rate" /></ElFormItem>
        <ElFormItem label="自动审核上限"><ElInput v-model="form.auto_withdraw_max" /></ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="form.status">
            <ElOption :value="1" label="启用" />
            <ElOption :value="0" label="禁用" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="备注"><ElInput v-model="form.remark" type="textarea" /></ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" @click="handleSubmit">保存</ElButton>
      </template>
    </ElDialog>

    <ElDialog v-model="secretDialogVisible" title="API 密钥（仅显示一次）" width="520px">
      <ElAlert type="warning" :closable="false" class="mb-4" title="请立即保存 Secret，关闭后无法再次查看" />
      <p><strong>API Key:</strong> {{ secretInfo.api_key }}</p>
      <p class="mt-2"><strong>API Secret:</strong> {{ secretInfo.api_secret }}</p>
    </ElDialog>
  </ContentWrap>
</template>
