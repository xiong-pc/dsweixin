# 会员中心设计文档（Member Center Design）

- 日期：2026-04-24
- 涉及模块：会员列表、会员标签、会员等级（含成长值规则）、会员余额、会员佣金、会员积分（含积分明细、积分规则）
- 作者：dsweixin team
- 状态：设计定稿，待 Plan 1 实施

---

## 0. 目标与范围

构建 dsweixin 平台「会员中心」子系统，覆盖会员档案、标签、等级、积分、余额、佣金 6 大子域。本设计为整体蓝图，分 6 个独立 Plan 依次实现。

**不在本期范围**：
- C 端微信登录接口（仅后端预留字段与 API，登录入口在后续专项）。
- 订单系统联动（消费/退款触发积分、成长值、佣金的业务入口以 `MemberBusinessService` 暴露，等订单模块接入时调用）。
- 支付网关对接（提现目前只到「标记已打款」，不做实时到账）。

---

## 1. 架构总览

### 1.1 子系统构成

```
会员中心
├── A. 会员档案   members + member_accounts
├── B. 会员标签   tag_groups + tags + tag_pivot + auto_tag_rules
├── C. 会员等级   levels + growth_rules + growth_logs + level_logs
├── D. 会员积分   points(账户) + points_logs(流水) + points_rules
├── E. 会员余额   balances(三池账户) + balance_logs(流水)
└── F. 会员佣金   commissions + commission_logs + relations
                  + commission_rules + commission_withdrawals
```

合计 **20 张表**。

### 1.2 技术栈继承

- 后端：Laravel 13 + Passport，沿用现有分层（Controller → Service → Model），所有业务接口走 `auth:api + tenant` 中间件。
- 前端：Vue 3 + TS + Element Plus，菜单仍由后端 `/auth/routes` 动态下发。
- 多租户：所有 20 张表均 `use BelongsToTenant`，**强租户隔离**（不像菜单/公告需要跨租户公共数据）。
- 权限：沿用现有菜单 × 角色 × 按钮权限三级模型；权限 key 统一前缀 `mbr:*`。

### 1.3 关键设计决策

| 决策 | 选择 | 理由 |
|---|---|---|
| 会员 vs 后台用户 | 新建 `members` 独立表 | `users` 是后台管理员，职责不同 |
| 多渠道账号 | `members` 主表 + `member_accounts` 绑定表 | 支持微信/手机/账号等多种身份源 |
| 账户与流水 | 账户表 + 流水表分开 | 流水是真实来源，账户是聚合缓存 |
| 余额三池 | available / gift / frozen | 常见电商模型，避免后期改表 |
| 等级升级 | 多因子（成长值 + 累计消费） + 匹配规则 | 用户 Q3 选择 |
| 积分过期 | 表结构预留，默认永久，系统配置可切换 | 用户 Q4 选择 |
| 佣金模型 | 账户+流水+分销关系+提现申请单 全套 | 用户 Q5 选择 |
| 分销层级 | 三级预计算（parent/grand/great_grand） | 多级佣金查询性能 |
| 关系建模 | 预计算三代 + path 字符串 | 规避递归 CTE |
| 自动打标签 | 规则表 + 定时任务 + 条件 JSON | 首版 6 字段，易扩展 |

---

## 2. 数据模型

**与 CLAUDE.md 默认值约定的例外**：`members.phone` 与 `members.email` 设为 `nullable`（不使用默认 `''`），因为需要与 `(tenant_id, phone)` / `(tenant_id, email)` 的 UNIQUE 索引兼容（多个空字符串值会触发唯一冲突，NULL 则不会）。其他字段严格遵循 CLAUDE.md 约定。

### 2.A 会员档案（2 张）

#### `members` — 会员主表

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — | 主键 |
| tenant_id | bigint unsigned | 0 | 租户 ID |
| phone | varchar(20) | null | 手机号（*nullable*，与 UNIQUE 约束兼容） |
| email | varchar(100) | null | 邮箱（*nullable*，同上） |
| nickname | varchar(50) | '' | 昵称 |
| avatar | varchar(255) | '' | 头像 URL |
| real_name | varchar(50) | '' | 实名 |
| id_card | varchar(30) | '' | 身份证号（加密存储） |
| gender | tinyint | 0 | 0 未知 1 男 2 女 |
| birthday | date | null | 生日 |
| source | tinyint | 0 | 1 后台 2 公众号 3 小程序 4 H5 5 其他 |
| level_id | bigint unsigned | 0 | 当前等级 |
| level_upgraded_at | timestamp | null | 升到当前等级时间 |
| level_expired_at | timestamp | null | 等级到期时间（null=不过期） |
| growth_value | int | 0 | 累计成长值（冗余） |
| total_consume | decimal(12,2) | 0.00 | 累计消费（冗余） |
| last_consume_at | timestamp | null | 最近一次消费时间（用于自动打标签） |
| invite_code | varchar(8) | '' | 本人邀请码（唯一） |
| parent_invite_code | varchar(8) | '' | 注册时输入的上级邀请码 |
| verified_status | tinyint | 0 | 0 未认证 1 待审核 2 已认证 3 认证失败 |
| verified_at | timestamp | null | 审核通过时间 |
| verified_reject_reason | varchar(255) | '' | 驳回原因 |
| register_ip | varchar(45) | '' | 注册 IP |
| register_at | timestamp | null | 注册时间 |
| last_login_at | timestamp | null | 上次登录时间 |
| last_login_ip | varchar(45) | '' | 上次登录 IP |
| status | tinyint | 1 | 0 禁用 1 正常 |
| remark | varchar(500) | '' | 备注 |
| created_at / updated_at / deleted_at | timestamp | null | 软删除 |

**索引**
- UNIQUE `(tenant_id, phone)`（phone 非空时）
- UNIQUE `(tenant_id, invite_code)`
- INDEX `(tenant_id, level_id)`、`(tenant_id, status)`、`(tenant_id, created_at)`

