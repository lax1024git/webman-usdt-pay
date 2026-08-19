<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  ElMessage,
  ElMessageBox,
  ElTable,
  ElTableColumn,
  ElInput,
  ElButton,
  ElTag,
  ElPagination,
  ElDialog,
  ElForm,
  ElFormItem,
  ElSelect,
  ElOption,
  ElAlert
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import {
  getAdminListApi,
  createAdminApi,
  updateAdminApi,
  deleteAdminApi,
  updateAdminPasswordApi
} from '@/api/admin'
import { getRoleListApi } from '@/api/role'
import { promptGoogleAuthCode } from '@/utils/googleAuthPrompt'

const { t } = useI18n()

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const roles = ref<{ id: number; name: string }[]>([])
const queryParams = ref({ page: 1, limit: 20, keyword: '' })

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
  username: '',
  password: '',
  nickname: '',
  status: 1,
  role_ids: [] as number[]
})

const pwdDialogVisible = ref(false)
const pwdTarget = ref<{ id: number; username: string } | null>(null)
const pwdForm = ref({ new_password: '', confirm_password: '' })

const resetForm = () => {
  editingId.value = null
  form.value = { username: '', password: '', nickname: '', status: 1, role_ids: [] }
}

const isCreate = () => editingId.value === null

const loadRoles = async () => {
  const res = await getRoleListApi({ page: 1, limit: 100 })
  roles.value = res.data?.items ?? []
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getAdminListApi(queryParams.value)
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
    username: row.username,
    password: '',
    nickname: row.nickname || '',
    status: row.status,
    role_ids: (row.roles || []).map((r: any) => r.id)
  }
  dialogVisible.value = true
}

const openResetPassword = (row: any) => {
  pwdTarget.value = { id: row.id, username: row.username }
  pwdForm.value = { new_password: '', confirm_password: '' }
  pwdDialogVisible.value = true
}

const handleSubmit = async () => {
  if (!editingId.value && (!form.value.username.trim() || !form.value.password.trim())) {
    ElMessage.warning(t('用户名和密码不能为空'))
    return
  }
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(
      editingId.value ? t('编辑管理员需验证') : t('新增管理员需验证')
    )
  } catch {
    return
  }
  try {
    if (editingId.value) {
      await updateAdminApi(editingId.value, {
        nickname: form.value.nickname,
        status: form.value.status,
        role_ids: form.value.role_ids,
        google_code: googleCode
      })
      ElMessage.success(t('更新成功'))
    } else {
      await createAdminApi({ ...form.value, google_code: googleCode })
      ElMessage.success(t('创建成功'))
    }
    dialogVisible.value = false
    resetForm()
    loadData()
  } catch {
    ElMessage.error(editingId.value ? t('更新失败') : t('创建失败'))
  }
}

const handleResetPassword = async () => {
  const pwd = pwdForm.value.new_password.trim()
  const confirm = pwdForm.value.confirm_password.trim()
  if (pwd.length < 6) {
    ElMessage.warning(t('新密码至少 6 位'))
    return
  }
  if (pwd !== confirm) {
    ElMessage.warning(t('两次输入的密码不一致'))
    return
  }
  if (!pwdTarget.value) return
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('重置管理员密码需验证'))
  } catch {
    return
  }
  try {
    await updateAdminPasswordApi(pwdTarget.value.id, {
      new_password: pwd,
      google_code: googleCode
    })
    ElMessage.success(t('密码已重置，对方需重新登录'))
    pwdDialogVisible.value = false
    pwdTarget.value = null
  } catch {
    ElMessage.error(t('重置密码失败'))
  }
}

const handleDelete = async (id: number) => {
  let googleCode = ''
  try {
    googleCode = await promptGoogleAuthCode(t('删除管理员需验证'))
  } catch {
    return
  }
  try {
    await ElMessageBox.confirm(t('确认删除该管理员吗？'), t('提示'), { type: 'warning' })
  } catch {
    return
  }
  await deleteAdminApi(id, { google_code: googleCode })
  ElMessage.success(t('删除成功'))
  loadData()
}

onMounted(() => {
  loadRoles()
  loadData()
})
</script>

