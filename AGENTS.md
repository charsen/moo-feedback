# AGENTS.md

本文件适用于整个 `moo-feedback` 仓库，**只写本包特有约束**。对话授权、提交 / 推送 / 打 tag / 发布、敏感信息与最小披露、
验证门禁、E2E 与浏览器验证、Composer 三份 manifest、跨仓公共契约等通用规则随全局 `~/.agents/AGENTS.md`，本文件不重复。
冲突时按「系统 / 用户当前指令 > 离目标最近的 `AGENTS.md` > 全局」判断；版本、命令与接口以当前代码、manifest 和测试核实。

> **清单双轨**：`composer.json` 是**本地优先**（`path` + `symlink`），干净克隆 / CI 用
> `COMPOSER=composer.ci.json composer update`（纯 vcs）—— 本仓开源，走 Gitee + GitHub 双源；workflow 里用 `COMPOSER=composer.ci.json`。两份清单**共用 `composer.lock`**，
> 切换后要重跑一次 `composer update`；`composer.ci.json` 已列入 `export-ignore`，不随 dist 分发。

## 开工顺序

1. 按任务读取真实入口：
   - 包定位与对外边界：`README.md`、`docs/overview.md`、`composer.json`
   - 表结构与生成语义：`scaffold/database/Feedback.yaml`、`database/migrations/`、`src/Models/`
   - 写入口与受理链路：`src/Models/Feedback.php`、`src/Http/Controllers/Admin/FeedbackController.php`、`routes/`
   - 跨包契约与升级：sibling `moo-contract/docs/organization-upgrade.md`、本包 `src/Contracts/`
2. 改文件前完整阅读目标文件和直接调用链；涉及公共符号、路由、config key、字段或 host 契约时，同时 grep 已知消费方。

## 协作边界

- 优先最小改动，复用现有 model 入口、Support、Event、Request 与 scaffold 抽象；不要绕过 `Feedback::submit()` 重写一套写路径。
- 没有现行先例、业务语义不明或选择会改变数据含义时停止询问，不自行发明需求。
- 计划外问题只报告，不顺手修改。
- 不主动新建 plan、审查报告或决策文档；用户明确要求时才创建。

## 项目定位与边界

