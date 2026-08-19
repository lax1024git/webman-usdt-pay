<script lang="ts" setup>
import { FormSchema, Form } from '@/components/Form'
import { useForm } from '@/hooks/web/useForm'
import { useValidator } from '@/hooks/web/useValidator'
import { useI18n } from '@/hooks/web/useI18n'
import { computed, ref, watch } from 'vue'
import { ElDivider, ElMessage, ElMessageBox } from 'element-plus'

const { t } = useI18n()

const props = defineProps({
  userInfo: {
    type: Object,
    default: () => ({})
  }
})

const { required, phone, maxlength, email } = useValidator()

const formSchema = computed<FormSchema[]>(() => [
  {
    field: 'realName',
    label: t('昵称'),
    component: 'Input',
    colProps: {
      span: 24
    }
  },
  {
    field: 'phoneNumber',
    label: t('手机号码'),
    component: 'Input',
    colProps: {
      span: 24
    }
  },
  {
    field: 'email',
    label: t('邮箱'),
    component: 'Input',
    colProps: {
      span: 24
    }
  }
])

const rules = computed(() => ({
  realName: [required(), maxlength(50)],
  phoneNumber: [phone()],
  email: [email()]
}))

const { formRegister, formMethods } = useForm()
const { setValues, getElFormExpose } = formMethods

watch(
  () => props.userInfo,
  (value) => {
    setValues(value)
  },
  {
    immediate: true,
    deep: true
  }
)

const saveLoading = ref(false)
const save = async () => {
  const elForm = await getElFormExpose()
  const valid = await elForm?.validate().catch((err) => {
    console.log(err)
  })
  if (valid) {
    ElMessageBox.confirm(t('是否确认修改?'), t('提示'), {
      confirmButtonText: t('确认'),
      cancelButtonText: t('取消'),
      type: 'warning'
    })
      .then(async () => {
        try {
          saveLoading.value = true
          // 这里可以调用修改用户信息接口
          ElMessage.success(t('修改成功'))
        } catch (error) {
          console.log(error)
        } finally {
          saveLoading.value = false
        }
      })
      .catch(() => {})
  }
}
</script>

<template>
  <Form :rules="rules" @register="formRegister" :schema="formSchema" />
  <ElDivider />
  <BaseButton type="primary" @click="save">{{ t('保存') }}</BaseButton>
</template>
