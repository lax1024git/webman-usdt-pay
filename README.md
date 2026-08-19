# 广告刷单系统（Webman Admin）

基于 **Webman 2.2** + **Vue 3 Element Plus Admin** + **H5 (Vant)** 的全栈平台，面向广告库存撮合交易场景。包含管理后台、H5 会员端、JWT 认证与 RBAC 权限。

| 层级 | 技术 |
|------|------|
| 后端 | PHP 8.1+ / Webman ~2.2.3 / Eloquent ORM |
| 管理前端 | Vue 3 / Element Plus / Vite / Pinia / TypeScript |
| H5 前端 | Vue 3 / Vant / Vite |
| 数据库 | MySQL 8.0+ |
| 缓存/队列 | Redis 7.0+ |

---

## 快速开始

```bash
# 1. 后端依赖
composer install
cp .env.example .env          # 编辑数据库、Redis、JWT 等，见下文「环境配置」

# 2. 数据库
mysql -u root -p -e "CREATE DATABASE webman_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php webman model              # 按 Model tableSchema 同步表结构
php database/seed.php         # 填充初始数据
php webman menu --fresh       # 同步菜单与 API 权限

# 3. 启动后端
php windows.php               # Windows 开发
# php start.php start         # Linux 开发
# php start.php start -d      # Linux 后台守护

# 4. 管理前端（端口 4000）
cd frontend && pnpm install && pnpm dev

# 5. H5（端口 3000）
cd web && pnpm install && pnpm dev
```

| 服务 | 默认地址 |
|------|----------|
| 后端 API | `http://127.0.0.1:8787` |
| 管理后台 | `http://127.0.0.1:4000` |
| H5 | `http://127.0.0.1:3000` |

| 端 | 用户名 | 密码 |
|----|--------|------|
| 管理后台 | admin | admin123 |

> 完整业务/开发说明见 [doc/README.md](./doc/README.md)

---

## 环境配置（`.env`）

复制 `.env.example` 为 `.env`。**修改任意 `.env` 项后须重启 Webman**（`php windows.php` 或 `php start.php restart`）。

### 应用与服务

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `APP_DEBUG` | `true` | 调试模式；**生产环境设为 `false`** |
| `APP_NAME` | `WebmanAdmin` | 应用标识（JWT iss 等） |
| `APP_TIMEZONE` | `Asia/Shanghai` | PHP 业务时区 |
| `DB_TIMEZONE` | `+08:00` | MySQL session 时区（建议固定偏移） |
| `SERVER_LISTEN` | `http://0.0.0.0:8787` | HTTP 监听地址 |
| `WEBMAN_WORKER_COUNT` | `cpu*2` | HTTP Worker 进程数（可选） |
| `WEBMAN_MINIMAL_PROCESS` | `false` | `true` 时仅启 HTTP，不跑定时结算/文件监控（压测/Docker 用） |

### MySQL

| 变量 | 说明 |
|------|------|
| `DB_CONNECTION` | 固定 `mysql` |
| `DB_HOST` / `DB_PORT` | 数据库地址，默认 `127.0.0.1:3306` |
| `DB_DATABASE` | 库名，默认 `webman_admin` |
| `DB_USERNAME` / `DB_PASSWORD` | 账号密码 |

### Redis

用于权限缓存、登录锁定、验证码、Token 会话、匹配队列等。

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `REDIS_HOST` | `127.0.0.1` | Redis 地址 |
| `REDIS_PORT` | `6379` | 端口 |
| `REDIS_PASSWORD` | 空 | 无密码留空 |
| `REDIS_DATABASE` | `0` | DB 编号 0–15 |
| `REDIS_PREFIX` | `pubmatic:` | Key 前缀，多项目共用 Redis 时隔离 |

### JWT 与登录安全

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `JWT_SECRET` | — | **必改**，JWT 签名密钥 |
| `JWT_ACCESS_TTL` | `7200` | Access Token 有效期（秒，2 小时） |
| `JWT_REFRESH_TTL` | `604800` | Refresh Token 有效期（秒，7 天） |
| `AUTH_SINGLE_DEVICE` | `false` | 单设备登录，新登录踢旧会话 |
| `AUTH_REFRESH_ROTATE` | `true` | 刷新 Token 时轮换 refresh_token |
| `LOGIN_MAX_ATTEMPTS` | `5` | 同用户名+IP 最大失败次数 |
| `LOGIN_LOCK_MINUTES` | `15` | 超限锁定时长（分钟） |
| `LOGIN_FAIL_WINDOW_SECONDS` | `900` | 失败计数窗口（秒） |
| `LOGIN_CAPTCHA_ENABLED` | `false` | 是否启用登录验证码 |
| `LOGIN_CAPTCHA_AFTER_FAILURES` | `3` | 失败几次后强制验证码 |
| `LOGIN_CAPTCHA_TTL` | `300` | 验证码有效期（秒） |

