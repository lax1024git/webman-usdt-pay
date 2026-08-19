<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import {
  ElMessage,
  ElMessageBox,
  ElTable,
  ElTableColumn,
  ElButton,
  ElPagination,
  ElDialog,
  ElForm,
  ElFormItem,
  ElInput,
  ElTree,
  ElTag,
  ElSelect,
  ElOption
} from 'element-plus'
import type { ElTree as ElTreeType } from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import {
  getRoleListApi,
  createRoleApi,
  updateRoleApi,
  deleteRoleApi,
  getRolePermissionsApi,
  assignRolePermissionsApi
} from '@/api/role'
import { getPermissionListApi } from '@/api/permission'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

interface PermissionNode {
  id: number
  name: string
  slug: string
  type: string
  children?: PermissionNode[]
}

const { t } = useI18n()

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, keyword: '' })

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ name: '', slug: '', description: '', data_scope: 'self' })

const assignDialogVisible = ref(false)
const assignLoading = ref(false)
const assignSubmitting = ref(false)
const assigningRole = ref<{ id: number; name: string; slug: string } | null>(null)
const permissionTree = ref<PermissionNode[]>([])
const permissionTreeRef = ref<InstanceType<typeof ElTreeType>>()
const pendingCheckedLeafIds = ref<number[]>([])

const resetForm = () => {
  editingId.value = null
  form.value = { name: '', slug: '', description: '', data_scope: 'self' }
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getRoleListApi(queryParams.value)
    list.value = res.data.items
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  resetForm()
  dialogVisible.value = true
}

const openEdit = (row: any) => {
  editingId.value = row.id
  form.value = {
    name: row.name,
    slug: row.slug,
    description: row.description || '',
    data_scope: row.data_scope || 'self'
  }
  dialogVisible.value = true
}

/** 从已授权 id 中筛出树里的叶子节点，供回显勾选 */
const collectLeafPermissionIds = (nodes: PermissionNode[], grantedIds: number[]): number[] => {
  const granted = new Set(grantedIds.filter((id) => Number.isFinite(id) && id > 0))
  const leaves: number[] = []

  const walk = (list: PermissionNode[]) => {
    list.forEach((node) => {
      const children = node.children || []
      if (children.length > 0) {
        walk(children)
        return
      }
      if (granted.has(Number(node.id))) {
        leaves.push(Number(node.id))
      }
    })
  }

  walk(nodes)
  return leaves
}

const applyCheckedLeafIds = async (leafIds: number[]) => {
  pendingCheckedLeafIds.value = leafIds
  await nextTick()
  await nextTick()
  permissionTreeRef.value?.setCheckedKeys(leafIds, true)
}

const openAssign = async (row: any) => {
  if (row.slug === 'super_admin') {
    ElMessage.warning(t('超级管理员拥有全部权限，无需分配'))
    return
  }

  assigningRole.value = { id: row.id, name: row.name, slug: row.slug }
  permissionTree.value = []
  pendingCheckedLeafIds.value = []
  assignDialogVisible.value = true
  assignLoading.value = true

  try {
    // 每次打开都重新拉权限树，避免菜单同步后仍用旧缓存
    const [permissionRes, rolePermRes] = await Promise.all([
      getPermissionListApi(),
      getRolePermissionsApi(row.id)
    ])

    permissionTree.value = permissionRes.data || []
    const leafIds = collectLeafPermissionIds(
      permissionTree.value,
      (rolePermRes.data?.permission_ids || []).map((id: number | string) => Number(id))
    )

    assignLoading.value = false
    // leafOnly=true：只勾叶子；父节点由 ElTree 根据子节点自动半选/全选
    // 若把已保存的父节点 id 直接 setCheckedKeys(..., false)，会把整棵子树误勾全选
    await applyCheckedLeafIds(leafIds)
  } catch {
    ElMessage.error(t('加载权限数据失败'))
    assignDialogVisible.value = false
    assigningRole.value = null
    assignLoading.value = false
  }
}

const resetAssign = () => {
  assigningRole.value = null
  permissionTree.value = []
  pendingCheckedLeafIds.value = []
  permissionTreeRef.value?.setCheckedKeys([], false)
}

const handleAssignSubmit = async () => {
  if (!assigningRole.value) {
    return
  }

  // 已勾选节点（含全选父级）+ 半选父级（菜单需入库才能出现在侧栏）
  const checkedKeys = (permissionTreeRef.value?.getCheckedKeys(false) || []) as Array<
    string | number
  >
  const halfCheckedKeys = (permissionTreeRef.value?.getHalfCheckedKeys() || []) as Array<
    string | number
  >
  const permissionIds = [
    ...new Set([...checkedKeys, ...halfCheckedKeys].map((id) => Number(id)).filter((id) => id > 0))
  ]

  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('角色授权需验证'))
  } catch {
    return
  }

  assignSubmitting.value = true
  try {
    await assignRolePermissionsApi(assigningRole.value.id, permissionIds, {
      google_code: googleCode
    })
    ElMessage.success(t('授权成功'))
    assignDialogVisible.value = false
    resetAssign()
  } catch {
    ElMessage.error(t('授权失败'))
  } finally {
    assignSubmitting.value = false
  }
}

const handleSubmit = async () => {
  if (!form.value.name.trim()) {
    ElMessage.warning(t('角色名称不能为空'))
    return
  }
  if (!editingId.value && !form.value.slug.trim()) {
    ElMessage.warning(t('角色标识不能为空'))
    return
  }
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(
      editingId.value ? t('编辑角色需验证') : t('新增角色需验证')
    )
  } catch {
    return
  }
  try {
    if (editingId.value) {
      await updateRoleApi(editingId.value, {
        name: form.value.name,
        description: form.value.description,
        data_scope: form.value.data_scope,
        google_code: googleCode
      })
      ElMessage.success(t('更新成功'))
    } else {
      await createRoleApi({ ...form.value, google_code: googleCode })
      ElMessage.success(t('创建成功'))
    }
    dialogVisible.value = false
    resetForm()
    loadData()
  } catch {
    ElMessage.error(editingId.value ? t('更新失败') : t('创建失败'))
  }
}

