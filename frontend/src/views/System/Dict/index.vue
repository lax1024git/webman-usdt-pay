<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  ElMessage,
  ElMessageBox,
  ElTable,
  ElTableColumn,
  ElInput,
  ElButton,
  ElPagination,
  ElDialog,
  ElForm,
  ElFormItem,
  ElSwitch,
  ElTag
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import {
  getDictListApi,
  createDictApi,
  updateDictApi,
  deleteDictApi,
  getDictItemsApi,
  saveDictItemsApi
} from '@/api/dict'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

const { t } = useI18n()

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, keyword: '' })

const dialogVisible = ref(false)
const itemDialogVisible = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ name: '', code: '', status: 1, remark: '' })
const dictItems = ref<any[]>([])
const currentTypeId = ref<number | null>(null)

const loadData = async () => {
  loading.value = true
  try {
    const res = await getDictListApi(queryParams.value)
    list.value = res.data?.items || []
    total.value = res.data?.total || 0
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  editingId.value = null
  form.value = { name: '', code: '', status: 1, remark: '' }
  dialogVisible.value = true
}

const openEdit = (row: any) => {
  editingId.value = row.id
  form.value = {
    name: row.name,
    code: row.code,
    status: Number(row.status) === 1 ? 1 : 0,
    remark: row.remark || ''
  }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!form.value.name || !form.value.code) {
    ElMessage.warning(t('名称和编码不能为空'))
    return
  }
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('保存字典需验证'))
  } catch {
    return
  }
  if (editingId.value) {
    await updateDictApi(editingId.value, { ...form.value, google_code: googleCode })
    ElMessage.success(t('更新成功'))
  } else {
    await createDictApi({ ...form.value, google_code: googleCode })
    ElMessage.success(t('创建成功'))
  }
  dialogVisible.value = false
  loadData()
}

const handleDelete = async (row: any) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('删除字典需验证'))
  } catch {
    return
  }
  try {
    await ElMessageBox.confirm(t('确定删除该字典类型？'), t('提示'), { type: 'warning' })
  } catch {
    return
  }
  await deleteDictApi(row.id, { google_code: googleCode })
  ElMessage.success(t('删除成功'))
  loadData()
}

const openItems = async (row: any) => {
  currentTypeId.value = row.id
  const res = await getDictItemsApi(row.id)
  dictItems.value = (res.data || []).map((item: any, index: number) => ({
    label: item.label,
    value: item.value,
    sort: item.sort ?? index,
    status: Number(item.status) === 1 ? 1 : 0,
    remark: item.remark || ''
  }))
  if (dictItems.value.length === 0) {
    dictItems.value.push({ label: '', value: '', sort: 0, status: 1, remark: '' })
  }
  itemDialogVisible.value = true
}

const addItemRow = () => {
  dictItems.value.push({
    label: '',
    value: '',
    sort: dictItems.value.length,
    status: 1,
    remark: ''
  })
}

const removeItemRow = (index: number) => {
  dictItems.value.splice(index, 1)
  if (dictItems.value.length === 0) {
    addItemRow()
  }
}

const saveItems = async () => {
  if (!currentTypeId.value) return
  const items = dictItems.value
    .map((item, index) => ({
      ...item,
      label: String(item.label ?? '').trim(),
      value: String(item.value ?? '').trim(),
      sort: Number(item.sort ?? index)
    }))
    .filter((item) => item.label !== '' || item.value !== '')

  const invalid = items.find((item) => item.label === '' || item.value === '')
  if (invalid) {
    ElMessage.warning(t('字典项的标签和值不能为空'))
    return
  }

  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('保存字典项需验证'))
  } catch {
    return
  }

  await saveDictItemsApi(currentTypeId.value, items, { google_code: googleCode })
  ElMessage.success(t('保存成功'))
  itemDialogVisible.value = false
}

onMounted(loadData)
</script>