### 上传、跨域、后台安全

| 变量 | 说明 |
|------|------|
| `UPLOAD_PATH` | 本地上传目录，默认 `public/uploads` |
| `UPLOAD_MAX_SIZE` | 图片上限（字节，默认 5MB） |
| `UPLOAD_DOCUMENT_MAX_SIZE` | 文档上限（默认 10MB） |
| `UPLOAD_VIDEO_MAX_SIZE` | 视频上限（默认 100MB） |
| `CORS_ORIGIN` | 跨域 Origin，`*` 或逗号分隔域名 |
| `ADMIN_IP_WHITELIST_ENABLED` | 后台 IP 白名单总开关；规则在后台「系统配置 → IP 白名单」维护 |

### 支付回调

| 变量 | 说明 |
|------|------|
| `PAYMENT_NOTIFY_BASE_URL` | 异步回调根域名，充值 `{BASE}/api/notify/recharge`，提现 `{BASE}/api/notify/withdraw` |
| `PAYMENT_RETURN_PATH` | 同步跳转相对路径（如 `/#/profile`） |
| `PAYMENT_CANCEL_PATH` | 取消跳转相对路径 |

### 实时推送（webman/push）

| 变量 | 说明 |
|------|------|
| `PUSH_ENABLE` | 是否启用推送 |
| `PUSH_WEBSOCKET` | Push 服务 WebSocket 监听，默认 `websocket://0.0.0.0:3131` |
| `PUSH_API` / `PUSH_API_LOCAL` | Push HTTP API 地址 |
| `PUSH_APP_KEY` / `PUSH_APP_SECRET` | 应用密钥 |
| `PUSH_CLIENT_URL` | 浏览器连接地址，默认 `/wss` |
| `PUSH_ADMIN_CHANNEL` | 后台待审提醒频道 |
| `PUSH_NOTICE_CHANNEL` / `PUSH_MESSAGE_CHANNEL` | H5 公告 / 站内信频道 |

### 业务专项

| 变量 | 说明 |
|------|------|
| `MATCH_BENCH_TOKEN` | 广告位匹配压测 `/api/finance_product/match_bench` 口令；留空则不校验 |

### 前端环境（非 `.env`，各自目录）

| 目录 | 文件 | 关键变量 |
|------|------|----------|
| `frontend/` | `.env.development` | `VITE_API_BASE_PATH=http://127.0.0.1:8787` |
| `web/` | `.env.development` | `VITE_API_BASE_URL=http://127.0.0.1:8787/` |

生产环境参考 `deploy/DEPLOY.md` 与 `.env.production.example`。

---

## `php webman` 指令

查看全部命令：

```bash
php webman list
php webman help <command>    # 如 php webman help model
```

### 进程启停（Linux）

| 命令 | 说明 |
|------|------|
| `php start.php start` | 调试模式启动（前台） |
| `php start.php start -d` | 守护进程后台启动 |
| `php start.php stop` | 停止 |
| `php start.php stop -g` | 平滑停止 |
| `php start.php restart` | 重启 |
| `php start.php reload` | 热重载代码 |
| `php start.php status` | 查看 Worker 状态 |
| `php start.php status -d` | 守护模式下查看实时状态 |

Windows 开发环境使用 `php windows.php`（等价于调试模式启动，含文件监控）。

> 亦可使用 `php webman start|stop|restart|reload|status`，参数与 `start.php` 相同。

### 数据库与权限（日常最常用）

| 命令 | 说明 |
|------|------|
| `php webman model` | 扫描全部 Model 的 `tableSchema()`，同步表结构/索引 |
| `php webman model XxxModel` | 仅同步单个模型，如 `AdProductOrderModel` |
| `php webman model invest/InvestmentProductModel` | 支持子目录路径 |
| `php webman model XxxModel --make` | 生成带 `tableSchema()` 的模型存根 |
| `php webman model XxxModel --make --force` | 强制覆盖已存在模型文件 |
| `php webman menu` | 扫描控制器 `menuConfig()`，增量同步菜单与 API 权限 |
| `php webman menu --fresh` | 同步并**删除**扫描结果外的旧权限（推荐日常用） |
| `php webman menu --scan` | 仅预览扫描结果，不写库 |
| `php webman menu --reseed` | 清空权限表后全量重建（等同 `composer reset-menus`） |
| `php database/seed.php` | 填充初始管理员、字典等（等同 `composer seed`） |

**Composer 快捷别名：**

```bash
composer migrate      # = php webman model
composer sync-model   # = php webman model
composer sync-menu    # = php webman menu
composer reset-menus  # = php webman menu --reseed
composer seed         # = php database/seed.php
composer test         # = phpunit
```

### 多语言

