/** 从 /admin/me 返回的 roles 中提取 slug 列表 */
export function normalizeRoleSlugs(roles: Array<string | { slug?: string }> = []): string[] {
  return roles.map((role) => (typeof role === 'string' ? role : role.slug || '')).filter(Boolean)
}