#### `member_accounts` — 多渠道账号绑定

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 | 所属会员 |
| provider | varchar(20) | '' | wx_mp / wx_mini / phone / username / email |
| provider_id | varchar(100) | '' | openid / username / phone |
| union_id | varchar(100) | '' | 微信 unionid |
| app_id | varchar(50) | '' | 来源应用 |
| extra | json | null | session_key 等附加信息 |
| created_at / updated_at | timestamp | null |  |

**索引**
- UNIQUE `(tenant_id, provider, provider_id)`
- INDEX `(tenant_id, union_id)`、`(member_id)`

### 2.B 会员标签（4 张）

#### `member_tag_groups`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| name | varchar(50) | '' | 分组名 |
| sort | int | 0 |  |
| created_at / updated_at / deleted_at | timestamp | null | 软删除 |

#### `member_tags`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| group_id | bigint unsigned | 0 |  |
| name | varchar(50) | '' | 标签名 |
| color | varchar(20) | '' | 颜色代码 |
| type | tinyint | 1 | 1 手动 2 自动 |
| sort | int | 0 |  |
| remark | varchar(255) | '' |  |
| created_at / updated_at / deleted_at | timestamp | null |  |

**索引**：UNIQUE `(tenant_id, name, deleted_at)`、INDEX `(tenant_id, group_id)`

#### `member_tag_pivot`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| tag_id | bigint unsigned | 0 |  |
| source | tinyint | 1 | 1 手动 2 自动 3 导入 |
| operator_id | bigint unsigned | 0 | 后台操作人 |
| expired_at | timestamp | null | null=永久有效 |
| created_at | timestamp | null |  |

**索引**：UNIQUE `(member_id, tag_id)`、INDEX `(tag_id)`、INDEX `(expired_at)`

#### `member_auto_tag_rules`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| tag_id | bigint unsigned | 0 |  |
| name | varchar(100) | '' |  |
| conditions | json | null | 规则 JSON |
| expire_days | int | 0 | 0=永久；>0=自动标签有效天数 |
| status | tinyint | 1 | 0 关 1 开 |
| remark | varchar(255) | '' |  |
| last_run_at | timestamp | null |  |
| created_at / updated_at / deleted_at | timestamp | null |  |

`conditions` 结构：
```json
{"all":[
  {"field":"total_consume","op":">=","value":1000},
  {"field":"days_since_last_consume","op":">","value":30}
]}
```

MVP 支持字段：`total_consume` / `days_since_last_consume` / `current_level_id` / `days_since_register` / `growth_value` / `points_balance`；支持操作符：`=` `!=` `>` `>=` `<` `<=` `in` `not_in`；支持根节点关系：`all`（AND）/ `any`（OR）。不支持嵌套组合（首版简化）。

### 2.C 会员等级（4 张）

#### `member_levels`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| name | varchar(50) | '' | 等级名（V1/V2/黄金/钻石） |
| code | varchar(30) | '' | 等级代码（编程引用） |
| icon | varchar(255) | '' | 图标 |
| sort | int | 0 | 等级顺序，越大越高 |
| growth_min | int | 0 | 成长值下限 |
| consume_min | decimal(12,2) | 0.00 | 累计消费下限 |
| match_rule | tinyint | 1 | 1 任一 2 全部 |
| discount | decimal(5,2) | 1.00 | 折扣（0.95=95 折） |
| benefits | json | null | 权益描述 |
| status | tinyint | 1 |  |
| remark | varchar(255) | '' |  |
| created_at / updated_at / deleted_at | timestamp | null |  |

**索引**：UNIQUE `(tenant_id, code, deleted_at)`、INDEX `(tenant_id, sort)`

#### `member_growth_rules`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| event_key | varchar(50) | '' | order.paid / signin.daily / invite.register / manual / register |
| name | varchar(100) | '' |  |
| rule_type | tinyint | 1 | 1 固定值 2 按金额比例 |
| amount | int | 0 | 固定值 |
| ratio | decimal(8,4) | 0 | 比例（金额 × ratio = 成长值） |
| daily_limit | int | 0 | 每日上限，0=不限 |
| status | tinyint | 1 |  |
| sort | int | 0 |  |
| remark | varchar(255) | '' |  |
| created_at / updated_at / deleted_at | timestamp | null |  |

**索引**：UNIQUE `(tenant_id, event_key, deleted_at)`

#### `member_growth_logs`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| event_key | varchar(50) | '' |  |
| change_type | tinyint | 1 | 1 + 2 - |
| amount | int | 0 | 正数 |
| before_value | int | 0 |  |
| after_value | int | 0 |  |
| source_type | varchar(30) | '' | order / admin / rule |
| source_id | bigint unsigned | 0 |  |
| operator_id | bigint unsigned | 0 | 0=系统触发 |
| remark | varchar(255) | '' |  |
| created_at | timestamp | null |  |

**索引**：INDEX `(tenant_id, member_id, created_at)`、INDEX `(source_type, source_id)`

#### `member_level_logs`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| before_level_id | bigint unsigned | 0 |  |
| after_level_id | bigint unsigned | 0 |  |
| change_type | tinyint | 1 | 1 升级 2 降级 3 初始化 4 手动 5 到期降级 |
| trigger_source | varchar(50) | '' | growth.check / admin / expire.job |
| operator_id | bigint unsigned | 0 |  |
| remark | varchar(255) | '' |  |
| created_at | timestamp | null |  |

**索引**：INDEX `(tenant_id, member_id, created_at)`

### 2.D 会员积分（3 张）

#### `member_points` — 账户

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| balance | int | 0 | 当前可用积分 |
| total_earned | int | 0 | 累计获得 |
| total_spent | int | 0 | 累计消耗 |
| total_expired | int | 0 | 累计过期 |
| updated_at | timestamp | null |  |

**索引**：UNIQUE `(member_id)`

#### `member_points_logs`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| change_type | tinyint | 1 | 1+获得 2-消耗 3-过期 4+退回 5+调增 6-调减 |
| amount | int | 0 | 正数 |
| before_balance | int | 0 |  |
| after_balance | int | 0 |  |
| expired_at | timestamp | null | 本批过期时间（预留 FIFO 用） |
| source_type | varchar(30) | '' |  |
| source_id | bigint unsigned | 0 |  |
| source_key | varchar(50) | '' | 对应 rule 的 event_key |
| operator_id | bigint unsigned | 0 |  |
| remark | varchar(255) | '' |  |
| created_at | timestamp | null |  |