<template>
  <ContentWrap>
    <div class="mb-4 flex gap-2">
      <ElInput v-model="queryParams.keyword" :placeholder="t('搜索名称/编码')" clearable />
      <ElButton type="primary" @click="loadData">{{ t('搜索') }}</ElButton>
      <ElButton type="success" @click="openCreate">{{ t('新增字典') }}</ElButton>
    </div>

    <ElTable v-loading="loading" :data="list" border>
      <ElTableColumn prop="id" label="ID" width="80" />
      <ElTableColumn prop="name" :label="t('名称')" />
      <ElTableColumn prop="code" :label="t('编码')" />
      <ElTableColumn :label="t('状态')" width="100">
        <template #default="{ row }">
          <ElTag :type="row.status === 1 ? 'success' : 'info'">
            {{ row.status === 1 ? t('启用') : t('禁用') }}
          </ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="remark" :label="t('备注')" />
      <ElTableColumn :label="t('操作')" width="220">
        <template #default="{ row }">
          <ElButton link type="primary" @click="openEdit(row)">{{ t('编辑') }}</ElButton>
          <ElButton link type="primary" @click="openItems(row)">{{ t('字典项') }}</ElButton>
          <ElButton link type="danger" @click="handleDelete(row)">{{ t('删除') }}</ElButton>
        </template>
      </ElTableColumn>
    </ElTable>

    <div class="mt-4 flex justify-end">
      <ElPagination
        v-model:current-page="queryParams.page"
        v-model:page-size="queryParams.limit"
        :total="total"
        layout="total, prev, pager, next"
        @current-change="loadData"
      />
    </div>

    <ElDialog
      v-model="dialogVisible"
      :title="editingId ? t('编辑字典') : t('新增字典')"
      width="520px"
    >
      <ElForm label-width="90px">
        <ElFormItem :label="t('名称')">
          <ElInput v-model="form.name" />
        </ElFormItem>
        <ElFormItem :label="t('编码')">
          <ElInput v-model="form.code" :disabled="!!editingId" />
        </ElFormItem>
        <ElFormItem :label="t('状态')">
          <ElSwitch v-model="form.status" :active-value="1" :inactive-value="0" />
        </ElFormItem>
        <ElFormItem :label="t('备注')">
          <ElInput v-model="form.remark" type="textarea" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">{{ t('取消') }}</ElButton>
        <ElButton type="primary" @click="handleSubmit">{{ t('确定') }}</ElButton>
      </template>
    </ElDialog>

    <ElDialog v-model="itemDialogVisible" :title="t('字典项管理')" width="760px">
      <ElButton class="mb-3" type="primary" @click="addItemRow">{{ t('新增项') }}</ElButton>
      <ElTable :data="dictItems" border>
        <ElTableColumn :label="t('标签')">
          <template #default="{ row }"><ElInput v-model="row.label" /></template>
        </ElTableColumn>
        <ElTableColumn :label="t('值')">
          <template #default="{ row }"><ElInput v-model="row.value" /></template>
        </ElTableColumn>
        <ElTableColumn :label="t('排序')" width="100">
          <template #default="{ row }"><ElInput v-model.number="row.sort" /></template>
        </ElTableColumn>
        <ElTableColumn :label="t('状态')" width="100">
          <template #default="{ row }">
            <ElSwitch v-model="row.status" :active-value="1" :inactive-value="0" />
          </template>
        </ElTableColumn>
        <ElTableColumn :label="t('操作')" width="90" fixed="right">
          <template #default="{ $index }">
            <ElButton link type="danger" @click="removeItemRow($index)">{{ t('删除') }}</ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
      <template #footer>
        <ElButton @click="itemDialogVisible = false">{{ t('取消') }}</ElButton>
        <ElButton type="primary" @click="saveItems">{{ t('保存') }}</ElButton>
      </template>
    </ElDialog>
  </ContentWrap>
</template>
