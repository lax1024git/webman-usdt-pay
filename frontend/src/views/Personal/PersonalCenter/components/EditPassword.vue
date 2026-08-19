<script setup lang="ts">
import { Form, FormSchema } from '@/components/Form'
import { useForm } from '@/hooks/web/useForm'
import { computed, ref } from 'vue'
import { useValidator } from '@/hooks/web/useValidator'
import { useI18n } from '@/hooks/web/useI18n'
import { ElMessage, ElMessageBox, ElDivider } from 'element-plus'
import { updateAdminPasswordApi } from '@/api/admin'

const { t } = useI18n()

const props = defineProps<{ userId?: number }>()

const { required } = useValidator()

const formSchema = computed<FormSchema[]>(() => [
  {
    field: 'password',
    label: t('旧密码'),
    component: 'InputPassword',
    colProps: {
      span: 24
    }
  },
  {
    field: 'newPassword',
    label: t('新密码'),
    component: 'InputPassword',
    colProps: {
      span: 24
    },
    componentProps: {
      strength: true
    }
  },
  {
    field: 'newPassword2',
    label: t('确认新密码'),
    component: 'InputPassword',
    colProps: {
      span: 24
    },
    componentProps: {
      strength: true
    }
  }
])

const { formRegister, formMethods } = useForm()
const { getFormData, getElFormExpose } = formMethods

const rules = computed(() => ({
  password: [required()],
  newPassword: [
    required(),
    {
      asyncValidator: async (_, val, callback) => {
        const formData = await getFormData()
        const { newPassword2 } = formData
        if (val !== newPassword2) {
          callback(new Error(t('新密码与确认新密码不一致')))
        } else {
          callback()
        }
      }
    }
  ],
  newPassword2: [
    required(),
    {
      asyncValidator: async (_, val, callback) => {
        const formData = await getFormData()
        const { newPassword } = formData
        if (val !== newPassword) {
          callback(new Error(t('确认新密码与新密码不一致')))
        } else {
          callback()
        }
      }
    }
  ]
}))

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
          const formData = await getFormData()
          const id = Number(props.userId || 0)
          if (!id) {
            ElMessage.error(t('无法获取当前用户ID'))
            return
          }
          await updateAdminPasswordApi(id, {
            old_password: formData.password,
            new_password: formData.newPassword
          })
          ElMessage.success(t('修改成功'))
        } catch {
          ElMessage.error(t('修改失败'))
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
  <BaseButton type="primary" @click="save">{{ t('确认修改') }}</BaseButton>
</template>