**索引**：INDEX `(member_id, created_at)`、INDEX `(source_type, source_id)`、INDEX `(expired_at)`

#### `member_points_rules`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| event_key | varchar(50) | '' | order.paid / signin.daily / register / birthday / manual |
| name | varchar(100) | '' |  |
| rule_type | tinyint | 1 | 1 固定值 2 按金额比例 |
| amount | int | 0 |  |
| ratio | decimal(8,4) | 0 |  |
| daily_limit | int | 0 | 每日上限 |
| total_limit | int | 0 | 总上限 |
| expire_strategy | tinyint | 0 | 0 永久 1 自然年 2 按批次N月 |
| expire_months | int | 0 | strategy=2 时使用 |
| status | tinyint | 1 |  |
| sort | int | 0 |  |
| remark | varchar(255) | '' |  |
| created_at / updated_at / deleted_at | timestamp | null |  |

**索引**：UNIQUE `(tenant_id, event_key, deleted_at)`

### 2.E 会员余额（2 张 · 三池）

#### `member_balances`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| available | decimal(12,2) | 0.00 | 可用余额 |
| gift | decimal(12,2) | 0.00 | 赠送余额（不可提现） |
| frozen | decimal(12,2) | 0.00 | 冻结余额 |
| total_recharged | decimal(12,2) | 0.00 | 累计充值 |
| total_consumed | decimal(12,2) | 0.00 | 累计消费 |
| updated_at | timestamp | null |  |

**索引**：UNIQUE `(member_id)`

#### `member_balance_logs`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| pool | tinyint | 1 | 1 available 2 gift 3 frozen |
| change_type | tinyint | 1 | 1+充值 2-消费 3+退款 4-冻结 5+解冻 6+调增 7-调减 8+赠送发放 9+提现退回 |
| amount | decimal(12,2) | 0.00 | 正数 |
| direction | tinyint | 1 | 1 + 2 - |
| before_amount | decimal(12,2) | 0.00 | 所在池前值 |
| after_amount | decimal(12,2) | 0.00 | 所在池后值 |
| unfreeze_at | timestamp | null | 冻结类型流水的计划解冻时间 |
| related_log_id | bigint unsigned | 0 | 冻结 ↔ 解冻 互相指向 |
| source_type | varchar(30) | '' |  |
| source_id | bigint unsigned | 0 |  |
| operator_id | bigint unsigned | 0 |  |
| remark | varchar(255) | '' |  |
| created_at | timestamp | null |  |

**索引**：INDEX `(member_id, created_at)`、INDEX `(source_type, source_id)`、INDEX `(unfreeze_at)`

### 2.F 会员佣金（5 张）

#### `member_commissions`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| available | decimal(12,2) | 0.00 | 可提现 |
| frozen | decimal(12,2) | 0.00 | 待结算/提现冻结 |
| total_earned | decimal(12,2) | 0.00 | 累计获得 |
| total_withdrawn | decimal(12,2) | 0.00 | 累计提现 |
| updated_at | timestamp | null |  |

**索引**：UNIQUE `(member_id)`

#### `member_commission_logs`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| change_type | tinyint | 1 | 1+获得 2-提现冻结 3-提现结清 4+驳回退回 5+调增 6-调减 7+冻转可用 8-可转冻 |
| amount | decimal(12,2) | 0.00 |  |
| before_available | decimal(12,2) | 0.00 |  |
| after_available | decimal(12,2) | 0.00 |  |
| before_frozen | decimal(12,2) | 0.00 |  |
| after_frozen | decimal(12,2) | 0.00 |  |
| from_member_id | bigint unsigned | 0 | 下级会员 id（0=非分销） |
| relation_level | tinyint | 0 | 1/2/3；0=非分销 |
| source_type | varchar(30) | '' | order / withdraw / admin |
| source_id | bigint unsigned | 0 |  |
| rate | decimal(6,4) | 0 | 分佣率（此笔记录用） |
| settle_at | timestamp | null | 计划结算时间 |
| settled_at | timestamp | null | 实际结算时间 |
| settled_log_id | bigint unsigned | 0 | 指向结算后那条 type=7 流水 |
| operator_id | bigint unsigned | 0 |  |
| remark | varchar(255) | '' |  |
| created_at | timestamp | null |  |

**索引**：INDEX `(member_id, created_at)`、INDEX `(from_member_id)`、INDEX `(settle_at, settled_at)`

#### `member_relations`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| parent_id | bigint unsigned | 0 | 直接上级 |
| grand_parent_id | bigint unsigned | 0 | 二级上级 |
| great_grand_parent_id | bigint unsigned | 0 | 三级上级 |
| path | varchar(255) | '' | 如 "/10/5/2/" |
| bind_at | timestamp | null |  |
| bind_source | tinyint | 1 | 1 扫码 2 邀请码 3 手动 |
| created_at / updated_at | timestamp | null |  |

**索引**：UNIQUE `(member_id)`、INDEX `(parent_id)`、INDEX `(grand_parent_id)`、INDEX `(great_grand_parent_id)`

#### `member_commission_rules`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| scene | varchar(50) | 'default' | 场景（将来可扩品类） |
| rate_l1 | decimal(6,4) | 0 | 一级分佣率 |
| rate_l2 | decimal(6,4) | 0 | 二级 |
| rate_l3 | decimal(6,4) | 0 | 三级 |
| settle_days | int | 0 | 0=立即可提现，>0=T+N 结算 |
| freeze_on_create | tinyint | 0 | 0 直入 available；1 先入 frozen |
| status | tinyint | 1 |  |
| sort | int | 0 |  |
| remark | varchar(255) | '' |  |
| created_at / updated_at / deleted_at | timestamp | null |  |

**索引**：UNIQUE `(tenant_id, scene, deleted_at)`

