/** 文章分类（字典 article_category，value 对应 category_id / type） */
export const ARTICLE_CATEGORY_MAP: Record<string, string> = {
  '1': '平台指南',
  '2': '信用分说明',
  '3': 'AI智能托管规则',
  '4': '签到规则',
  '5': '新人体验金规则',
  '6': '充值返利规则',
  '7': '充值说明',
  '8': '提现说明',
  '9': '共创余额说明',
  '10': '推广奖励规则',
  '11': '广告位交易规则',
  '12': '帮助中心',
  '13': '关于平台',
  '14': '注册协议',
  '15': '服务中心图文'
}

export const ARTICLE_CATEGORY_OPTIONS = Object.entries(ARTICLE_CATEGORY_MAP).map(
  ([value, label]) => ({ value, label })
)

export function getArticleCategoryLabel(categoryId?: number | string | null): string {
  if (categoryId === null || categoryId === undefined || categoryId === '') {
    return '-'
  }
  return ARTICLE_CATEGORY_MAP[String(categoryId)] || String(categoryId)
}
