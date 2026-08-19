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
  ElMessage,
  ElMessageBox,
  ElSelect,
  ElOption,
  ElTag,
  ElUpload,
  ElSwitch
} from 'element-plus'
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'
import {
  getLangTextListApi,
  getLangTextDetailApi,
  saveLangTextApi,
  deleteLangTextApi,
  exportLangTextApi,
  translateLangTextApi,
  translateLangTextPreviewApi,
  importLangTextApi
} from '@/api/lang-text'

const { t } = useI18n()
const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const langs = ref<any[]>([])
const translatingId = ref(0)
const dialogTranslating = ref(false)
const typeOptions = ref<{ value: string; label: string }[]>([
  { value: 'front', label: '前端' },
  { value: 'admin', label: '后台' }
])
const queryParams = ref({ page: 1, limit: 20, keyword: '', type: '' })

const typeLabel = (type: string) => {
  if (type === 'admin') return t('后台')
  if (type === 'front') return t('前端')
  const hit = typeOptions.value.find((o) => o.value === type)
  return hit ? t(hit.label) : t('前端')
}

const loadData = async () => {
  loading.value = true
  try {
    const params: Record<string, any> = { ...queryParams.value }
    Object.keys(params).forEach((k) => {
      if (params[k] === '') delete params[k]
    })
    const res = await getLangTextListApi(params)
    list.value = res.data?.items ?? []
    total.value = res.data?.total ?? 0
    langs.value = res.data?.langs ?? []
    if (Array.isArray(res.data?.type_options) && res.data.type_options.length) {
      typeOptions.value = res.data.type_options
    }
  } finally {
    loading.value = false
  }
}

const dialogVisible = ref(false)
const editId = ref(0)
const form = ref({ title: '', type: 'front', texts: {} as Record<string, string> })

const openCreate = () => {
  editId.value = 0
  const texts: Record<string, string> = {}
  langs.value.forEach((l) => {
    texts[l.lang] = ''
  })
  form.value = {
    title: '',
    type: queryParams.value.type || 'front',
    texts
  }
  dialogVisible.value = true
}

const openEdit = async (row: any) => {
  editId.value = row.id
  const res = await getLangTextDetailApi(row.id)
  form.value = {
    title: res.data.title ?? '',
    type: res.data.type || 'front',
    texts: { ...(res.data.texts ?? {}) }
  }
  dialogVisible.value = true
}

const handleSave = async () => {
  if (!form.value.title.trim()) {
    ElMessage.warning(t('请填写文案键'))
    return
  }
  await saveLangTextApi({ id: editId.value || undefined, ...form.value })
  ElMessage.success(t('保存成功'))
  dialogVisible.value = false
  loadData()
}

const handleDelete = (id: number) => {
  ElMessageBox.confirm(t('确认删除该文案吗？'), t('提示'), { type: 'warning' }).then(async () => {
    await deleteLangTextApi(id)
    ElMessage.success(t('删除成功'))
    loadData()
  })
}

const handleExport = async () => {
  const res = await exportLangTextApi()
  ElMessage.success(t('已导出语言文件', { n: res.data?.files ?? 0 }))
}

const handleTranslate = async (row: any, overwrite = 0) => {
  if (!row?.id || translatingId.value) return
  translatingId.value = row.id
  try {
    const res = await translateLangTextApi(row.id, { overwrite })
    const n = res.data?.translated?.length ?? 0
    ElMessage.success(n > 0 ? t('已翻译几种语言', { n }) : t('无需翻译'))
    if (res.data?.texts) {
      row.texts = { ...res.data.texts }
    } else {
      await loadData()
    }
  } catch {
    // axios 拦截器已提示（接口 msg 已由 sy_lang_items 翻译）
  } finally {
    translatingId.value = 0
  }
}

const handleTranslateForce = (row: any) => {
  ElMessageBox.confirm(t('将覆盖该行已有译文，是否继续？'), t('强制翻译'), { type: 'warning' }).then(
    () => {
      handleTranslate(row, 1)
    }
  )
}

const handleDialogTranslate = async (overwrite = 0) => {
  if (dialogTranslating.value) return
  const title = form.value.title.trim()
  if (!title) {
    ElMessage.warning(t('请先填写文案键'))
    return
  }
  dialogTranslating.value = true
  try {
    const res = await translateLangTextPreviewApi({
      title,
      overwrite,
      existing: form.value.texts
    })
    const map = res.data?.texts ?? {}
    Object.keys(map).forEach((lang) => {
      form.value.texts[lang] = String(map[lang] ?? '')
    })
    const n = res.data?.translated?.length ?? 0
    ElMessage.success(n > 0 ? t('已翻译几种语言', { n }) : t('无需翻译'))
  } catch {
    // axios 拦截器已提示
  } finally {
    dialogTranslating.value = false
  }
}

const handleDialogTranslateForce = () => {
  ElMessageBox.confirm(t('将覆盖该行已有译文，是否继续？'), t('强制翻译'), { type: 'warning' }).then(
    () => {
      handleDialogTranslate(1)
    }
  )
}

const importVisible = ref(false)
const importing = ref(false)
const importForm = ref({
  type: 'front',
  lang: '',
  overwrite: false,
  file: null as File | null
})

const openImport = () => {
  importForm.value = {
    type: queryParams.value.type || 'front',
    lang: langs.value[0]?.lang || '',
    overwrite: false,
    file: null
  }
  importVisible.value = true
}