#### `member_commission_withdrawals`

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| id | bigint unsigned AI | — |  |
| tenant_id | bigint unsigned | 0 |  |
| member_id | bigint unsigned | 0 |  |
| no | varchar(40) | '' | 申请单号（唯一） |
| amount | decimal(12,2) | 0.00 |  |
| fee | decimal(12,2) | 0.00 | 手续费 |
| actual_amount | decimal(12,2) | 0.00 | amount - fee |
| method | tinyint | 1 | 1 微信 2 支付宝 3 银行卡 4 其他 |
| account_info | json | null | 收款账号信息 |
| status | tinyint | 0 | 0 待审核 1 通过待打款 2 已打款 3 驳回 4 撤销 |
| audit_remark | varchar(500) | '' |  |
| audit_by | bigint unsigned | 0 |  |
| audit_at | timestamp | null |  |
| paid_at | timestamp | null |  |
| paid_voucher | varchar(255) | '' |  |
| reject_reason | varchar(500) | '' |  |
| log_freeze_id | bigint unsigned | 0 | 对应 type=2 冻结流水 |
| log_settle_id | bigint unsigned | 0 | 对应 type=3 结清流水 |
| created_at / updated_at | timestamp | null |  |

**索引**：UNIQUE `(tenant_id, no)`、INDEX `(member_id, status)`、INDEX `(status, created_at)`

### 2.G 系统配置键（存在已有 sys_configs 表中，不新建）

```
mbr.commission.withdraw_min        = 10.00
mbr.commission.withdraw_fee_rate   = 0.00
mbr.commission.withdraw_fee_fixed  = 0.00
mbr.points.default_expire_strategy = 0
mbr.level.allow_demotion           = 0    # 是否允许自动降级
mbr.invite.code_length             = 8
```

---

## 3. 后端分层与 API

### 3.1 Controller 清单（14 个）

| # | Controller | 资源 URL | 职责 |
|---|---|---|---|
| 1 | `MemberController` | `/members` | CRUD + verify + reset-invite + change-level + adjust-growth + import/export + 嵌套查询 |
| 2 | `MemberAccountController` | `/member-accounts` + `/members/{id}/accounts` | 第三方账号查询、解绑 |
| 3 | `MemberTagGroupController` | `/member-tag-groups` | 标签分组 CRUD |
| 4 | `MemberTagController` | `/member-tags` + `/members/{id}/tags` | 标签 CRUD + 打/摘 |
| 5 | `MemberAutoTagRuleController` | `/member-auto-tag-rules` | 自动规则 CRUD + run |
| 6 | `MemberLevelController` | `/member-levels` | 等级 CRUD |
| 7 | `MemberGrowthRuleController` | `/member-growth-rules` | 成长值规则 CRUD |
| 8 | `MemberPointsController` | `/member-points` 等 | 账户列表 + 流水 + adjust + grant |
| 9 | `MemberPointsRuleController` | `/member-points-rules` | 积分规则 CRUD |
| 10 | `MemberBalanceController` | `/member-balances` 等 | 账户 + 流水 + recharge + adjust |
| 11 | `MemberCommissionController` | `/member-commissions` 等 | 账户 + 流水 + adjust |
| 12 | `MemberCommissionRuleController` | `/member-commission-rules` | 分佣比例 CRUD |
| 13 | `MemberCommissionWithdrawalController` | `/member-commission-withdrawals` | 列表 + 审核 + 打款 |
| 14 | `MemberRelationController` | `/member-relations` + `/members/{id}/relation-tree` | 列表 + 树 + 绑定/解绑 |

### 3.2 完整路由

```php
Route::middleware(['auth:api', 'tenant'])->group(function () {
    // 会员档案（自定义路由必须在 apiResource 之前，否则 /members/{id} 会吃掉 /members/export）
    Route::post  ('members/import',                 [MemberController::class, 'import']);
    Route::get   ('members/export',                 [MemberController::class, 'export']);
    Route::apiResource('members', MemberController::class);
    Route::post  ('members/{id}/verify',            [MemberController::class, 'verify']);
    Route::post  ('members/{id}/reset-invite-code', [MemberController::class, 'resetInviteCode']);
    Route::post  ('members/{id}/change-level',      [MemberController::class, 'changeLevel']);
    Route::post  ('members/{id}/growth/adjust',     [MemberController::class, 'adjustGrowth']);
    Route::get   ('members/{id}/accounts',          [MemberAccountController::class, 'indexByMember']);
    Route::delete('member-accounts/{id}',           [MemberAccountController::class, 'destroy']);
    Route::get   ('members/{id}/level-logs',        [MemberController::class, 'levelLogs']);
    Route::get   ('members/{id}/growth-logs',       [MemberController::class, 'growthLogs']);

    // 标签
    Route::apiResource('member-tag-groups',     MemberTagGroupController::class);
    Route::post  ('member-tags/batch-attach',       [MemberTagController::class, 'batchAttach']);  // 先于 apiResource
    Route::apiResource('member-tags',           MemberTagController::class);
    Route::post  ('members/{id}/tags',              [MemberTagController::class, 'attach']);
    Route::delete('members/{id}/tags/{tagId}',      [MemberTagController::class, 'detach']);
    Route::apiResource('member-auto-tag-rules', MemberAutoTagRuleController::class);
    Route::post  ('member-auto-tag-rules/{id}/run', [MemberAutoTagRuleController::class, 'run']);

    // 等级 & 成长值
    Route::apiResource('member-levels',         MemberLevelController::class);
    Route::apiResource('member-growth-rules',   MemberGrowthRuleController::class);

    // 积分
    Route::get ('member-points',                    [MemberPointsController::class, 'index']);
    Route::get ('member-points-logs',               [MemberPointsController::class, 'logs']);
    Route::post('members/{id}/points/adjust',       [MemberPointsController::class, 'adjust']);
    Route::post('members/{id}/points/grant',        [MemberPointsController::class, 'grant']);
    Route::apiResource('member-points-rules',   MemberPointsRuleController::class);

    // 余额
    Route::get ('member-balances',                  [MemberBalanceController::class, 'index']);
    Route::get ('member-balance-logs',              [MemberBalanceController::class, 'logs']);
    Route::post('members/{id}/balance/recharge',    [MemberBalanceController::class, 'recharge']);
    Route::post('members/{id}/balance/adjust',      [MemberBalanceController::class, 'adjust']);

    // 佣金
    Route::get ('member-commissions',               [MemberCommissionController::class, 'index']);
    Route::get ('member-commission-logs',           [MemberCommissionController::class, 'logs']);
    Route::post('members/{id}/commission/adjust',   [MemberCommissionController::class, 'adjust']);
    Route::apiResource('member-commission-rules',        MemberCommissionRuleController::class);
    Route::apiResource('member-commission-withdrawals', MemberCommissionWithdrawalController::class)
        ->only(['index', 'show']);
    Route::post('member-commission-withdrawals/{id}/audit', [MemberCommissionWithdrawalController::class, 'audit']);
    Route::post('member-commission-withdrawals/{id}/pay',   [MemberCommissionWithdrawalController::class, 'pay']);

    // 关系
    Route::get ('member-relations',                 [MemberRelationController::class, 'index']);
    Route::get ('members/{id}/relation-tree',       [MemberRelationController::class, 'tree']);
    Route::post('members/{id}/relation/bind',       [MemberRelationController::class, 'bind']);
    Route::post('members/{id}/relation/unbind',     [MemberRelationController::class, 'unbind']);
});
```

