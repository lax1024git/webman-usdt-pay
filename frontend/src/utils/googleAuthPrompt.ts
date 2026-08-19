import { ElMessageBox } from 'element-plus'
import { useI18n } from '@/hooks/web/useI18n'
import { getMeApi, verifyGoogleAuthApi } from '@/api/login'

/**
 * 敏感操作前置：弹窗输入谷歌验证码并立即调接口预校验。
 * 系统配置「后台操作是否开启谷歌验证」关闭时直接返回空串。
 * 取消时抛出 'cancel'。
 */
export const promptGoogleAuthCode = async (actionHint?: string): Promise<string> => {
  try {
    const meRes = await getMeApi()
    if (meRes.data?.admin_google_auth_required === false) {
      return ''
    }
  } catch {
    // me 失败时仍走弹窗，由业务接口最终裁决
  }

  const { t } = useI18n()
  const message = actionHint
    ? `${actionHint}\n${t('请输入 6 位谷歌验证码')}`
    : t('请输入 6 位谷歌验证码')

  for (;;) {
    const { value } = await ElMessageBox.prompt(message, t('谷歌验证'), {
      confirmButtonText: t('确定'),
      cancelButtonText: t('取消'),
      inputPattern: /^\d{6}$/,
      inputErrorMessage: t('请输入 6 位谷歌验证码'),
      inputPlaceholder: t('输入 6 位谷歌验证码'),
      closeOnClickModal: false
    })

    const code = String(value || '').trim()
    try {
      await verifyGoogleAuthApi(code)
      return code
    } catch {
      // axios 已提示错误，继续弹窗重试
    }
  }
}
