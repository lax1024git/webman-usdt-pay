<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  ElTable,
  ElTableColumn,
  ElInput,
  ElButton,
  ElPagination,
  ElDialog,
  ElForm,
  ElFormItem,
  ElSelect,
  ElOption,
  ElMessage,
  ElMessageBox,
  ElTag,
  ElInputNumber,
  ElSwitch
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import {
  getLangListApi,
  createLangApi,
  updateLangApi,
  deleteLangApi,
  getLangDetailApi
} from '@/api/lang'

const { t } = useI18n()

const switchEnabledLangs = ['zh-cn', 'en', 'ja', 'ko', 'id', 'ms']

const getDefaultSwitchEnabled = (lang: string) =>
  switchEnabledLangs.includes(String(lang || '').toLowerCase()) ? 1 : 0

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const queryParams = ref({ page: 1, limit: 20, keyword: '', status: '' })

const loadData = async () => {
  loading.value = true
  try {
    const params: Record<string, any> = { ...queryParams.value }
    Object.keys(params).forEach((k) => {
      if (params[k] === '') delete params[k]
    })
    const res = await getLangListApi(params)
    list.value = res.data?.items ?? []
    total.value = res.data?.total ?? 0
  } finally {
    loading.value = false
  }
}

const dialogVisible = ref(false)
const editId = ref(0)
const form = ref({
  title: '',
  lang: '',
  remark: '',
  is_default: 0,
  is_default_lang: 0,
  switch_enabled: 0,
  flag: '',
  status: 1,
  sort: 0
})

const openCreate = () => {
  editId.value = 0
  form.value = {
    title: '',
    lang: '',
    remark: '',
    is_default: 0,
    is_default_lang: 0,
    switch_enabled: 0,
    flag: '',
    status: 1,
    sort: 0
  }
  dialogVisible.value = true
}

const openEdit = async (row: any) => {
  editId.value = row.id
  const res = await getLangDetailApi(row.id)
  form.value = { ...res.data }
  dialogVisible.value = true
}

const handleSave = async () => {
  if (!form.value.title || !form.value.lang) {
    ElMessage.warning(t('请填写语言名称和代码'))
    return
  }
  if (!editId.value && form.value.switch_enabled === 0) {
    form.value.switch_enabled = getDefaultSwitchEnabled(form.value.lang)
  }
  if (editId.value) await updateLangApi(editId.value, form.value)
  else await createLangApi(form.value)
  ElMessage.success(t('保存成功'))
  dialogVisible.value = false
  loadData()
}

const handleDelete = (id: number) => {
  ElMessageBox.confirm(t('删除语言将同时删除其翻译明细，确认继续？'), t('提示'), {
    type: 'warning'
  }).then(async () => {
    await deleteLangApi(id)
    ElMessage.success(t('删除成功'))
    loadData()
  })
}

onMounted(loadData)
</script>

<template>
  <ContentWrap :title="t('语言')">
    <div class="mb-16px flex flex-wrap gap-12px items-center">
      <el-input
        v-model="queryParams.keyword"
        :placeholder="t('名称/代码')"
        style="width: 180px"
        clearable
      />
      <el-select
        v-model="queryParams.status"
        :placeholder="t('状态')"
        clearable
        style="width: 120px"
      >
        <el-option :label="t('启用')" :value="1" />
        <el-option :label="t('禁用')" :value="0" />
      </el-select>
      <el-button type="primary" @click="loadData">{{ t('查询') }}</el-button>
      <el-button type="success" @click="openCreate">{{ t('新增语言') }}</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="title" :label="t('名称')" min-width="120" />
      <el-table-column prop="lang" :label="t('代码')" width="100" />
      <el-table-column prop="remark" :label="t('备注')" min-width="140" show-overflow-tooltip />
      <el-table-column :label="t('默认语言')" width="100">
        <template #default="{ row }">
          <el-tag v-if="row.is_default_lang" type="success">{{ t('是') }}</el-tag>
          <span v-else>{{ t('否') }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('状态')" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status ? 'success' : 'info'">{{
            row.status ? t('启用') : t('禁用')
          }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('允许切换')" width="100">
        <template #default="{ row }">
          <el-tag :type="row.switch_enabled ? 'success' : 'info'">{{
            row.switch_enabled ? t('\u662f') : t('\u5426')
          }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="sort" :label="t('排序')" width="70" />
      <el-table-column :label="t('操作')" width="160" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">{{ t('编辑') }}</el-button>
          <el-button link type="danger" @click="handleDelete(row.id)">{{ t('删除') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      class="mt-16px"
      v-model:current-page="queryParams.page"
      v-model:page-size="queryParams.limit"
      :total="total"
      layout="total, prev, pager, next"
      @current-change="loadData"
    />

    <el-dialog
      v-model="dialogVisible"
      :title="editId ? t('编辑语言') : t('新增语言')"
      width="520px"
    >
      <el-form label-width="100px">
        <el-form-item :label="t('名称')" required>
          <el-input v-model="form.title" :placeholder="t('如 简体中文')" />
        </el-form-item>
        <el-form-item :label="t('代码')" required>
          <el-input v-model="form.lang" :placeholder="t('如 zh-cn / pt')" />
        </el-form-item>
        <el-form-item :label="t('备注')">
          <el-input
            v-model="form.remark"
            :placeholder="t('当前语言本地名称，如 英语→English')"
          />
          <div class="text-12px text-gray-400 mt-4px">
            {{ t('备注为该语言自身写法，例如：英语→English，日本→日本語') }}
          </div>
        </el-form-item>
        <el-form-item :label="t('默认语言')">
          <el-switch v-model="form.is_default_lang" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item :label="t('默认地区')">
          <el-switch v-model="form.is_default" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item :label="t('允许切换')">
          <el-switch v-model="form.switch_enabled" :active-value="1" :inactive-value="0" />
          <div class="text-12px text-gray-400 mt-4px">
            {{ t('关闭后用户端即使展示该语言也不会真实切换') }}
          </div>
        </el-form-item>
        <el-form-item :label="t('状态')">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item :label="t('排序')">
          <el-input-number v-model="form.sort" :min="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('取消') }}</el-button>
        <el-button type="primary" @click="handleSave">{{ t('保存') }}</el-button>
      </template>
    </el-dialog>
  </ContentWrap>
</template>