### 3.3 Service 两层结构

**Layer 1 Resource Service**（14 个，1:1 对应 Controller）：仅 CRUD + 列表过滤，任何账户/等级/流水操作必须委托给 Layer 2。

**Layer 2 Domain Service**（8 个 + 1 基类，放 `app/Services/Api/Member/Domain/`）：

| Service | 方法 | 职责 |
|---|---|---|
| `AbstractAccountService` | （基类）`doWrite($memberId, $delta, $ctx)` | 行锁 + 流水模板 + 账户更新 |
| `PointsAccountService` | `grant` / `consume` / `refund` / `adjust` / `expire` / `grantByRule` | 积分账户 |
| `BalanceAccountService` | `recharge` / `consume` / `refund` / `freeze` / `unfreeze` / `adjust` / `grantGift` | 余额三池 |
| `CommissionAccountService` | `earn` / `freeze` / `settle` / `withdraw` / `refund` / `adjust` / `moveFrozenToAvailable` | 佣金账户 |
| `GrowthService` | `add` / `deduct` / `adjust` / `addByEvent` | 成长值 + 触发 `LevelService::recalculate` |
| `LevelService` | `recalculate($memberId)` / `manualChange($memberId, $levelId)` | 等级判定与变更 |
| `TagAutoRuleService` | `evaluate($rule)` / `runAll()` / `runOne($ruleId)` | 自动打标签 |
| `RelationService` | `bind($memberId, $inviteCode)` / `unbind($memberId)` / `tree($memberId, $level)` | 分销关系 |
| `WithdrawalService` | `apply` / `audit` / `pay` / `cancel` / `transitionTo` | 提现状态机 |

**强制约定**：
- 所有 Domain Service 的写方法签名：`(memberId, amount, sourceType, sourceId, operatorId, remark, extra=[])`，返回 `Log` 实例。
- 所有写操作在 `DB::transaction` + `lockForUpdate()` 内。
- 流水先写、账户后写；`before_* / after_*` 快照用锁后读出的值。
- 余额/积分/佣金不允许变为负数，扣减前检查，否则抛 `DomainException`。
- 同一业务同时动多个账户（例如消费扣余额立返积分）时，在 Controller 层顶起最外层事务，多个 Domain Service 共享同一事务。

### 3.4 事件（预留）

定义不实现 Listener：
```
App\Events\Member\MemberRegistered
App\Events\Member\MemberLevelChanged
App\Events\Member\PointsChanged
App\Events\Member\BalanceChanged
App\Events\Member\CommissionEarned
App\Events\Member\WithdrawalStatusChanged
```

### 3.5 定时任务

| 任务 | 频率 | 动作 |
|---|---|---|
| `member:tag-auto-run` | hourly | 跑所有启用的自动标签规则 |
| `member:balance-unfreeze` | 每 5 分钟 | 扫 `balance_logs.unfreeze_at <= now() AND related_log_id=0` 自动解冻 |
| `member:commission-settle` | 每 15 分钟 | 扫 `commission_logs.settle_at<=now() AND settled_at IS NULL` 冻转可用 |
| `member:tag-expire-clean` | daily 凌晨 | 删除 `member_tag_pivot.expired_at<now()` |
| `member:points-expire` | daily 凌晨 | **本期空实现**；预留 `expire_strategy>0` 时扫过期并扣减 |

所有任务配 `withoutOverlapping()`；通过原子 UPDATE 实现幂等（见 §5.4）。

### 3.6 权限 key

```
mbr:member:list / add / edit / delete / export / import / verify / reset-invite / change-level / adjust-growth
mbr:account:list / unbind
mbr:tag:list / add / edit / delete / attach / detach
mbr:tag-group:list / add / edit / delete
mbr:auto-tag-rule:list / add / edit / delete / run
mbr:level:list / add / edit / delete
mbr:growth-rule:list / add / edit / delete
mbr:points:list / logs / adjust / grant
mbr:points-rule:list / add / edit / delete
mbr:balance:list / logs / recharge / adjust
mbr:commission:list / logs / adjust
mbr:commission-rule:list / add / edit / delete
mbr:withdrawal:list / audit / pay
mbr:relation:list / tree / bind / unbind
```

### 3.7 响应约定

- 列表走 `$this->paginate()`，返回 `{ list, total, page, pageSize }`。
- Resource 使用 `data_get($this->resource, 'field')` 兼容数组返回场景。
- 金额：`decimal(12,2)` 字段由 Resource 统一 `number_format($v, 2, '.', '')` 输出字符串。
- 积分：整数直出。
- 时间：`Y-m-d H:i:s` 字符串。

---

## 4. 前端导航与页面

### 4.1 菜单树