const onImportFileChange = (uploadFile: any) => {
  importForm.value.file = uploadFile?.raw || null
  const name = String(uploadFile?.name || '')
  const base = name.replace(/\.(php|json)$/i, '').replace(/_/g, '-').toLowerCase()
  const hit = langs.value.find((l) => {
    const code = String(l.lang || '').toLowerCase()
    return code === base || code.replace(/_/g, '-') === base
  })
  if (hit) {
    importForm.value.lang = hit.lang
  }
}

const handleImport = async () => {
  if (!importForm.value.file) {
    ElMessage.warning(t('请选择要导入的文件'))
    return
  }
  importing.value = true
  try {
    const fd = new FormData()
    fd.append('file', importForm.value.file)
    fd.append('type', importForm.value.type)
    fd.append('lang', importForm.value.lang || '')
    fd.append('overwrite', importForm.value.overwrite ? '1' : '0')
    const res = await importLangTextApi(fd)
    ElMessage.success(res.msg || t('导入成功'))
    importVisible.value = false
    loadData()
  } catch {
    // 拦截器提示
  } finally {
    importing.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <ContentWrap :title="t('翻译文案')">
    <div class="mb-16px flex flex-wrap gap-12px items-center">
      <el-select v-model="queryParams.type" :placeholder="t('类型')" clearable style="width: 140px">
        <el-option
          v-for="opt in typeOptions"
          :key="opt.value"
          :label="t(opt.label)"
          :value="opt.value"
        />
      </el-select>
      <el-input
        v-model="queryParams.keyword"
        :placeholder="t('文案键关键词')"
        style="width: 220px"
        clearable
      />
      <el-button type="primary" @click="loadData">{{ t('查询') }}</el-button>
      <el-button type="success" @click="openCreate">{{ t('新增文案') }}</el-button>
      <el-button @click="openImport">{{ t('导入') }}</el-button>
      <el-button @click="handleExport">{{ t('导出PHP翻译文件') }}</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column :label="t('类型')" width="90">
        <template #default="{ row }">
          <el-tag :type="row.type === 'admin' ? 'warning' : 'success'" size="small">
            {{ typeLabel(row.type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="title" :label="t('文案键')" min-width="200" show-overflow-tooltip />
      <el-table-column
        v-for="lang in langs"
        :key="lang.id"
        :label="lang.title"
        min-width="160"
        show-overflow-tooltip
      >
        <template #default="{ row }">{{ row.texts?.[lang.lang] || '-' }}</template>
      </el-table-column>
      <el-table-column :label="t('操作')" width="260" fixed="right">
        <template #default="{ row }">
          <el-button
            link
            type="warning"
            :loading="translatingId === row.id"
            @click="handleTranslate(row, 0)"
          >
            {{ t('一键翻译') }}
          </el-button>
          <el-button link type="primary" @click="openEdit(row)">{{ t('编辑') }}</el-button>
          <el-button link type="info" @click="handleTranslateForce(row)">{{ t('强翻') }}</el-button>
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

    <el-dialog v-model="dialogVisible" :title="editId ? t('编辑文案') : t('新增文案')" width="640px">
      <el-form label-width="100px">
        <el-form-item :label="t('类型')" required>
          <el-select v-model="form.type" style="width: 100%">
            <el-option
              v-for="opt in typeOptions"
              :key="opt.value"
              :label="t(opt.label)"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('文案键')" required>
          <el-input v-model="form.title" :placeholder="t('通常为中文源文本')" />
        </el-form-item>
        <el-form-item :label="t('多语言翻译')">
          <div class="flex flex-wrap gap-8px">
            <el-button type="warning" :loading="dialogTranslating" @click="handleDialogTranslate(0)">
              {{ t('一键翻译') }}
            </el-button>
            <el-button :disabled="dialogTranslating" @click="handleDialogTranslateForce">
              {{ t('强翻') }}
            </el-button>
            <span class="text-12px text-gray-400" style="line-height: 32px">
              {{ t('根据文案键自动翻译到其它可切换语言') }}
            </span>
          </div>
        </el-form-item>
        <el-form-item v-for="lang in langs" :key="lang.id" :label="lang.remark || lang.title">
          <el-input v-model="form.texts[lang.lang]" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('取消') }}</el-button>
        <el-button type="primary" @click="handleSave">{{ t('保存') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="importVisible" :title="t('导入翻译文案')" width="560px">
      <el-form label-width="110px">
        <el-form-item :label="t('类型')" required>
          <el-select v-model="importForm.type" style="width: 100%">
            <el-option
              v-for="opt in typeOptions"
              :key="opt.value"
              :label="t(opt.label)"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('目标语言')">
          <el-select
            v-model="importForm.lang"
            clearable
            :placeholder="t('键值对格式必选')"
            style="width: 100%"
          >
            <el-option
              v-for="lang in langs"
              :key="lang.id"
              :label="`${lang.title} (${lang.lang})`"
              :value="lang.lang"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('覆盖已有')">
          <el-switch v-model="importForm.overwrite" />
        </el-form-item>
        <el-form-item :label="t('文件')" required>
          <el-upload
            :auto-upload="false"
            :limit="1"
            accept=".php,.json,application/json"
            :on-change="onImportFileChange"
            :on-remove="() => (importForm.file = null)"
          >
            <el-button type="primary">{{ t('选择文件') }}</el-button>
            <template #tip>
              <div class="el-upload__tip">{{ t('导入格式说明') }}</div>
            </template>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="importVisible = false">{{ t('取消') }}</el-button>
        <el-button type="primary" :loading="importing" @click="handleImport">
          {{ t('开始导入') }}
        </el-button>
      </template>
    </el-dialog>
  </ContentWrap>
</template>
