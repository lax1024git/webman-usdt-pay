import { ElMessageBox } from 'element-plus'

/**
 * 删除前二次确认，防止误删。
 * 用户取消时 Promise reject（与 ElMessageBox.confirm 行为一致）。
 */
export function confirmDelete(message = '确认删除该数据吗？') {
  return ElMessageBox.confirm(message, '提示', {
    type: 'warning',
    confirmButtonText: '确认',
    cancelButtonText: '取消'
  })
}