```
会员管理 (/member, icon: UserFilled)
├── 会员列表            /member/list
├── 会员标签            /member/tag              (tab: 标签 / 分组 / 自动规则)
├── 会员等级
│   ├── 等级列表        /member/level
│   └── 成长值规则       /member/growth-rule
├── 会员积分
│   ├── 积分明细        /member/points-log
│   └── 积分规则        /member/points-rule
├── 会员余额            /member/balance          (tab: 账户 / 流水)
└── 会员佣金
    ├── 佣金账户        /member/commission       (tab: 账户 / 流水)
    ├── 分佣比例        /member/commission-rule
    ├── 提现审核        /member/withdrawal
    └── 分销关系        /member/relation
```

### 4.2 目录结构

```
frontend/src/
├── api/                       (14 个)
│   ├── member.ts
│   ├── memberAccount.ts
│   ├── memberTag.ts           (含 tag/group/attach/detach)
│   ├── memberAutoTagRule.ts
│   ├── memberLevel.ts
│   ├── memberGrowthRule.ts
│   ├── memberPoints.ts        (账户 + 流水 + adjust)
│   ├── memberPointsRule.ts
│   ├── memberBalance.ts
│   ├── memberCommission.ts
│   ├── memberCommissionRule.ts
│   ├── memberCommissionWithdrawal.ts
│   └── memberRelation.ts
├── types/api/                 (1:1 对应 api/)
├── views/member/
│   ├── list/                  会员列表 + 详情抽屉（9 Tab）+ N 个 Dialog
│   ├── tag/                   3 tab 组合页
│   ├── level/                 等级 CRUD
│   ├── growth-rule/           成长值规则 CRUD
│   ├── points-log/            积分流水明细
│   ├── points-rule/           积分规则 CRUD
│   ├── balance/               账户 + 流水 tab
│   ├── commission/            账户 + 流水 tab
│   ├── commission-rule/       分佣比例 CRUD
│   ├── withdrawal/            提现审核
│   └── relation/              列表 + 树视图
└── components/member/
    └── MemberPicker.vue       可复用选会员组件
```

### 4.3 关键页面

**会员列表**
- 搜索：手机 / 昵称 / 等级下拉 / 标签多选 / 状态 / 来源 / 注册日期区间 / 累计消费区间
- 列：头像 + 昵称/手机 / 来源 / 等级 / 成长值 / 累计消费 / 积分 / 可用余额 / 佣金余额 / 标签(前3+N) / 注册时间 / 状态 / 操作
- 行操作：查看详情（大抽屉）/ 调积分 / 调余额 / 调佣金 / 调成长值 / 改等级 / 改标签 / 实名审核 / 启/禁 / 删除
- 工具栏：新增 / 导入 / 导出 / 批量打标签 / 批量禁用
- 可按成长值 / 累计消费 / 注册时间 / 积分排序

**会员详情抽屉（9 Tab）**
基本信息 / 账号绑定 / 标签 / 等级轨迹 / 成长值流水 / 积分明细 / 余额流水 / 佣金流水 / 下级会员

**自动规则条件构建器**
`关系(all/any)` + 多条件行：`字段 / 操作符 / 值`。字段枚举同 §2.B。

**提现审核**
顶部 tab：全部 / 待审 / 待打 / 已打 / 驳回 / 撤销。Dialog：审核（通过/驳回+备注）、打款（方式+凭证上传）。

**分销关系**
视图切换：列表 / 树（`el-tree` 懒加载）。

### 4.4 共享组件

- `MemberPicker.vue`：手机 / 昵称搜索选择，分页滚动，用于所有需要挑人的场景。

### 4.5 i18n

- 新增 `lang/zh-CN/member.ts` 和 `lang/en/member.ts`。
- 所有 change_type 枚举走 `member.domain.*.change_type.N` 的 key。

---

## 5. 多租户 / 权限 / 并发一致性

### 5.1 多租户

- 所有 20 张表 `use BelongsToTenant`，不覆盖 `includeNullTenantInScope` / `includeZeroTenantInScope`，保持严格过滤。
- 唯一键一律 `(tenant_id, ...)` 开头；跨租户允许重复。
- `member_relations.parent_id` 必须同租户，FormRequest 校验。
- 查询多表 JOIN 时显式写 `where tenant_id = ?` 作为防御（Scope 兜底）。

### 5.2 权限模型

- 新增内置角色 `MEMBER_ADMIN`，默认挂全部 `mbr:*` 权限 + 会员管理菜单。
- 现有 `ADMIN` 角色默认不含会员菜单，由租户自行分配。
- `SUPER_ADMIN` 走 `isSuperAdmin()` 短路。

敏感接口需在 FormRequest.authorize() 显式检查 `hasPermissionKey`：
- 账户调整（积分/余额/佣金）
- 提现审核/打款
- 导入/导出会员

### 5.3 并发一致性

Account 写操作统一模板（在 `AbstractAccountService`）：
```php
DB::transaction(function () use (...) {
    $account = AccountModel::where('member_id', $memberId)
        ->lockForUpdate()->first() ?? $this->initAccount(...);
    $before = $account->balance;
    $after  = $before + $delta;
    if ($after < 0) throw new DomainException('api.xxx.insufficient');
    $log = LogModel::create(['before'=>$before,'after'=>$after,...]);
    $account->update(['balance'=>$after]);
    event(new XxxChanged($log));
    return $log;
});
```

### 5.4 定时任务幂等

- `balance-unfreeze`：原子更新 `related_log_id`，第二个进程 UPDATE 0 行即跳过。
- `commission-settle`：`UPDATE ... WHERE settled_at IS NULL` 原子检查。
- `tag-auto-run`：启动即更新 `last_run_at`，乐观锁。
- 所有任务 `withoutOverlapping()`。

### 5.5 超管跨租户视角

- 超管查会员必须显式选租户（顶部 `TenantSelector`），后端通过 `X-Tenant-Id` 头或 `tenant_id` 参数确定 scope；不做全租户聚合列表。
- 非超管自动锁死自己的 tenant_id。

### 5.6 软删除策略

