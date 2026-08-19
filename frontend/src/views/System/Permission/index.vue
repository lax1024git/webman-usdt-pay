<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  ElMessage,
  ElMessageBox,
  ElTable,
  ElTableColumn,
  ElTag,
  ElButton,
  ElDialog,
  ElForm,
  ElFormItem,
  ElInput,
  ElSelect,
  ElOption,
  ElInputNumber,
  ElSwitch
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import {
  getPermissionListApi,
  createPermissionApi,
  updatePermissionApi,
  deletePermissionApi
} from '@/api/permission'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

interface PermissionNode {
  id: number
  name: string
  slug: string
  type: string
  path?: string
  method?: string
  icon?: string
  component?: string
  sort?: number
  hidden?: number
  parent_id?: number
  children?: PermissionNode[]
}

const { t } = useI18n()

const loading = ref(false)
const treeData = ref<PermissionNode[]>([])
const dialogVisible = ref(false)
const editingId = ref<number | null>(null)

const defaultForm = () => ({
  name: '',
  slug: '',
  type: 'api' as 'menu' | 'api',
  parent_id: 0,
  path: '',
  method: 'GET',
  icon: '',
  component: '',
  sort: 0,
  hidden: 0
})

const form = ref(defaultForm())

const flattenTree = (nodes: PermissionNode[], result: PermissionNode[] = []): PermissionNode[] => {
  for (const node of nodes) {
    result.push(node)
    if (node.children?.length) {
      flattenTree(node.children, result)
    }
  }
  return result
}

const parentOptions = computed(() => {
  return flattenTree(treeData.value).filter((item) => item.type === 'menu')
})

const resetForm = () => {
  editingId.value = null
  form.value = defaultForm()
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getPermissionListApi()
    treeData.value = res.data ?? []
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  resetForm()
  dialogVisible.value = true
}

const openEdit = (row: PermissionNode) => {
  editingId.value = row.id
  form.value = {
    name: row.name,
    slug: row.slug,
    type: row.type as 'menu' | 'api',
    parent_id: row.parent_id ?? 0,
    path: row.path || '',
    method: row.method || 'GET',
    icon: row.icon || '',
    component: row.component || '',
    sort: row.sort ?? 0,
    hidden: row.hidden ?? 0
  }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!form.value.name.trim()) {
    ElMessage.warning(t('名称不能为空'))
    return
  }
  if (!editingId.value && !form.value.slug.trim()) {
    ElMessage.warning(t('标识不能为空'))
    return
  }

  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('保存权限需验证'))
  } catch {
    return
  }

  try {
    if (editingId.value) {
      await updatePermissionApi(editingId.value, {
        name: form.value.name,
        path: form.value.path,
        method: form.value.type === 'api' ? form.value.method : '',
        icon: form.value.icon,
        component: form.value.component,
        sort: form.value.sort,
        hidden: form.value.hidden,
        google_code: googleCode
      })
      ElMessage.success(t('更新成功'))
    } else {
      await createPermissionApi({
        ...form.value,
        method: form.value.type === 'api' ? form.value.method : '',
        google_code: googleCode
      })
      ElMessage.success(t('创建成功'))
    }
    dialogVisible.value = false
    resetForm()
    loadData()
  } catch {
    ElMessage.error(editingId.value ? t('更新失败') : t('创建失败'))
  }
}

const handleDelete = async (row: PermissionNode) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('删除权限需验证'))
  } catch {
    return
  }
  try {
    await ElMessageBox.confirm(t('确认删除权限「{name}」吗？', { name: row.name }), t('提示'), {
      type: 'warning'
    })
  } catch {
    return
  }
  await deletePermissionApi(row.id, { google_code: googleCode })
  ElMessage.success(t('删除成功'))
  loadData()
}

onMounted(loadData)
</script>

