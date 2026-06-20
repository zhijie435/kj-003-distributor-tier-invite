# 经销商分级邀请码 - 部署文档

## 目录

1. [功能概述](#功能概述)
2. [环境要求](#环境要求)
3. [环境变量配置](#环境变量配置)
4. [数据库迁移与种子数据](#数据库迁移与种子数据)
5. [队列任务配置](#队列任务配置)
6. [定时任务配置](#定时任务配置)
7. [验收命令](#验收命令)
8. [API 接口文档](#api-接口文档)
9. [常见问题排查](#常见问题排查)

---

## 功能概述

经销商分级邀请码系统支持以下功能：

- **四级经销商分级**：普通经销商 (NORMAL)、银牌经销商 (SILVER)、金牌经销商 (GOLD)、钻石经销商 (DIAMOND)
- **邀请码管理**：创建、编辑、删除、恢复、批量生成、启用/禁用
- **邀请码属性**：自定义码长 (4-20位)、最大使用次数、过期时间、描述
- **邀请码兑换**：用户使用邀请码后自动加入对应客户分组
- **邀请码验证**：在兑换前可验证邀请码有效性
- **统计与清理**：使用情况统计、过期邀请码自动清理

---

## 环境要求

- PHP >= 8.2
- Laravel >= 11.x
- 数据库：MySQL 5.7+ / PostgreSQL 10+ / SQLite 3.8.8+
- （可选）Redis 用于队列和缓存

---

## 环境变量配置

在 `.env` 文件中添加以下配置项（已预置默认值，可按需调整）：

```bash
# ============================================
# 经销商分级邀请码配置
# ============================================

# 邀请码默认长度 (4-20，默认8)
INVITATION_CODE_DEFAULT_LENGTH=8

# 邀请码最小长度
INVITATION_CODE_MIN_LENGTH=4

# 邀请码最大长度
INVITATION_CODE_MAX_LENGTH=20

# 单次批量生成邀请码最大数量
INVITATION_CODE_BATCH_MAX=100

# 邀请码最大使用次数上限
INVITATION_CODE_MAX_USES_LIMIT=1000000

# 是否启用邀请码队列处理 (批量生成时使用队列)
INVITATION_CODE_QUEUE_ENABLED=false

# 邀请码队列名称
INVITATION_CODE_QUEUE=invitation-codes

# 过期邀请码自动清理 (天)，0表示不自动清理
INVITATION_CODE_EXPIRED_CLEANUP_DAYS=30

# 默认客户分组邀请码设置
# 普通经销商分组代码
CUSTOMER_GROUP_NORMAL_CODE=NORMAL
# 银牌经销商分组代码
CUSTOMER_GROUP_SILVER_CODE=SILVER
# 金牌经销商分组代码
CUSTOMER_GROUP_GOLD_CODE=GOLD
# 钻石经销商分组代码
CUSTOMER_GROUP_DIAMOND_CODE=DIAMOND
```

### 配置说明

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `INVITATION_CODE_DEFAULT_LENGTH` | 8 | 未指定长度时的默认邀请码长度 |
| `INVITATION_CODE_MIN_LENGTH` | 4 | 允许的最短邀请码长度 |
| `INVITATION_CODE_MAX_LENGTH` | 20 | 允许的最长邀请码长度 |
| `INVITATION_CODE_BATCH_MAX` | 100 | API接口单次批量生成上限 |
| `INVITATION_CODE_MAX_USES_LIMIT` | 1000000 | 单个邀请码最大可使用次数上限 |
| `INVITATION_CODE_QUEUE_ENABLED` | false | 是否使用队列异步处理批量生成 |
| `INVITATION_CODE_QUEUE` | invitation-codes | 队列名称 |
| `INVITATION_CODE_EXPIRED_CLEANUP_DAYS` | 30 | 自动清理过期多少天的邀请码，0为不清理 |
| `CUSTOMER_GROUP_*_CODE` | NORMAL/SILVER/GOLD/DIAMOND | 四级经销商分组的代码标识 |

修改配置后执行：

```bash
php artisan config:clear
php artisan config:cache   # 生产环境
```

---

## 数据库迁移与种子数据

### 1. 执行迁移

```bash
cd backend

# 首次部署执行
php artisan migrate

# 或重置并重新执行（会清空所有数据！）
php artisan migrate:fresh
```

迁移文件会创建以下数据表：

| 表名 | 说明 |
|------|------|
| `customer_groups` | 客户分组表（经销商分级） |
| `model_has_customer_groups` | 用户与客户分组多态关联表 |
| `invitation_codes` | 邀请码表 |
| `invitation_code_usages` | 邀请码使用记录表 |

### 2. 执行种子数据

```bash
# 执行全部种子
php artisan db:seed

# 仅执行 DatabaseSeeder（包含经销商分级和邀请码示例数据）
php artisan db:seed --class=DatabaseSeeder
```

种子数据会创建：

- **4个经销商分级**：普通经销商、银牌经销商、金牌经销商、钻石经销商
- **24个示例邀请码**：每级6个（1个不限次通用码 + 3个100次限量码 + 2个10次限量码）
- **10个测试用户**：分别关联到不同客户分组

### 3. 一键重置（开发/测试环境）

```bash
php artisan migrate:fresh --seed
```

---

## 队列任务配置

### 队列任务列表

| 任务类 | 说明 | 场景 |
|--------|------|------|
| `BatchGenerateInvitationCodesJob` | 异步批量生成邀请码 | 大批量生成邀请码时避免请求超时 |
| `CleanupExpiredInvitationCodesJob` | 清理过期邀请码 | 定期维护数据库 |

### 启动队列 Worker

#### 方式一：直接启动（开发环境）

```bash
# 启动默认队列 + 邀请码队列
php artisan queue:work --queue=default,invitation-codes

# 仅启动邀请码专用队列
php artisan queue:work --queue=invitation-codes
```

#### 方式二：Supervisor（生产环境推荐）

创建 `/etc/supervisor/conf.d/invitation-codes.conf`：

```ini
[program:invitation-codes-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/backend/artisan queue:work --queue=invitation-codes --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/project/backend/storage/logs/queue-worker.log
stopwaitsecs=3600
```

启动并生效：

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start invitation-codes-worker:*
```

### 启用队列处理

在 `.env` 中设置：

```bash
QUEUE_CONNECTION=database      # 或 redis
INVITATION_CODE_QUEUE_ENABLED=true
```

如使用 Redis：

```bash
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

初始化队列表（使用 database 驱动时）：

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

---

## 定时任务配置

### 已配置的定时任务

系统已在 `routes/console.php` 中配置了自动清理任务：

| 任务 | 执行时间 | 说明 |
|------|----------|------|
| 过期邀请码清理 | 每天 03:00 | 清理 `INVITATION_CODE_EXPIRED_CLEANUP_DAYS` 天前过期的邀请码 |

### 方式一：Crontab

编辑 crontab：

```bash
crontab -e
```

添加：

```bash
* * * * * cd /path/to/project/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 方式二：手动触发

```bash
# 查看所有已注册的定时任务
php artisan schedule:list

# 手动执行一次清理任务
php artisan invitation-codes:cleanup --days=30 --force

# 测试定时任务调度（生产环境不要用）
php artisan schedule:work
```

---

## 验收命令

系统提供 4 个 Artisan 命令用于管理和验收邀请码功能。

### 1. 功能验收命令（核心）

```bash
php artisan invitation-codes:verify
```

**可选参数**：

| 参数 | 说明 |
|------|------|
| `--smoke` | 仅运行冒烟测试（跳过边界用例） |
| `--output=table` | 输出格式：`table`（默认）或 `json` |

**测试覆盖范围（共 27 项）**：

1. 经销商分级客户分组检查（5项）
2. 邀请码 CRUD 操作（7项）
3. 邀请码兑换功能（4项）
4. 批量生成邀请码（3项）
5. 邀请码验证功能（4项）
6. 边界情况测试（4项，--smoke 时跳过）

**示例输出**：

```
===== 开始验收经销商分级邀请码功能 =====

[1/6] 检查经销商分级客户分组...
  ✓ 客户分组 NORMAL 存在
  ✓ 客户分组 SILVER 存在
  ...
===== 验收完成: 27/27 通过，0 失败 =====
```

### 2. 邀请码统计

```bash
# 查看全部统计
php artisan invitation-codes:stats

# 按分组筛选
php artisan invitation-codes:stats --group=1

# JSON 输出
php artisan invitation-codes:stats --format=json
```

输出包含：总数、有效、已禁用、已过期、已用完、已删除、累计使用次数，以及按客户分组的统计。

### 3. 生成邀请码

```bash
# 为 SILVER 分组生成 1 个邀请码
php artisan invitation-codes:generate SILVER

# 为分组 ID=1 生成 10 个邀请码
php artisan invitation-codes:generate 1 10

# 指定自定义邀请码
php artisan invitation-codes:generate GOLD 1 --code=SPECIAL2026

# 指定长度、使用次数、过期时间
php artisan invitation-codes:generate NORMAL 5 \
  --length=12 \
  --max-uses=100 \
  --expires-days=30 \
  --description="活动专用"

# 仅输出生成的邀请码字符串（便于脚本处理）
php artisan invitation-codes:generate SILVER 3 --output=codes

# 使用队列异步生成（需开启队列）
php artisan invitation-codes:generate GOLD 100 --queue
```

**参数说明**：

| 参数 | 说明 |
|------|------|
| `customer-group` | (必填) 客户分组 ID 或代码 |
| `count` | (可选) 生成数量，默认 1 |
| `--code` | 自定义邀请码（仅 count=1 时） |
| `--length` | 邀请码长度（4-20） |
| `--max-uses` | 最大使用次数，0 表示不限 |
| `--expires-days` | 多少天后过期 |
| `--expires-at` | 指定过期时间 (Y-m-d H:i:s) |
| `--description` | 邀请码描述 |
| `--queue` | 使用队列异步生成 |
| `--output` | 输出格式：table/json/codes |

### 4. 清理过期邀请码

```bash
# 预览将要删除的邀请码（不实际删除）
php artisan invitation-codes:cleanup --dry-run

# 清理 30 天前过期的邀请码（会要求确认）
php artisan invitation-codes:cleanup --days=30

# 跳过确认直接执行
php artisan invitation-codes:cleanup --days=30 --force

# 提交到队列异步执行
php artisan invitation-codes:cleanup --days=30 --queue
```

---

## API 接口文档

所有邀请码接口都需要通过 `auth:sanctum` 中间件认证（`/validate` 除外）。

### 客户分组接口

| Method | 路径 | 说明 |
|--------|------|------|
| GET | `/api/customer-groups/all` | 获取所有有效客户分组（带缓存） |
| GET | `/api/customer-groups` | 客户分组分页列表 |
| POST | `/api/customer-groups` | 创建客户分组 |
| GET | `/api/customer-groups/{id}` | 客户分组详情 |
| PUT | `/api/customer-groups/{id}` | 更新客户分组 |
| DELETE | `/api/customer-groups/{id}` | 删除客户分组 |
| PATCH | `/api/customer-groups/{id}/toggle-active` | 切换启用状态 |
| POST | `/api/customer-groups/{id}/attach-users` | 批量添加用户 |
| POST | `/api/customer-groups/{id}/detach-users` | 批量移除用户 |
| GET | `/api/customer-groups/{id}/invitation-codes` | 获取该分组下的所有邀请码 |

### 邀请码接口

| Method | 路径 | 说明 |
|--------|------|------|
| GET | `/api/invitation-codes` | 邀请码分页列表 |
| POST | `/api/invitation-codes` | 创建单个邀请码 |
| POST | `/api/invitation-codes/batch-generate` | 批量生成邀请码 |
| POST | `/api/invitation-codes/redeem` | 兑换使用邀请码 |
| POST | `/api/invitation-codes/validate` | 验证邀请码有效性（无需登录） |
| GET | `/api/invitation-codes/{id}` | 邀请码详情（含使用记录） |
| PUT | `/api/invitation-codes/{id}` | 更新邀请码 |
| DELETE | `/api/invitation-codes/{id}` | 软删除邀请码 |
| PATCH | `/api/invitation-codes/{id}/toggle-active` | 切换启用状态 |
| POST | `/api/invitation-codes/{id}/restore` | 恢复已删除的邀请码 |

### 列表筛选参数

```
GET /api/invitation-codes?
  customer_group_id=1    # 按客户分组筛选
  status=active          # 状态筛选: active/inactive/expired/used_up/deleted
  active=1               # 仅显示有效
  is_valid=1             # 仅显示可兑换
  search=ABC             # 按邀请码或描述搜索
  include_trashed=1      # 包含已删除
  only_trashed=1         # 仅显示已删除
  per_page=15            # 每页数量
  page=1                 # 页码
```

### 兑换邀请码示例

```bash
curl -X POST http://localhost:8000/api/invitation-codes/redeem \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"code": "SILVER2026"}'
```

成功响应：

```json
{
  "message": "邀请码使用成功",
  "data": {
    "invitation_code": { "...": "..." },
    "customer_group": {
      "id": 2,
      "name": "银牌经销商",
      "code": "SILVER"
    }
  }
}
```

---

## 常见问题排查

### 1. 运行验收命令失败

**现象**：`php artisan invitation-codes:verify` 有项目不通过。

**排查步骤**：

```bash
# 1. 确认数据库迁移已执行
php artisan migrate:status

# 2. 确认种子数据已执行
php artisan tinker
> App\Models\CustomerGroup::count();  // 应返回 >= 4
> App\Models\User::count();           // 应返回 >= 1

# 3. 重新迁移和播种
php artisan migrate:fresh --seed

# 4. 再次运行验收
php artisan invitation-codes:verify
```

### 2. 队列任务不执行

**现象**：使用 `--queue` 参数后邀请码未生成。

**排查步骤**：

```bash
# 1. 确认 .env 队列配置
grep QUEUE .env

# 2. 确认队列 Worker 正在运行
ps aux | grep "queue:work"

# 3. 查看失败任务
php artisan queue:failed

# 4. 手动同步执行测试
php artisan invitation-codes:generate SILVER 3
```

### 3. 定时任务不执行

**现象**：过期邀请码未自动清理。

**排查步骤**：

```bash
# 1. 确认 crontab 配置
crontab -l

# 2. 手动执行调度
php artisan schedule:run -v

# 3. 手动清理测试
php artisan invitation-codes:cleanup --days=1 --dry-run
```

### 4. 配置不生效

**现象**：修改 `.env` 后邀请码长度未变化。

**解决**：

```bash
php artisan config:clear
php artisan cache:clear
```

生产环境使用：

```bash
php artisan config:cache
```

### 5. 数据库表已存在导致迁移失败

```bash
# 方案一：安全迁移（保留数据）
php artisan migrate

# 方案二：重置所有数据（开发环境）
php artisan migrate:fresh --seed
```

---

## 快速部署 Checklist

```
□ 1. 复制 .env.example 为 .env 并配置数据库
□ 2. 在 .env 中配置经销商分级邀请码相关变量
□ 3. 执行 composer install
□ 4. 执行 php artisan key:generate
□ 5. 执行 php artisan migrate
□ 6. 执行 php artisan db:seed
□ 7. （可选）配置队列并启动 queue:work
□ 8. （可选）配置 crontab 定时任务
□ 9. 运行 php artisan invitation-codes:verify 验证
□ 10. 运行 php artisan test 运行完整测试套件
```

验证通过标准：
- `php artisan invitation-codes:verify` 输出 **27/27 通过**
- `php artisan test --filter InvitationCode` 输出 **173 passed**