- 本仓库是 Laravel Composer 扩展包 `charsen/moo-feedback`，命名空间 `Mooeen\Feedback\`，业务表 `moo_feedbacks`（MIT，GitHub 镜像分发）。
- 它把「**外部提交 → 后台受理 → 回复 → 状态流转**」这套骨架统一沉淀成一处，供多个后台项目复用；咨询 / 留言 / 反馈 / 建议是同一副骨架的四种叫法。
- 运行时支持范围以 `composer.json` 为准：PHP 8.2+、Laravel 10/11/12。包内 Testbench 主要覆盖 Laravel 12，不能把单一测试矩阵误写成全部运行时兼容性已验证。
- 包直接依赖 `charsen/moo-scaffold`、`charsen/moo-contract`、`tucker-eric/eloquentfilter`。**不依赖 `moo-system`，也不依赖任何 host `App\*`**。
- 包根目录没有 `artisan`。`php artisan` 与真实 HTTP smoke 必须在以 path repository 接入本包的消费方 host 中执行。
- 目录职责：`src/` 业务实现，`routes/admin.php` 后台路由、`routes/web.php` 前台提交入口，`database/migrations/` 空库可复现结构，`config/` 可发布默认配置，`lang/` 合并语言包，`scaffold/database/Feedback.yaml` schema 源，`tests/` 包级回归。

## Schema、migration 与生成区

- 字段结构先改 `scaffold/database/Feedback.yaml`，再在 path repository 接入本包的 host 中运行 `moo:free admin Feedback`（或单步 `moo:model` / `moo:migration`）；生成产物必须落在包仓。
- `src/Models/Traits/*Trait.php`、常规 Request、Filter、Enum、migration、lang 和资源路由属于**生成区**，禁止手改。业务深化放在 model 手写区、controller、独立 Request、Support、Event 或测试中。
- **机制 trait 目前位于 `src/Models/Concerns/`**（`Feedbackable`）。家族规范位置是 `src/Models/Traits/`（codegen 硬编码 emit `use {ns}Traits\...`），迁移属破坏性 namespace 变更，未获明确批准前不要移动。
- 迁移 diff 的基准是 `scaffold/database/.snapshots/Feedback.yaml`，不是数据库现状或 migration 文件是否存在。
- 除 `id` / `deleted_at` / `created_at` / `updated_at` 外，**一切语义化字段带 `feedback_` 前缀**（含 `feedback_root_id` / `feedback_parent_id`）。理由：`lang/*/db.php` 是扁平的「字段名 → 标签」映射，翻译合并器把各包 `db.php` 深合并进 host，裸字段名会跨包相撞（家族评论包已占用 `root_id`）。
- 枚举的输入只能写在表级 `enums:` 块；字段 `desc` 里的 `{值: 标签}` 是生成物，不是输入。

## Host 契约与兼容性

- **分类目录**：host 实现 `Mooeen\Feedback\Contracts\FeedbackTypeResolver` 并在自己的 provider 里 bind；未绑定时 `Support\NullFeedbackTypeResolver` 只返 `OTHER`，保证包开箱可跑。分类用 `varchar(32)` 存 key，**不用整型号段**——号段在不同 host 含义不同，库里躺着一堆 `3` 无法自解释。
- **姓名展示**：消费公共 `Mooeen\Contract\PersonnelNameResolver`（批量 `resolveNames()`，读时解析、不落库）。该契约**没有默认实现，未绑定即显式失败**——这是刻意的，空实现会把全站人名静默变空白。旧的本包 `SubmitterResolver` 及其空实现已删除。
- **当前操作人**：复用 scaffold 共享 `Mooeen\Scaffold\Contracts\OperatorResolver`，本包**不自造**身份契约。
- 三者不可互相替代，也不要用 `function_exists` 之类嗅探兜底：分类是「目录」，姓名是「读时展示」，身份是「写时取当前人」。
- **后台中间件组**：config 默认的 `admin` 只是兼容性路由组名，不保证 host 的该组含强制认证。host 必须为反馈包建立独立组（如 `moo-feedback`）并让发布后的 config 指向它，不得借用放行登录接口的 `admin` 或其他扩展包的组。验收：匿名 401 / 已登录无 ACL 403 / 授权成功。
- **管理面前端不在包内**。包出接口与 ACL，页面由各 host 管理端实现；列表行内动作没有编辑笔（包没有 update 路由），取而代之是 `handle`（受理）。响应形状或路由变化时同步验证 host 页面，不下发不存在路由的动作。
- 包的 Admin 控制器必须登记到 host `controller.admin.extra_modules` 后才会进入 ACL / API 元数据；模块或 action 名变化会改变持久化 action key，必须提供 host 适配清单。
- 公共契约调整前后，分别验证「未实现的默认 host」与「个性化 host」，不能只验证提出需求的消费者。

## 业务硬约束

1. **单表自引用话题串**：顶楼行（`feedback_root_id` / `feedback_parent_id` 均 null）= 一条反馈，承载分类 / 状态 / 联系方式 / 多态宿主 / 环境采集；子行 = 一条发言。回复就是行，**不把「最新回复」镜像成顶楼行冗余列**。
2. 业务字段只在顶楼行有意义。这是约定而非数据库约束，由模型层守门：子行禁写这些字段，写入时按 `parent` 自动推 `feedback_root_id`。
3. `feedback_status` **包内封闭，不开放 host 扩展**（`10` 待受理 / `20` 处理中 / `30` 已完结 / `40` 已挂起 / `50` 已关闭）。它驱动事件、默认筛选与统计，开放则行为不确定；host 需要更细分期请另开字段。
4. `feedback_last_speaker_side` / `feedback_last_replied_at` 是**派生缓存**，不参与业务判断，不得当成状态使用。
5. 唯一的自动规则：`已完结` / `已关闭` 后提交侧再发言 → 自动退回 `待受理`。跃迁合法性守门刻意不做。
6. 提交人与受理人**同住 `feedback_submitter_id`**，靠 `feedback_speaker_side` 区分，故姓名展示只需一个 resolver 契约。
7. **永久删除顶楼行必须同步清理整条话题串**：在 `Feedback::forceDelete()` 手写区内、同一事务中先逐条永久删除 `thread()->withTrashed()` 的全部发言，再删顶楼行。软删与恢复继续只作用于顶楼行，以保留可恢复的话题串。
8. **前台提交入口默认关闭**（`config('moo-feedback.public.enabled')`）——它是匿名可写的公开接口，装上包就多一个对外写入口是坏默认。开启时守住三条：成功与蜜罐静默拦截返回**完全相同**的响应且不返回反馈 ID；多态宿主**只认 morph 别名**（经 `Relation::getMorphedModel()` 解析），不接受前端直传模型 FQN；必填联系方式由 `public.required_contact` 决定。
9. 反垃圾（限流 / 蜜罐 / 内容长度）是匿名入口的必备项，不可整体关闭；验证码刻意不集成，由 host 前置。
10. `Support\SecretRedactor` 只打码**凭证类**模式（JWT、`Bearer <token>`、`password=` 等）；**刻意不打码手机号 / 身份证等 PII**——那些常作为查询值出现在 URL 与 SQL 字面量里，打码后无法照搬复现问题，其可见性由访问控制兜底。
11. 通知**不进包**：包只派事件（`FeedbackSubmitted` / `FeedbackAppended` / `FeedbackReplied` / `FeedbackStatusChanged`），host 监听后自行决定渠道。

## 代码风格与安全

- 对话、文档、注释与 commit message 中文优先，跟随现有文件风格。
- PHP 遵循根目录 `pint.json`，保留 `declare(strict_types=1)` 与单行 `<?php declare(...)` 风格。
- 分类、姓名、身份三者只经契约获取；不要在包内硬编码 host 业务值，也不要新增包私有 resolver 绕开既有契约。
- 简单业务守卫优先内联；只有逻辑复杂、确有多个入口共同演进时才抽 Support / trait。`Feedback::submit()`、`AntiSpamGuard` 等既有单一真值源不得复制。

## 验证门禁

- 文档-only 改动至少运行 `git diff --check`，并核对链接、路径与命令是否与现码一致。
- PHP 改动开发中按需先跑目标 Pest；最终代码状态执行 `composer ci`（= `pint --test` + `pest`）。`pint --dirty` 会改写文件，只读检查必须带 `--test`；需要格式化时只处理本任务的显式文件并复核 diff。
- Testbench / SQLite 不能证明 MySQL、真实 admin 中间件顺序、ACL、morph 别名解析、前台入口与前端契约正确。
- 涉及 model、Request、controller action、route、config key、schema / migration 或公共响应时，除包内测试外还要在受影响 host 执行相应真实测试；未验证项必须如实说明。
- 变更 Laravel 兼容面时按 `composer.json` 的支持范围验证；只跑 Laravel 12 不得宣称 Laravel 10/11 已通过。