| 命令 | 说明 |
|------|------|
| `php webman lang:collect-front` | 扫描 H5 API 中文提示，写入 `sy_lang_items`（type=front） |
| `php webman lang:collect-front --dry-run` | 只扫描不入库 |
| `php webman lang:translate --type=front --only-empty` | Google 翻译，仅补空译文 |
| `php webman lang:translate --type=admin --force` | 覆盖已有后台译文 |
| `php webman lang:translate --id=123 --limit=50` | 指定 ID / 限制条数 |

### 财务结算（定时任务也可手动跑）

| 命令 | 说明 |
|------|------|
| `php webman finance:settle` | 执行全部结算组 |
| `php webman finance:settle --group=orders` | 广告/托管订单到期结算 |
| `php webman finance:settle --group=rewards` | 推广/活动奖励结算 |
| `php webman finance:settle --group=credit` | 信用分相关结算 |
| `php webman finance:settle --group=daily` | 日报/分红等日结任务 |

> Webman 启动后会自动跑 `finance_settle_*`、`ad_password_refresh` 等进程（见 `config/process.php`）；`WEBMAN_MINIMAL_PROCESS=true` 时可关闭。

### 会员树维护（运维/数据修复）

| 命令 | 说明 |
|------|------|
| `php webman member:rebuild-tree` | 按 `parent_id` 重建全部用户 `tree_id` |
| `php webman member:rebuild-tree --dry-run` | 只统计不写库 |
| `php webman member:normalize-tree` | 统一 `tree_id` 为 `/id/` 路径格式 |
| `php webman member:fix-login-log-type` | 修正登录日志 `type=2` → `type=0` |

### 其他业务命令

| 命令 | 说明 |
|------|------|
| `php webman brokerage-pool:migrate-history` | 迁移历史佣金池差额到 `brokerage_balance` |
| `php webman route:list` | 列出全部路由 |
| `php scripts/check_openapi_sync.php` | 对比路由与 `doc/openapi.json` 是否同步 |

### 后台 Redis 队列（随 Webman 自动启动）

| 进程名 | 队列 | 用途 |
|--------|------|------|
| `match` | `finance_product_match` | 广告位撮合异步消费 |
| `transfer` | 代理转账 | 代理资金划转 |
| `export` | CSV 导出 | 后台大数据导出 |

配置见 `config/plugin/webman/redis-queue/process.php`。匹配无响应时，检查 Redis 与 `match` 进程是否在跑。

---

## 项目结构

```
.
├── app/
│   ├── admin/controller/       # 管理后台
│   ├── api/controller/         # H5 / 会员 API
│   ├── model/                  # 数据模型（tableSchema 驱动）
│   ├── service/                # 业务层
│   ├── command/                # php webman 自定义命令
│   ├── queue/redis/            # Redis 队列消费者
│   └── process/                # 定时/常驻进程
├── config/routes/              # admin.php / api.php
├── frontend/                   # 管理后台 Vue
├── web/                        # H5 Vue
├── doc/                        # 项目文档（业务 / 开发 / 接口）
├── docs/                       # 参考工程 reference/（见 docs/README.md）
├── database/seed.php           # 初始数据
└── .env.example                # 环境变量模板
```

---

## 接口约定

```json
{ "code": 200, "msg": "success", "data": {} }
{ "code": 42201, "msg": "错误描述", "data": null }
```

认证：`Authorization: Bearer <token>`

- 管理后台：`/admin/login` 签发
- H5 会员：`/api/login/doLogin` 签发；Token 失效返回 `code: 10001`

错误码详见 [doc/接口文档.md](./doc/接口文档.md)。

---

## 文档索引

| 文档 | 说明 |
|------|------|
| [doc/README.md](./doc/README.md) | 文档总索引 |
| [doc/开发文档.md](./doc/开发文档.md) | 环境、模块代码位置、部署要点 |
| [doc/业务功能文档.md](./doc/业务功能文档.md) | 业务功能与核心流程 |
| [doc/接口文档.md](./doc/接口文档.md) | H5 / 管理端 API（日常维护） |
| [doc/架构设计.md](./doc/架构设计.md) | 系统架构、认证、权限 |
| [doc/数据库设计.md](./doc/数据库设计.md) | 表结构、ER 关系 |
| [doc/模型规范.md](./doc/模型规范.md) | 表/字段命名规范 |
| [DEVELOPMENT.md](./DEVELOPMENT.md) | 开发指南与代码示例 |

---

## 测试

```bash
composer test
# 或 vendor/bin/phpunit
```

Postman：`doc/postman/Webman-Admin.postman_collection.json`

---

## 部署

详见 [deploy/DEPLOY.md](./deploy/DEPLOY.md)

- Nginx：`deploy/nginx/webman-admin.conf`
- 生产环境变量：`.env.production.example`
- PHAR 加密发布：`deploy/ENCRYPT_PHAR.md`

---

## License

MIT