<template>
  <ContentWrap :title="t('权限列表')">
    <ElButton v-permission="'permission:create'" type="primary" class="mb-16px" @click="openCreate">
      {{ t('新建权限') }}
    </ElButton>

    <ElTable
      :data="treeData"
      v-loading="loading"
      border
      row-key="id"
      default-expand-all
      :tree-props="{ children: 'children' }"
    >
      <ElTableColumn prop="name" :label="t('名称')" min-width="160" />
      <ElTableColumn prop="slug" :label="t('标识')" min-width="160" />
      <ElTableColumn prop="type" :label="t('类型')" width="100">
        <template #default="scope">
          <ElTag v-if="scope?.row" :type="scope.row.type === 'menu' ? 'success' : 'info'">
            {{ scope.row.type === 'menu' ? t('菜单') : t('接口') }}
          </ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="path" :label="t('路径')" min-width="200" />
      <ElTableColumn prop="method" :label="t('方法')" width="100" />
      <ElTableColumn prop="sort" :label="t('排序')" width="80" />
      <ElTableColumn :label="t('操作')" width="160" fixed="right">
        <template #default="scope">
          <ElButton
            v-if="scope?.row"
            v-permission="'permission:update'"
            type="primary"
            size="small"
            link
            @click="openEdit(scope.row)"
          >
            {{ t('编辑') }}
          </ElButton>
          <ElButton
            v-if="scope?.row"
            v-permission="'permission:delete'"
            type="danger"
            size="small"
            link
            @click="handleDelete(scope.row)"
          >
            {{ t('删除') }}
          </ElButton>
        </template>
      </ElTableColumn>
    </ElTable>

    <ElDialog
      v-model="dialogVisible"
      :title="editingId ? t('编辑权限') : t('新建权限')"
      width="520px"
      @closed="resetForm"
    >
      <ElForm :model="form" label-width="90px">
        <ElFormItem :label="t('名称')" required>
          <ElInput v-model="form.name" :placeholder="t('权限名称')" />
        </ElFormItem>
        <ElFormItem v-if="!editingId" :label="t('标识')" required>
          <ElInput v-model="form.slug" :placeholder="t('唯一标识，如 article:list')" />
        </ElFormItem>
        <ElFormItem v-else :label="t('标识')">
          <ElInput v-model="form.slug" disabled />
        </ElFormItem>
        <ElFormItem v-if="!editingId" :label="t('类型')" required>
          <ElSelect v-model="form.type" style="width: 100%">
            <ElOption :label="t('菜单')" value="menu" />
            <ElOption :label="t('接口')" value="api" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem v-if="!editingId" :label="t('上级')">
          <ElSelect v-model="form.parent_id" style="width: 100%">
            <ElOption :label="t('无（顶级）')" :value="0" />
            <ElOption
              v-for="item in parentOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem :label="t('路径')">
          <ElInput v-model="form.path" :placeholder="t('路由或 API 路径')" />
        </ElFormItem>
        <ElFormItem v-if="form.type === 'api'" :label="t('方法')">
          <ElSelect v-model="form.method" style="width: 100%">
            <ElOption label="GET" value="GET" />
            <ElOption label="POST" value="POST" />
            <ElOption label="PUT" value="PUT" />
            <ElOption label="DELETE" value="DELETE" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem v-if="form.type === 'menu'" :label="t('图标')">
          <ElInput v-model="form.icon" :placeholder="t('菜单图标')" />
        </ElFormItem>
        <ElFormItem v-if="form.type === 'menu'" :label="t('组件')">
          <ElInput v-model="form.component" :placeholder="t('如 views/article/index')" />
        </ElFormItem>
        <ElFormItem :label="t('排序')">
          <ElInputNumber v-model="form.sort" :min="0" />
        </ElFormItem>
        <ElFormItem v-if="form.type === 'menu'" :label="t('隐藏')">
          <ElSwitch v-model="form.hidden" :active-value="1" :inactive-value="0" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">{{ t('取消') }}</ElButton>
        <ElButton type="primary" @click="handleSubmit">{{ t('确定') }}</ElButton>
      </template>
    </ElDialog>
  </ContentWrap>
</template>
