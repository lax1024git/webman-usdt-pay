<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  ElButton,
  ElDialog,
  ElForm,
  ElFormItem,
  ElInput,
  ElMessage,
  ElPagination,
  ElPopconfirm,
  ElSelect,
  ElOption,
  ElTable,
  ElTableColumn,
  ElTag
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { createPayBlacklistApi, deletePayBlacklistApi, getPayBlacklistListApi } from '@/api/pay'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

const loading = ref(false)
const total = ref(0)
const list = ref<any[]>([])
const dialogVisible = ref(false)
const queryParams = ref({ page: 1, limit: 20, chain: 'TRC20', address: '' })
const form = ref({ chain: 'TRC20', address: '', reason: '' })

const loadData = async () => {
  loading.value = true
  try {
    const res = await getPayBlacklistListApi(queryParams.value)
    list.value = res.data.items || []
    total.value = res.data.total || 0
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  form.value = { chain: queryParams.value.chain || 'TRC20', address: '', reason: '' }
  dialogVisible.value = true
}

const handleCreate = async () => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('添加黑名单需验证')
  } catch {
    return
  }
  await createPayBlacklistApi({ ...form.value, google_code: googleCode })
  ElMessage.success('添加成功')
  dialogVisible.value = false
  loadData()
}

const handleDelete = async (row: any) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode('删除黑名单需验证')
  } catch {
    return
  }
  await deletePayBlacklistApi(row.id, { google_code: googleCode })
  ElMessage.success('删除成功')
  loadData()
}

onMounted(loadData)
</script>

<template>
  <ContentWrap title="地址黑名单" message="命中黑名单的提币地址将被风控拦截。">
    <div class="mb-4 flex gap-2">
      <ElSelect v-model="queryParams.chain" style="width: 120px">
        <ElOption label="TRC20" value="TRC20" />
        <ElOption label="ERC20" value="ERC20" />
        <ElOption label="BEP20" value="BEP20" />
      </ElSelect>
      <ElInput v-model="queryParams.address" placeholder="地址搜索" clearable style="width: 280px" />
      <ElButton type="primary" @click="loadData">查询</ElButton>
      <ElButton type="success" @click="openCreate">新增地址</ElButton>
    </div>

    <ElTable v-loading="loading" :data="list" border stripe>
      <ElTableColumn prop="id" label="ID" width="80" />
      <ElTableColumn prop="chain" label="链类型" width="100">
        <template #default="{ row }">
          <ElTag>{{ row.chain }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="address" label="地址" min-width="260" show-overflow-tooltip />
      <ElTableColumn prop="reason" label="原因" min-width="180" show-overflow-tooltip />
      <ElTableColumn prop="created_at" label="创建时间" width="180" />
      <ElTableColumn label="操作" width="120" fixed="right">
        <template #default="{ row }">
          <ElPopconfirm title="确认删除该黑名单地址？" @confirm="handleDelete(row)">
            <template #reference>
              <ElButton link type="danger">删除</ElButton>
            </template>
          </ElPopconfirm>
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

    <ElDialog v-model="dialogVisible" title="新增黑名单地址" width="520px">
      <ElForm label-width="100px">
        <ElFormItem label="链类型">
          <ElSelect v-model="form.chain" style="width: 100%">
            <ElOption label="TRC20" value="TRC20" />
            <ElOption label="ERC20" value="ERC20" />
            <ElOption label="BEP20" value="BEP20" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="地址" required>
          <ElInput v-model="form.address" type="textarea" :rows="3" />
        </ElFormItem>
        <ElFormItem label="原因">
          <ElInput v-model="form.reason" type="textarea" :rows="3" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" @click="handleCreate">确认</ElButton>
      </template>
    </ElDialog>
  </ContentWrap>
</template>
