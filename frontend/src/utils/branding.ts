import { getBrandingApi } from '@/api/login'
import { useAppStoreWithOut } from '@/store/modules/app'

/** ?? public/logo.png ?????????????????? */
export const setupAdminBranding = async () => {
  const appStore = useAppStoreWithOut()
  try {
    const res = await getBrandingApi()
    const data = res.data || {}
    const name = String(data.name || '').trim()
    if (name) {
      appStore.setTitle(name)
    }
    appStore.setBrandLogo('/logo.png')
  } catch {
    appStore.setBrandLogo('/logo.png')
  }
}