const handleDelete = async (id: number) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('删除角色需验证'))
  } catch {
    return
  }
  try {
    await ElMessageBox.confirm(t('确认删除该角色吗？'), t('提示'), { type: 'warning' })
  } catch {
    return
  }
  await deleteRoleApi(id, { google_code: googleCode })
  ElMessage.success(t('删除成功'))
  loadData()
}

const permissionTypeLabel = (type: string) => {
  if (type === 'menu') return t('菜单')
  if (type === 'button') return t('按钮')
  return t('接口')
}

const permissionTypeTag = (type: string) => {
  if (type === 'menu') return 'success'
  if (type === 'button') return 'warning'
  return 'info'
}

onMounted(loadData)
</script>

<template>
  <ContentWrap :title="t('角色')">
    <div class="mb-16px">
      <el-button v-permission="'role:create'" type="primary" class="mr-12px" @click="openCreate">
        {{ t('新建角色') }}
      </el-button>
      <el-input
        v-model="queryParams.keyword"
        :placeholder="t('搜索角色')"
        clearable
        style="width: 200px"
        class="mr-12px"
      />
      <el-button type="primary" @click="loadData">{{ t('搜索') }}</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" :label="t('角色名称')" />
      <el-table-column prop="slug" :label="t('标识')" />
      <el-table-column prop="data_scope" :label="t('数据权限')" width="120">
        <template #default="scope">
          <el-tag v-if="scope?.row?.data_scope === 'all'" type="success">{{ t('全部') }}</el-tag>
          <el-tag v-else type="info">{{ t('仅本人') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="description" :label="t('描述')" />
      <el-table-column :label="t('操作')" width="220">
        <template #default="scope">
          <el-button
            v-if="scope?.row"
            v-permission="'role:assign'"
            type="success"
            size="small"
            link
            :disabled="scope.row.slug === 'super_admin'"
            @click="openAssign(scope.row)"
          >
            {{ t('授权') }}
          </el-button>
          <el-button
            v-if="scope?.row"
            v-permission="'role:update'"
            type="primary"
            size="small"
            link
            @click="openEdit(scope.row)"
          >
            {{ t('编辑') }}
          </el-button>
          <el-button
            v-if="scope?.row"
            v-permission="'role:delete'"
            type="danger"
            size="small"
            link
            @click="handleDelete(scope.row.id)"
          >
            {{ t('删除') }}
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      class="mt-16px"
      :total="total"
      v-model:page-size="queryParams.limit"
      v-model:current-page="queryParams.page"
      layout="total, prev, pager, next"
      @change="loadData"
    />

    <el-dialog
      v-model="dialogVisible"
      :title="editingId ? t('编辑角色') : t('新建角色')"
      width="480px"
      @closed="resetForm"
    >
      <el-form :model="form" label-width="80px">
        <el-form-item :label="t('名称')" required>
          <el-input v-model="form.name" :placeholder="t('角色名称')" />
        </el-form-item>
        <el-form-item v-if="!editingId" :label="t('标识')" required>
          <el-input v-model="form.slug" :placeholder="t('唯一标识，如 editor')" />
        </el-form-item>
        <el-form-item v-else :label="t('标识')">
          <el-input v-model="form.slug" disabled />
        </el-form-item>
        <el-form-item :label="t('描述')">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            :placeholder="t('角色描述')"
          />
        </el-form-item>
        <el-form-item :label="t('数据权限')">
          <el-select v-model="form.data_scope" style="width: 100%">
            <el-option :label="t('全部数据')" value="all" />
            <el-option :label="t('仅本人')" value="self" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('取消') }}</el-button>
        <el-button type="primary" @click="handleSubmit">{{ t('确定') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="assignDialogVisible"
      :title="`${t('分配权限')} - ${assigningRole?.name || ''}`"
      width="720px"
      @closed="resetAssign"
    >
      <div v-loading="assignLoading" class="permission-tree-wrap">
        <el-tree
          v-if="!assignLoading && assigningRole"
          :key="`role-perm-tree-${assigningRole.id}`"
          ref="permissionTreeRef"
          :data="permissionTree"
          show-checkbox
          node-key="id"
          default-expand-all
          :props="{ label: 'name', children: 'children' }"
        >
          <template #default="{ data }">
            <span class="permission-tree-node">
              <span>{{ data.name }}</span>
              <el-tag size="small" :type="permissionTypeTag(data.type)" class="ml-8px">
                {{ permissionTypeLabel(data.type) }}
              </el-tag>
              <span v-if="data.slug" class="permission-tree-slug">{{ data.slug }}</span>
            </span>
          </template>
        </el-tree>
      </div>
      <template #footer>
        <el-button @click="assignDialogVisible = false">{{ t('取消') }}</el-button>
        <el-button type="primary" :loading="assignSubmitting" @click="handleAssignSubmit">
          {{ t('保存') }}
        </el-button>
      </template>
    </el-dialog>
  </ContentWrap>
</template>

<style scoped>
.permission-tree-wrap {
  max-height: 480px;
  overflow: auto;
  border: 1px solid var(--el-border-color-light);
  border-radius: 4px;
  padding: 12px;
}

.permission-tree-node {
  display: inline-flex;
  align-items: center;
}

.permission-tree-slug {
  margin-left: 8px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
}
</style>
