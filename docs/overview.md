---
title: Feedback·研发立项说明
group: 基础资料
order: 1
tags:
---

> **一句话介绍**：moo-feedback（**意见反馈**）是一套可被多个 Laravel 后台项目共享的「外部提交 → 后台受理 → 回复 → 状态流转」Composer 包——咨询 / 留言 / 反馈 / 建议四种叫法同一套骨架，支持匿名访客与登录用户两类提交人、单表自引用话题串、host 自定义分类。moo 系基础设施依赖 `charsen/moo-scaffold`；分类目录经 host 的 `FeedbackTypeResolver` 接入，不直接依赖 `moo-system`。

---

## 1. 项目定位

| 项 | 说明 |
|---|---|
| **包名 / 命名空间** | `charsen/moo-feedback` / `Mooeen\Feedback\` |
| **性质** | 开源（MIT）Laravel 功能模块扩展包，Gitee 为主仓、GitHub 镜像分发 |
| **上游依赖** | `charsen/moo-scaffold`、`tucker-eric/eloquentfilter`；Laravel 10 / 11 / 12、PHP 8.2+ |
| **消费方** | 需要「对外收集反馈并在后台受理」能力的 host 应用 |
| **解决的问题** | 「收一条外部提交、分类、受理、回复、流转状态」这套**与具体业务无关、却被各项目各写一遍**的能力，统一沉淀为一个包、一处维护、多处复用 |
| **交付形态** | 包内自带迁移 / 路由 / 统一管理面控制器 / 模型 / 机制层 trait / 契约 / 事件；host 装包 + 跑迁移 + bind 分类契约即可用 |

---

## 2. 动机：同一骨架的多套实现

在若干 Laravel 项目中盘点「对外收集 + 后台受理」这类功能，得到的是**同一副骨架的多种残缺实现**：

| 维度 | 观察到的分歧 |
|---|---|
| **提交人** | 一类是匿名访客（表单填联系方式），一类是登录用户（带账号）。两者的表结构被分别设计，无法互通 |
| **分类** | 有的用字符串常量，有的用整型枚举；取值集合彼此零交集，均为各自业务私有 |
| **回复** | 有的只有一个「回应结果」文本框（单轮），有的演进成了独立的消息表（多轮）——后者的迁移注释明确记载：单轮不够用 |
| **状态** | 有的是人工置位的三态，有的是随最后发言者自动翻转的两态，还有的干脆没有状态字段、靠「回复时间是否为空」间接判断 |
| **触达** | 有的接了站内通知，有的把提交人邮箱存进库里就再没有下文 |
| **反垃圾** | **全部缺位**。有的采集了 IP / UA，但没有任何一处做了限流、蜜罐或验证码 |

字段命名各行其是，能力参差不齐，同一个 bug 要修多遍。骨架其实完全一致：**外部提交 → 后台受理 → 回复 → 状态流转**，差异全在外围，不在骨架。

---

## 3. 表设计

单表 `moo_feedbacks`（物理前缀 `moo_`）。**顶楼行 = 一条反馈**（`feedback_root_id` / `feedback_parent_id` 均 null），**子行 = 一条发言**（访客追加或受理方回复）。

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | bigint（雪花） | 主键 |
| `feedback_root_id` | bigint 可空（索引） | 所属顶楼 ID；null = 自身是顶楼 |
| `feedback_parent_id` | bigint 可空（索引） | 直接父行 ID；null = 顶级 |
| `feedbackable_type` / `feedbackable_id` | varchar(128) / bigint 可空（联合索引） | 多态宿主（存模型 FQN）；仅顶楼行 |
| `feedbackable_title` | varchar(192) 可空 | 提交时刻宿主对象标题**写时快照**，对象改名不回溯——管理面列表免 morph eager-load |
| `feedback_type` | varchar(32) 可空 | 分类 key（host 私有，见 §5）；仅顶楼行 |
| `feedback_status` | tinyint | 受理状态（见 §6）；仅顶楼行 |
| `feedback_content` | text | 本行内容 |
| `feedback_submitter_id` | bigint 可空（索引） | 有值 = 登录用户 / 受理人；null = 匿名访客 |
| `feedback_speaker_side` | tinyint | 1 = 提交侧，2 = 受理侧 |
| `feedback_contact_name` / `feedback_organization` / `feedback_phone` / `feedback_email` | varchar 可空 | 匿名提交时的联系方式快照；仅顶楼行 |
| `feedback_ip` / `feedback_device` / `feedback_platform` / `feedback_browser` / `feedback_page_url` | varchar 可空 | 提交环境采集（可关，见 §7）；仅顶楼行 |
| `feedback_last_speaker_side` / `feedback_last_replied_at` | tinyint / timestamp 可空 | **派生缓存**，供列表排序与预览；不参与业务判断；仅顶楼行 |
| `deleted_at` / `created_at` / `updated_at` | timestamp | 软删 + 时间戳 |

索引：`feedback_root_id`、`feedback_parent_id`、`feedback_submitter_id`、`feedback_status`、`(feedbackable_type, feedbackable_id)`、`feedback_ip`（反垃圾限流查询）。

**多态命名** `feedbackable_*` 沿用 moo 家族既有约定（`commentable_*` / `collectable_*` / `likeable_*`），便于跨包认形状。

### 3.1 字段前缀口径：为什么连 `root_id` 都要带前缀

包的 `lang/*/db.php` 是**扁平的「字段名 → 标签」映射**，翻译合并器把各包的 `db.php` 深合并进 host。裸字段名因此会**跨包相撞**——家族的多态评论包已经占了 `root_id => '顶楼评论ID'`，同一个 host 同时装两个包时，后合并的会覆盖前一个的标签，管理面就会显示错误的字段名。

所以除 `id` / `deleted_at` / `created_at` / `updated_at` 这四个**标签全库一致、撞了也无害**的通用结构字段外，**一切语义化字段一律带 `feedback_` 前缀**，包括通常被当作结构字段的 `root_id` / `parent_id`。

命名不堆无意义中段：联系方式取 `feedback_organization` / `feedback_phone` 而非 `feedback_contact_organization`，与既有项目的 `inquiry_organization` / `connection_user_ip` 风格一致。

### 3.2 话题串：单表自引用，不依赖评论包

回复层级用 `feedback_parent_id` + `feedback_root_id`——这套机制学自 moo-family 的多态评论包，但**本包不依赖它**，原因是三条硬阻断：

1. 评论表的「评论人 ID」是**非空**列，匿名访客无从表达；
2. 其姓名解析契约面向**内部人员**设计，不适用于外部访客；
3. 评论包自带全站评论只读管理面，反馈的**内部受理回复会漏进公开评论列表**——这是安全问题，不是体验问题。

自引用同时消掉了盘点中反复出现的一个疤：把「最新回复」冗余镜像成顶楼行上的 `reply` / `replied_at` 列。**回复就是行**，不需要镜像。

**约束**：业务字段（分类、状态、联系方式、多态关联、环境采集）**只在顶楼行有意义**。这是约定不是数据库约束，由模型层守门——子行禁写这些字段，写入时按 `parent` 自动推 `feedback_root_id`。

---

## 4. 提交人身份：一张表容纳两类

匿名访客与登录用户的区别只在「联系方式从哪来」和「能不能站内触达」，骨架完全共用。因此不拆表，由三个字段表达：

```
feedback_submitter_id  可空 → 有值走用户档案；null 回落联系方式快照
feedback_speaker_side       → 区分提交侧 / 受理侧，与 submitter 正交
feedback_contact_name / _organization / _phone / _email
                            → 仅匿名提交时写入顶楼行
```

因为提交人与受理人**同住 `feedback_submitter_id`**（靠 `feedback_speaker_side` 区分），姓名展示只需一个 resolver 契约即可覆盖双方。

**已知限制**：匿名访客提交后无登录态，无法回来追加第二句。表结构已支持双向多轮，但要真正打通需要「带令牌的回访链接」——**首版不做**（见 §11）。首版匿名场景的实际形态是：访客发一次 + 受理方多轮记录处理过程。

---

## 5. 分类：host 私有，经契约声明

各 host 的分类取值集合零交集，包不可能预知，因此**不硬编码任何分类值**。

```php
namespace Mooeen\Feedback\Contracts;

interface FeedbackTypeResolver
{
    /** @return array<string, array{label:string, requires_target?:bool, sort?:int}> */
    public function types(): array;
}
```

host 在自己的胶水层实现并绑定：

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

设计要点：

- **用 varchar 存 key，不用整型号段**。号段在不同 host 含义完全不同，库里躺着一堆 `3` 无法自解释，跨库对数据更是灾难。
- **走容器绑定，不用 config 数组**。config 缓存序列化闭包会炸生产；容器绑定还允许 host 从自己的库表动态读分类（让运营在后台自行增减），config 做不到。
- **`requires_target` 不只是标签**。某些分类必须携带多态宿主（例如「从某个产品发起的咨询」），另一些不带。这条声明让包统一校验，而不是每个 host 各写一遍 if。
- **未绑定时默认 `OTHER => 其他`**，包开箱即跑，测试与演示不依赖 host。

**代价**：`feedback_type` 用不上 moo-scaffold yaml 的枚举约定（`desc: '{1: xx}'` → 自动生成 PHP enum + `_txt` 访问器 + 前端下拉），这几样需包自行从契约产出。

---

## 6. 状态机：人工状态与派生信号分离

盘点中出现的两套状态**性质不同**：一套是受理人手动置位的，一套是随最后发言者自动翻转的。混进同一字段必然打架——受理方回了一句但问题没解决，自动翻转会显示成「已回复」像是完事了；反过来人工标「已处理」后提交人又追一句，状态该不该退回没人说得清。

因此拆成两个概念，**只有一个是状态字段**：

**① `feedback_status` —— 人工受理状态，唯一真值**

| 值 | 常量 | 含义 |
|---|---|---|
| `10` | `PENDING` | 待受理 |
| `20` | `PROCESSING` | 处理中 |
| `30` | `RESOLVED` | 已完结 |
| `40` | `SUSPENDED` | 已挂起 |
| `50` | `CLOSED` | 已关闭（无效 / 垃圾 / 重复，不计入统计） |

**② 最后发言方不存状态**，从话题串派生，仅冗余成 `feedback_last_speaker_side` / `feedback_last_replied_at` 供列表排序与预览使用。它们是缓存，不参与业务判断。

三条约束：

- **状态封闭，不开放 host 扩展**。状态驱动包的行为（事件触发、列表默认筛选、响应率统计），一旦开放行为就不确定。host 需要更细的分期请另开字段（标签 / 优先级），不要动 status。用不到的态在管理面隐藏即可——少用不破坏确定性，扩展会。
- **`RESOLVED` / `CLOSED` 后提交侧再发言 → 自动退回 `PENDING`**。这是首版唯一的自动规则，其余全人工置位。跃迁合法性守门先不做，过度约束会卡住受理人员的实际操作。
- **`RESOLVED` 与 `CLOSED` 必须分开**。前者是有效反馈处理完，后者是垃圾 / 重复。合并会污染响应率统计——匿名入口的垃圾量不会小。

**状态类型用 tinyint 而分类用 varchar，不是疏忽**：归属方不同。分类由 host 定义（包不认识，集合无限），用自解释字符串；状态由包定义（包控制行为，集合封闭），用紧凑整型。

---

## 7. 环境采集与文本脱敏

**采集**（仅顶楼行，config 可整体关闭）：IP、设备、操作系统、浏览器、来源页面地址。用途有二——受理时复现问题，以及作为反垃圾限流的维度。

盘点中见过一个「语言」字段：写入侧恒为空字符串，从未被填充过。**不予继承**——死字段不进新表。

**脱敏**：包内提供 `SecretRedactor`，对用户自由文本中的凭证模式打码（JWT 三段式、`Bearer <token>`、`password=` / `token:` 等 key-value 形式）。这段实现移植自既有项目中已验证的版本。

它**刻意不打码手机号 / 身份证等 PII 数字**。这条是有意的：那些是 host 自有业务数据，且常作为查询值出现在 URL 路径或 SQL 字面量里，打码后受理人员无法照搬复现问题。本层只处理「泄露即可被滥用」的凭证类；PII 的可见性由「谁能访问该后台」的访问控制兜底，不靠字符串打码。

---

## 8. 反垃圾（首版必备）

匿名提交入口不做这个，上线即被灌。首版提供三样，全部 config 可调：

- **提交限流**：同 IP / 同邮箱在时间窗内的次数上限；
- **蜜罐字段**：表单隐藏字段，有值即静默丢弃；
- **内容长度上下限**：过短过长均拒。

**验证码不集成**——包不绑定具体服务商，host 自行在提交入口前置。

---

## 9. 写入口：PHP 级与 HTTP 级

写入口只有一个真值源 —— `Feedback::submit($attrs, $target)`。其余入口全部委托到它。

| 层 | 入口 | 说明 |
|---|---|---|
| **PHP** | `Feedback::submit()` | 单一真值源：分类校验 → 反垃圾 → 落行 → 派事件 |
| **PHP** | `Feedbackable::receiveFeedback()` | 业务模型上的语法糖，委托到 `submit()` 并自动带宿主 |
| **HTTP** | `POST {prefix}/feedbacks` | 前台提交，**默认关闭**（见下） |
| **HTTP** | `GET {prefix}/feedbacks/meta` | 表单渲染元信息：分类目录、蜜罐字段名、内容长度上下限、必填联系方式 |

**前台入口默认关闭**，host 须在 `config('moo-feedback.public.enabled')` 显式开启。理由：这是**匿名可写**的公开接口，装上包就悄悄多一个对外写入口是坏默认。不开启时包只提供 PHP 级入口，host 自行接管路由与响应形态。

三个安全设计：

- **成功与蜜罐静默拦截返回完全相同的响应**（`201 {"submitted": true}`，且**不返回反馈 ID**）。二者必须对脚本作者不可区分，否则等于告诉他「换个字段名再来」。不返回 ID 同时避免泄漏主键序列信息。
- **多态宿主只认 morph 别名**（`target=product`），经 `Relation::getMorphedModel()` 解析，未注册的别名一律 422。**不接受**前端直传模型 FQN —— 那等于让客户端指定要实例化哪个类。
- **必填联系方式由 config 决定**（`public.required_contact`）。各 host 口径不同（有的要企业名，有的只要邮箱），不在包里写死。

`public.middleware` 默认带 `throttle:30,1`：这是 Laravel 层的粗粒度闸门，与包内 `anti_spam` 的业务限流**互补**——前者挡暴力刷接口，后者按 IP / 邮箱限制真实提交量。

---

## 10. 契约、扩展点与事件

| 契约 | 归属 | 作用 |
|---|---|---|
| `FeedbackTypeResolver` | 本包 | 声明分类目录（见 §5）；默认实现返 `OTHER` |
| `SubmitterResolver` | 本包 | `feedback_submitter_id` → 姓名，读时批量解析，防列表 N+1；不落库不快照。因提交人与受理人同住一列，一个契约覆盖双方 |
| `OperatorResolver` | 复用 `moo-scaffold` | 「当前是谁 → id」，写入受理方发言时取操作人；本包不自造 |

**事件**（包只派事件，不投递通知）：

```
FeedbackSubmitted     顶楼行创建
FeedbackAppended      提交侧追加发言
FeedbackReplied       受理侧回复
FeedbackStatusChanged 状态变更（携带前后值）
```

**通知投递不在包内**。host 监听事件后自行决定渠道——站内铃铛、邮件、短信皆可。这样包对任何通知设施零依赖，也不会因为某个 host 的通知实现而绑死其他 host。

---

## 11. 明确不在首版范围

- **匿名访客回访令牌**（带令牌链接让访客回来追加）——表结构已支持双向多轮，缺的只是令牌机制，后补不影响表结构。
- **验证码集成**——见 §8。
- **非 moo-scaffold 栈的 host**——本包 core 与 admin 层未拆分，管理面产物面向 moo 系前端。若将来需要服务其他技术栈的 host，再评估拆分 core 的成本（模型层对 scaffold 的依赖很浅，主要是若干 trait 与一个 filter 基类）。
- **工单化能力**——SLA、派单、优先级、客服坐席。本包是「反馈」不是「工单系统」，边界要守住。

---

## 12. Phase 路线图

| 阶段 | 内容 | 完成标志 |
|---|---|---|
| **M1 包骨架** | schema yaml → codegen 产物；话题串写入机制、状态机、契约、事件、反垃圾、脱敏 | 包内 Pint + Pest 全绿，未绑定契约时可独立跑通 |
| **M2 首个 host 落地** | 在一个 greenfield host 上完整接入（无存量数据） | 提交 → 受理 → 回复 → 流转全链路在真实项目跑通；抽象若不顺手，此时修改代价为零 |
| **M3 第二个 host + 存量迁移** | 接入一个有生产数据的 host，沉淀字段映射与回填能力 | 存量数据无损迁入；迁移命令进包，后续 host 复用 |

**顺序刻意如此**：先 greenfield 再存量。用一个从未真正跑起来的抽象去猜多个消费者的需求，是这类共享包最常见的死法；moo 家族既有的 schema-first + host codegen 工作流，本就是让包与 host 咬合着长。

---

## 13. host 接入步骤

```jsonc
// composer.json
"repositories": {
    "moo-feedback": { "type": "path", "url": "../moo-feedback", "options": { "symlink": true } }
}
```

1. `composer require charsen/moo-feedback`
2. `php artisan migrate`
3. 建胶水层 `app/Moo/Feedback/`：实现 `FeedbackTypeResolver`（分类目录）与 `SubmitterResolver`（姓名解析），在 `FeedbackServiceProvider` 中 bind
4. 按需在 `config/moo-feedback.php` 调整环境采集开关与反垃圾阈值
5. 业务模型 `use Feedbackable` 即可被反馈关联（可选，仅 `requires_target` 类分类需要）