- `members`：SoftDeletes，支持回收站；但删除前必须账户三池全为 0，否则 422。
- 账户表（points/balances/commissions）：不软删，随会员物理删除。
- 流水表：永不删除，暂无归档。
- 规则表 / 等级表 / 标签表：软删除 + `unique (tenant_id, code/name/event_key, deleted_at)` 索引。

### 5.7 错误码

```
api.member.phone_duplicate
api.member.has_balance_cannot_delete
api.points.insufficient
api.balance.insufficient
api.commission.insufficient
api.withdrawal.invalid_status
api.withdrawal.amount_too_small
api.withdrawal.amount_exceed_available
api.relation.bind_loop
api.relation.bind_self
api.relation.bind_diff_tenant
api.relation.already_bound
api.relation.parent_not_found
api.level.demotion_not_allowed
api.tag.duplicate
api.tag.expired
```

---

## 6. 关键业务流程

### 6.1 会员注册

```
MemberService::register(data)  [事务]
1. 唯一性校验 (tenant_id, phone) / (tenant_id, provider, provider_id)
2. 生成 invite_code（冲突重试 3 次）
3. INSERT members + member_accounts
4. 初始化三张账户表（points/balances/commissions）
5. 若存在 parent_invite_code → RelationService::bind
6. 触发 register 事件的 growth 和 points 规则
7. LevelService::recalculate 判定初始等级
8. event(MemberRegistered)
```

### 6.2 后台调账

```
POST /members/{id}/{points|balance|commission}/adjust
→ Request.authorize: hasPermissionKey('mbr:xxx:adjust')
→ XxxAccountService::adjust → [事务+lockForUpdate]
→ 写流水（带 before/after）→ 更新账户
```

### 6.3 等级自动升降级

```
LevelService::recalculate($memberId)  [事务+lockForUpdate members 行]
1. 读 growth_value, total_consume, level_id
2. 按 sort DESC 遍历 member_levels：
   - match_rule=1 任一 / match_rule=2 全部
   - 第一个命中 → target
3. 对比 target 与 current：
   - 相同 → noop
   - 更高 sort → 升级
   - 更低 sort → 看 mbr.level.allow_demotion 开关
4. 更新 members.level_id, level_upgraded_at
5. 写 member_level_logs
6. event(MemberLevelChanged)
```

### 6.4 会员消费扣余额立返积分（多账户联动）

```
MemberBusinessService::consume(memberId, total, balanceUsed, giftUsed, orderId)
[顶层事务]
1. BalanceAccountService::consume(...)
   → 锁 available 池 → 扣 → 写 pool=1 流水
   → 锁 gift 池 → 扣 → 写 pool=2 流水
2. PointsAccountService::grantByRule('order.paid', memberId, ctx)
3. GrowthService::addByEvent('order.paid', ...)
   → 内部触发 LevelService::recalculate
4. UPDATE members.total_consume += total
```

### 6.5 佣金完整生命周期

```
earn (订单分佣)
  → 按 commission_rules.scene 找 rate_l1/l2/l3
  → 逐代 CommissionAccountService::earn(parentN, amount, relation_level=N)
  → 若 freeze_on_create=1：入 frozen + settle_at=now+settle_days

settle (scheduler 15min)
  → 扫 settled_at IS NULL AND settle_at<=now()
  → 原子 UPDATE settled_at
  → CommissionAccountService::moveFrozenToAvailable

withdraw (会员申请)
  → 校验 amount >= min, <= available
  → 建 withdrawal 单 status=0
  → CommissionAccountService::freeze(amount) → frozen += amount, available -= amount
  → 写 log (type=2) → withdrawal.log_freeze_id

audit
  - 通过：status 0→1
  - 驳回：status 0→3 → CommissionAccountService::refund → available += amount

pay
  - status 1→2
  - CommissionAccountService::settle → frozen -= amount, total_withdrawn += amount
  - 写 log (type=3) → withdrawal.log_settle_id
  - 上传 paid_voucher

cancel (会员撤销，仅 status=0 允许)
  - status 0→4 → refund 同驳回路径
```

**不变量**：`total_earned - total_withdrawn - 调账净额 == available + frozen`

### 6.6 自动打标签

```
Scheduler member:tag-auto-run (hourly)
→ 乐观锁批量更新 rules.last_run_at
→ 对每条规则：
   1. conditions JSON → SQL (MemberQueryBuilder)
   2. SELECT id FROM members WHERE ... AND tenant_id=?
   3. diff pivot 已存在集合
   4. INSERT 新命中的 pivot (source=2)
   5. MVP 不删除「不再满足」的记录
```

### 6.7 邀请码绑定关系

```
RelationService::bind(memberId, parentInviteCode)
1. parent = members.where(tenant_id, invite_code).first()
2. 防御：
   - parent 不存在
   - parent.id == memberId
   - parent.tenant_id != member.tenant_id
   - member 已有 parent_id
   - parent.path 含 "/memberId/" → 环
3. 写 member_relations (parent_id, grand_parent_id, great_grand_parent_id, path)
4. UPDATE members.parent_invite_code
```

---

## 7. 交付 Roadmap

### Plan 1 · 会员档案 + 多渠道账号

- 表：members, member_accounts
- 后端：MemberController, MemberAccountController + 对应 Service/Request/Resource；`AbstractAccountService` 基类（先只提供抽象接口，account/log 模板方法会被 Plans 4/5/6 继承实现）
- 前端：会员列表页 + 新增/编辑 Dialog + 详情抽屉（「基本信息」「账号绑定」Tab，其余占位）
- 菜单 & 权限：MenuSeeder 注入父菜单「会员管理」+ 子菜单「会员列表」+ 按钮权限；`MEMBER_ADMIN` 角色创建
- 注意：§6.1 的「初始化三张账户表」在 Plan 1 暂不实现，Plan 4/5/6 各自负责：自己的账户表迁移 + 在 MemberService::register 里补一行 `init` 调用（用已有服务，Plan 1 register 方法留 hook 点）

