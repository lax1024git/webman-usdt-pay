# USDT 充付系统 — 文档索引

> 基于 Webman 2.2 + Vue Element Plus Admin 的 **充 U（入金）** 与 **付 U（出金/代付）** 支付网关。

| 文档 | 说明 |
|------|------|
| [PRD-USDT充付系统.md](./PRD-USDT充付系统.md) | 产品需求、角色、业务流程、状态机 |
| [架构设计.md](./架构设计.md) | 系统分层、模块划分、链上监听、队列与定时任务 |
| [数据库设计.md](./数据库设计.md) | 表结构、索引、分区、字段说明 |
| [接口文档.md](./接口文档.md) | 管理端 `/admin/*` 与商户端 `/api/*` 接口约定 |
| [模型规范.md](./模型规范.md) | Model `tableSchema` 约定与命名规范 |
| [开发计划.md](./开发计划.md) | 分期交付、目录规划、联调与上线清单 |

---

## 阅读顺序（建议）

1. **PRD** — 理解业务目标与边界  
2. **架构设计** — 把握模块与数据流  
3. **数据库设计** — 落表前对齐字段  
4. **接口文档** — 前后端与商户联调依据  
5. **模型规范** — 编码与 `php webman model` 同步  
6. **开发计划** — 按里程碑实施  

---

## 术语

| 术语 | 含义 |
|------|------|
| 充 U / 入金 | 用户或下游商户向平台指定地址转入 USDT，系统确认到账后记账 |
| 付 U / 出金 / 代付 | 平台从热钱包向目标地址转出 USDT，完成商户提现或代付 |
| 商户 | 接入本系统的业务方，通过 API 创建订单并接收回调 |
| 通道 / 平台 | 具体链路与币种组合，如 `TRC20-USDT` |
| 归集 | 将分散的充值地址余额汇总至冷/热钱包 |

---

## 与现有工程的关系

当前仓库为 **Webman 管理壳**（登录/JWT、RBAC、菜单、字典、日志、导出等已就绪）。USDT 充付业务作为 **新业务域** 增量接入：

- 后端：`app/admin/controller`、`app/api/controller`（商户 API）、`app/service`、`app/model/pay`
- 前端：`frontend/src/views/Pay/*`
- 表前缀：`pa_*`（payment）
- 系统表保持 `sy_*` 不变

---

## 快速命令

```bash
# 同步 pa_* 业务表（模型就绪后）
php webman model DepositOrderModel WithdrawOrderModel ...

# 同步菜单与 API 权限
php webman menu --fresh

# 启动 Redis 队列消费者（出金广播、回调重试等）
php start.php restart
```