<template>
  <ContentWrap :title="t('管理员')">
    <div class="mb-16px">
      <el-button v-permission="'admin:create'" type="primary" class="mr-12px" @click="openCreate">
        {{ t('新建管理员') }}
      </el-button>
      <el-input
        v-model="queryParams.keyword"
        :placeholder="t('搜索用户名')"
        clearable
        style="width: 200px"
        class="mr-12px"
      />
      <el-button type="primary" @click="loadData">{{ t('搜索') }}</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="username" :label="t('用户名')" />
      <el-table-column prop="nickname" :label="t('昵称')" />
      <el-table-column prop="status" :label="t('状态')" width="100">
        <template #default="scope">
          <el-tag v-if="scope?.row" :type="scope.row.status === 1 ? 'success' : 'danger'">{{
            scope.row.status === 1 ? t('启用') : t('禁用')
          }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('角色')">
        <template #default="scope">
          <el-tag v-for="role in scope?.row?.roles || []" :key="role.id" class="mr-4px">{{
            role.name
          }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('谷歌验证')" width="100">
        <template #default="scope">
          <el-tag v-if="scope?.row" :type="scope.row.google_auth_bound ? 'success' : 'info'">
            {{ scope.row.google_auth_bound ? t('已绑定') : t('未绑定') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('操作')" width="220">
        <template #default="scope">
          <el-button
            v-if="scope?.row"
            v-permission="'admin:update'"
            type="primary"
            size="small"
            link
            @click="openEdit(scope.row)"
          >
            {{ t('编辑') }}
          </el-button>
          <el-button
            v-if="scope?.row"
            v-permission="'admin:update'"
            type="warning"
            size="small"
            link
            @click="openResetPassword(scope.row)"
          >
            {{ t('重置密码') }}
          </el-button>
          <el-button
            v-if="scope?.row"
            v-permission="'admin:delete'"
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
      :title="editingId ? t('编辑管理员') : t('新建管理员')"
      width="480px"
      @closed="resetForm"
    >
      <el-form :model="form" label-width="80px">
        <el-form-item v-if="isCreate()" :label="t('用户名')" required>
          <el-input v-model="form.username" :placeholder="t('登录用户名')" />
        </el-form-item>
        <el-form-item v-if="isCreate()" :label="t('密码')" required>
          <el-input
            v-model="form.password"
            type="password"
            :placeholder="t('登录密码')"
            show-password
          />
        </el-form-item>
        <el-form-item :label="t('昵称')">
          <el-input v-model="form.nickname" :placeholder="t('显示昵称')" />
        </el-form-item>
        <el-form-item :label="t('状态')">
          <el-select v-model="form.status" style="width: 100%">
            <el-option :label="t('启用')" :value="1" />
            <el-option :label="t('禁用')" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('角色')">
          <el-select
            v-model="form.role_ids"
            multiple
            :placeholder="t('选择角色')"
            style="width: 100%"
          >
            <el-option v-for="role in roles" :key="role.id" :label="role.name" :value="role.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('取消') }}</el-button>
        <el-button type="primary" @click="handleSubmit">{{ t('确定') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="pwdDialogVisible"
      :title="`${t('重置密码')}：${pwdTarget?.username || ''}`"
      width="440px"
    >
      <el-alert
        type="warning"
        :closable="false"
        show-icon
        class="mb-16px"
        :title="t('重置后对方当前登录会失效，需用新密码重新登录')"
      />
      <el-form :model="pwdForm" label-width="90px">
        <el-form-item :label="t('新密码')" required>
          <el-input
            v-model="pwdForm.new_password"
            type="password"
            :placeholder="t('至少 6 位')"
            show-password
          />
        </el-form-item>
        <el-form-item :label="t('确认密码')" required>
          <el-input
            v-model="pwdForm.confirm_password"
            type="password"
            :placeholder="t('再次输入新密码')"
            show-password
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="pwdDialogVisible = false">{{ t('取消') }}</el-button>
        <el-button type="primary" @click="handleResetPassword">{{ t('确定重置') }}</el-button>
      </template>
    </el-dialog>
  </ContentWrap>
</template>
