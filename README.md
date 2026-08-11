# moo-feedback · 意见反馈

一个 Laravel **功能模块扩展包**：把「**外部提交 → 后台受理 → 回复 → 状态流转**」这套骨架统一沉淀成一处，供多个后台项目复用。咨询、留言、反馈、建议——四种叫法，同一副骨架。

moo 系基础设施依赖 [`charsen/moo-scaffold`](https://github.com/charsen/moo-scaffold)；不直接依赖 `moo-system` 或 host `App\*`。分类目录经 `FeedbackTypeResolver` 契约由 host 声明，发言人姓名经 `SubmitterResolver` 契约读时批量解析，二者未绑定时包仍可独立运行。

> **状态**：骨架已就位（契约 / 默认实现 / 配置 / 脱敏 / CI）。表、模型与管理面属 schema-first codegen 产物，随 `scaffold/database/Feedback.yaml` 落地。设计全貌见 [`docs/overview.md`](docs/overview.md)。

## 为什么有这个包

在若干 Laravel 项目中盘点「对外收集 + 后台受理」，得到的是**同一副骨架的多种残缺实现**：提交人有的是匿名访客、有的是登录用户，两套表结构互不相通；分类有的用字符串、有的用整型，取值集合零交集；回复有的只有一个「回应结果」文本框，有的演进成了独立消息表；状态有的人工置位、有的自动翻转、有的干脆没有；反垃圾**全部缺位**。

字段命名各行其是，能力参差不齐，同一个 bug 要修多遍。骨架其实完全一致，差异全在外围。

## 技术信息

- **PHP 8.2+ / Laravel 10 · 11 · 12**，命名空间 `Mooeen\Feedback\`
- **Scaffold 驱动**：model / migration / controller / Request / Resource 由 `scaffold/database/Feedback.yaml` 经 `moo:free admin Feedback` 生成，生成后在生成区外做业务深化
- Snowflake 单例、模型基础 trait、Filter 基类、翻译合并器、操作人身份 `OperatorResolver` 均由 `moo-scaffold` 提供；本包不自持副本
- 代码格式 Pint（Laravel preset + `strict_types`），测试 Pest 3 + orchestra/testbench
- 业务表 `moo_feedbacks`（**单表自引用**话题串；软删语义含 `deleted_at`）

## 设计要点

### 单表自引用，顶楼行 = 一条反馈

`feedback_root_id` + `feedback_parent_id` 表达话题串：顶楼行（两者均 null）承载分类、状态、联系方式、多态宿主；子行只是一条发言。

这套机制学自 moo 家族的多态评论包，但**不依赖它**——评论表的「评论人 ID」是非空列（匿名访客无从表达），其姓名契约面向内部人员设计，且评论包自带的全站评论管理面会让反馈的**内部受理回复漏进公开评论列表**。

自引用同时消掉了盘点中反复出现的一个疤：把「最新回复」冗余镜像成顶楼行上的 `reply` / `replied_at` 列。**回复就是行**，不需要镜像。

### 一张表容纳两类提交人

匿名访客与登录用户的区别只在「联系方式从哪来」和「能不能站内触达」，骨架共用：

| 字段 | 作用 |
|---|---|
| `feedback_submitter_id`（可空） | 有值走用户档案；null 回落联系方式快照 |
| `feedback_speaker_side` | 提交侧 / 受理侧，与 submitter 正交 |
| `feedback_contact_name` / `_organization` / `_phone` / `_email` | 仅匿名提交时写入顶楼行 |

因提交人与受理人**同住 `feedback_submitter_id`**，姓名展示只需一个 resolver 契约即可覆盖双方。

### 字段一律带 `feedback_` 前缀

包的 `lang/*/db.php` 是扁平的「字段名 → 标签」映射，翻译合并器把各包的 `db.php` 深合并进 host——裸字段名会**跨包相撞**（家族的评论包已占了 `root_id => '顶楼评论ID'`）。故除 `id` / `deleted_at` / `created_at` / `updated_at` 外，所有语义化字段带前缀，含 `feedback_root_id` / `feedback_parent_id`。

### 分类 host 私有，状态包内封闭

| | 归属 | 类型 | 理由 |
|---|---|---|---|
| `feedback_type` | **host 定义** | `varchar(32)` | 集合无限且各家零交集，用自解释字符串；整型号段在不同 host 含义不同，库里躺着一堆 `3` 无法自解释 |
| `feedback_status` | **包定义** | `tinyint` | 集合封闭且驱动包的行为（事件、默认筛选、统计），用紧凑整型 |

状态基线：`10` 待受理 / `20` 处理中 / `30` 已完结 / `40` 已挂起 / `50` 已关闭（无效·垃圾·重复，不计入统计）。**不开放 host 扩展**——状态驱动行为，开放则行为不确定；需要更细的分期请另开字段。

「最后发言方」**不是状态**，从话题串派生，仅冗余成 `feedback_last_speaker_side` / `feedback_last_replied_at` 供列表排序预览，不参与业务判断。唯一的自动规则：`已完结` / `已关闭` 后提交侧再发言 → 自动退回 `待受理`。

### 通知不进包

包只派事件，host 监听后自行决定渠道（站内信、邮件、短信皆可）。这样包对任何通知设施零依赖，也不会因为某个 host 的通知实现绑死其他 host。

## 对外边界

| 面 | 入口 |
|---|---|
| **提交反馈（PHP）** | `Feedback::submit($attrs, $target)` —— 单一真值源：分类校验 → 反垃圾 → 落行 → 派事件 |
| **提交反馈（业务模型）** | `$product->receiveFeedback($attrs)`（`use Feedbackable` 后可用） |
| **提交反馈（HTTP）** | `POST {prefix}/feedbacks` + `GET {prefix}/feedbacks/meta`，**默认关闭**，见下 |
| **后台受理** | `api/admin/feedbacks`：列表 / 详情 / 回复 / 状态流转 / 清理，**无 store / update** |
| **分类目录** | host 实现 `Contracts\FeedbackTypeResolver`，在自己的 provider 里 bind |
| **发言人姓名** | host 实现 `Contracts\SubmitterResolver`（读时批量，防 N+1） |
| **当前操作人** | 复用 scaffold 共享 `OperatorResolver`，本包不自造 |
| **文本脱敏** | `Support\SecretRedactor`：打码 JWT / Bearer / `password=` 等凭证类模式；**刻意不打码** 手机号等 PII（打码后无法照搬复现问题，PII 由访问控制兜底） |

### 前台提交入口默认关闭

这是**匿名可写**的公开接口，装上包就悄悄多一个对外写入口是坏默认。host 显式开启：

```php
// config/moo-feedback.php
'public' => [
    'enabled'          => true,
    'required_contact' => ['feedback_contact_name', 'feedback_email'],
],
```

三个安全设计：**成功与蜜罐静默拦截返回完全相同的响应**（`201 {"submitted": true}`，不返回反馈 ID）——二者必须对脚本作者不可区分；**多态宿主只认 morph 别名**（`target=product`），不接受前端直传模型 FQN；**必填联系方式由 config 决定**，各 host 口径不同。

## 安装

```bash
composer require charsen/moo-feedback
php artisan migrate
```

本地联调可走 path 仓库：

```jsonc
// composer.json
"repositories": {
    "moo-feedback": { "type": "path", "url": "../moo-feedback", "options": { "symlink": true } }
}
```

### host 接入

1. 建胶水层 `app/Moo/Feedback/`，实现两个契约并在 `FeedbackServiceProvider` 中 bind：

```php
// app/Moo/Feedback/AppFeedbackTypes.php
public function types(): array
{
    return [
        'SALES'   => ['label' => '销售咨询', 'requires_target' => true],
        'SUPPORT' => ['label' => '技术支持'],
        'OTHER'   => ['label' => '其他'],
    ];
}
```

2. 按需发布并调整配置（环境采集开关、反垃圾阈值）：

```bash
php artisan vendor:publish --tag=moo-feedback-config
```

3. 业务模型 `use Feedbackable` 即可被反馈关联（可选，仅 `requires_target` 类分类需要）。

### 后台路由安全配置（必做）

包配置默认的 `admin` 只是兼容性路由组名，不保证 host 的该组含强制认证。host 必须在 `bootstrap/app.php` 为反馈包建立独立后台组，并让发布后的 `config/moo-feedback.php` 指向它；不要借用放行登录接口的 `admin` 或其他扩展包的组：

```php
// bootstrap/app.php -> withMiddleware()
$packageAdminMiddleware = [
    'jwt.assign.guard:admin',
    'jwt.guard.auth:admin',
    'jwt.auth.refresh',
    'throttle:admin',
    'set.locale',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
];
$middleware->appendToGroup('moo-feedback', $packageAdminMiddleware);
```

```php
// config/moo-feedback.php
'admin' => ['prefix' => 'api/admin', 'name' => 'admin.', 'middleware' => 'moo-feedback'],
```

中间件类名可按 host 调整，但完整链必须包含 admin 守卫、强制认证、续签/过期处理、限流和路由绑定。验收：匿名访问 `/api/admin/feedbacks` 返回 401，已登录但无反馈 ACL 返回 403，授权账号成功。匿名提交入口继续使用 `public.middleware`、业务限流和蜜罐，不要套后台认证组。

### 管理面前端的一个约定

moo 系包出接口与 ACL，页面由各 host 自己的管理端仓库实现。反馈的管理面比一般 CRUD 多一步：

列表的行内动作里**没有编辑笔**（包没有 update 路由，摆一支必然 404 的笔是错的），取而代之是 `handle`（受理）。host 前端需要为它渲染一个入口，点开后在同一个对话框里完成三件事：

- 展示整条话题串（`GET {prefix}/feedbacks/{id}` 的 `data` + `thread`）
- 回复：`POST {prefix}/feedbacks/{id}/reply`，body `{feedback_content}`
- 置位状态：`PATCH {prefix}/feedbacks/{id}/transition`，body `{feedback_status}`；可选值由 show 响应的 `statuses` 给出

若 host 未实现该入口，前端会显示占位文本而不是静默失效 —— 这是有意的，漏实现要看得见。

## 反垃圾

匿名提交入口不做这个，上线即被灌。包内提供三样，全部 config 可调：**提交限流**（同 IP / 同邮箱时间窗次数上限）、**蜜罐字段**（隐藏字段有值即静默丢弃）、**内容长度上下限**。

验证码**刻意不集成**——包不绑定具体服务商，host 自行在提交入口前置。

## 开发

```bash
composer ci        # pint --test + pest
composer pint      # 格式化
composer test      # 仅测试
```

## 许可

MIT © Charsen