**验收**
- `php artisan migrate:fresh --seed` 全绿
- `MemberTest` / `MemberAccountTest` 全 PASS（唯一性、多租户、实名流转、邀请码）
- 前端 `npm run type-check` 零错误
- 手工全链路：登录→新增→导入 5 条→编辑→审核→禁用→删除（余额 0 允许、非 0 拦截）

### Plan 2 · 会员标签

- 表：4 张
- 后端：3 个 Controller + `TagAutoRuleService`
- Scheduler：`member:tag-auto-run`（hourly）、`member:tag-expire-clean`（daily）
- 前端：`/member/tag` 3 Tab + `ConditionBuilder.vue`；详情抽屉补「标签」Tab

**验收**
- `MemberTagTest` / `MemberAutoTagRuleTest` 全 PASS
- 条件构建器能构造 all/any，JSON 与后端解析一致
- 过期标签自动不再查询返回
- 自动规则任务跑两次幂等

### Plan 3 · 会员等级 + 成长值

- 表：4 张
- 后端：`MemberLevelController` / `MemberGrowthRuleController` + `GrowthService` + `LevelService`
- `MemberController::adjustGrowth` / `changeLevel` / `levelLogs` / `growthLogs` 补齐
- 前端：2 个 CRUD 页 + 详情抽屉补 2 Tab + 列表页 2 Dialog

**验收**
- 升级多因子（match_rule=1/2）生效
- 降级开关 `mbr.level.allow_demotion` 生效
- 100 次并发 `GrowthService::add(+1)` 终值 100，流水 before/after 严格递进

### Plan 4 · 会员积分

- 表：3 张
- 后端：`MemberPointsController` + `MemberPointsRuleController` + `PointsAccountService`
- 前端：`/member/points-log`、`/member/points-rule`；详情抽屉补「积分明细」Tab；列表页加「调积分」Dialog

**验收**
- 余额不足扣减 → 422 `api.points.insufficient`
- `daily_limit` / `total_limit` 生效
- 无权限 adjust → 403
- 并发 ±100 次 → 终值正确
- `expired_at` 字段记录，过期任务空实现（TODO 注释）

### Plan 5 · 会员余额

- 表：2 张
- 后端：`MemberBalanceController` + `BalanceAccountService`
- Scheduler：`member:balance-unfreeze`（5min）
- 前端：`/member/balance` 2 Tab；详情抽屉补「余额流水」Tab；列表页 2 Dialog

**验收**
- 充值按赠送比例自动分两池
- 消费优先扣 available 再扣 gift
- gift 不可提现 / 不可转账
- 冻结到期自动解冻；重跑幂等
- 100 条混合操作对账：`total_recharged - total_consumed == available + gift + frozen - 调账净额`

### Plan 6 · 会员佣金 + 分销 + 提现（最重）

- 表：5 张
- 后端：4 个 Controller + `CommissionAccountService` + `RelationService` + `WithdrawalService`
- Scheduler：`member:commission-settle`（15min）
- Plan 1 注册流程补 `parent_invite_code` 绑定
- 前端：4 页；详情抽屉补「佣金流水」「下级会员」2 Tab

**验收**
- 关系：防环 / 跨租户拒绝 / 重复绑定拒绝 / 三代预计算正确
- 提现：状态机全分支（审核通过/驳回/撤销/打款）+ 对应账户变更与退款
- 端到端：A→B→C，C 消费 1000 按 10/5/2% → B=100, A=50（第三代 0）；B 提现 50 审批打款
- 对账：`total_earned - total_withdrawn == available + frozen`（排除调账）

### 合并策略

- 每 Plan 一个 feature 分支 + 一个 PR
- 共享文件（MenuSeeder / RoleSeeder / routes/api.php / i18n）每 Plan 只追加
- 每 Plan 基于 `/gen-module` 骨架 + 手动补业务
- 每 Plan 合完可独立 demo

---

## 8. 风险与待定项

### 已定下一期不做
- 订单系统联动（入口函数 `MemberBusinessService::consume` 已预留）
- C 端登录接口（字段已预留）
- 支付网关对接（提现只到「已打款」标记）
- 积分过期自动执行（字段已预留，任务空实现）
- 自动标签「不再满足就移除」逻辑（MVP 只做新增）

### 风险点
1. **对账性能**：流水表数据量增长后 `total_earned - total_withdrawn == available + frozen` 的校验可能超时。**缓解**：只对单个会员做实时校验；全量对账走后台异步脚本 + 进度条。
2. **等级频繁 recalculate**：每次成长值/消费变动都触发，高并发可能争锁 members 行。**缓解**：recalculate 做 noop 快速路径；后期考虑异步化。
3. **自动标签规则超时**：当会员数上万时一次性扫全表会慢。**缓解**：规则级别限流（一次最多处理 10000 会员），剩余下次继续。
4. **分销三代限制**：超过三代的场景当前直接截断不报错。**权衡**：保持数据简单，大多数分销业务三代足够；超过三代的需求走自定义实现。
5. **系统配置缓存**：`mbr.*` 配置使用频率高，需进缓存；沿用现有 `sys_configs` 的缓存策略。

### 待定
- 【提现手续费】首版走固定+比例的组合公式，由系统配置控制；如果业务要「不同金额区间不同费率」需要单独一个 `withdrawal_fee_rules` 表。**下期评估**。
- 【标签自动清理】手动打的标签是否也能设过期？目前只对自动标签支持 `expire_days`。如需手动标签也过期，UI 需加输入，schema 已支持。**下期评估**。
- 【等级权益落地】`benefits` JSON 目前只显示不执行（折扣 `discount` 字段也只是展示），真正的权益执行需要和业务场景（如订单结算）耦合。**订单模块接入时评估**。

---

## 9. 参考

- 现有模块实现范式：参考 `MemberController` 类似结构见 `UserController`、`TenantController`
- 多租户机制：`app/Scopes/TenantScope.php`、`app/Models/Traits/BelongsToTenant.php`
- 代码生成骨架：`.claude/skills/gen-module/SKILL.md`
- 已完工文档参考：`docs/superpowers/specs/2026-04-24-mall-system-design.md`
