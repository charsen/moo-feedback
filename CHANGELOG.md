# Changelog

本项目遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

### Fixed

- 管理列表正式接受并校验 Host 声明的 `feedback_type`，类型筛选改为精确匹配；状态与类型搜索元数据现在使用真实后端字段名，避免前端传入 `status/type` 后被验证层静默丢弃。

## [0.1.0] - 2026-08-11

首个发布版本。「外部提交 → 后台受理 → 回复 → 状态流转」这套骨架此前在各 host 里各写一遍，本包把它收成一处。

已在两个 host 落地验证：**tcaweb-v2**（1.0.9，存量 `content_connections` 迁移过来，真实数据跑通）与 **xing-ke-homepage**（greenfield 官网咨询，合作 / 采购 / 研学三类）。

### Added

- 包骨架：composer 元数据（MIT）、Pint / Pest 配置、CI 工作流（quality + Gitee 镜像）
- `Contracts\FeedbackTypeResolver` —— 分类目录声明契约（host 私有分类，走容器绑定）
- `Contracts\SubmitterResolver` —— 发言人姓名读时批量解析契约（提交人与受理人同住一列，一个契约覆盖双方）
- `Support\NullFeedbackTypeResolver` / `Support\NullSubmitterResolver` —— 默认实现，未绑定时包可独立跑通
- `Support\SecretRedactor` —— 用户自由文本的凭证类模式打码（JWT / Bearer / key=value），刻意不打码 PII
- `config/moo-feedback.php` —— 后台路由挂载、环境采集开关、反垃圾阈值、脱敏开关
- `docs/overview.md` —— 研发立项说明（表设计、四项设计决策、契约与事件、路线图）
- `scaffold/database/Feedback.yaml` —— schema 真值源；表 `moo_feedbacks` 单表自引用话题串
- 迁移 `create_moo_feedbacks_table` —— 22 字段 / 6 索引；除 `id`、`deleted_at`、`created_at`、`updated_at` 外全部带 `feedback_` 前缀，避免与其它包的 `db.php` 词条相撞
- `Models\Feedback` —— 写入口 `submit()`、话题串 `appendMessage()`、状态机 `transitionTo()`、`roots()` 作用域、子行业务字段守门、内容读时脱敏访问器
- `Models\Concerns\Feedbackable` —— 业务模型挂载即可被反馈关联，含标题写时快照
- `Models\Enums\FeedbackStatus`（待受理 / 处理中 / 已完结 / 已挂起 / 已关闭）与 `FeedbackSpeakerSide`
- 四个领域事件：`FeedbackSubmitted` / `FeedbackAppended` / `FeedbackReplied` / `FeedbackStatusChanged`
- `Support\AntiSpamGuard` 反垃圾三件套（限流 / 蜜罐 / 长度）与 `Support\EnvironmentCapture` 环境采集
- 异常 `SpamRejected`（区分静默拦截）与 `InvalidFeedbackType`
- 管理面 `Admin\FeedbackController` + `routes/admin.php`：只读 + 清理 + 受理（reply / transition），**无 store / update / create / edit**
- 前台提交入口 `Web\FeedbackController` + `routes/web.php` + `SubmitRequest`：`POST {prefix}/feedbacks` 与 `GET {prefix}/feedbacks/meta`，**默认关闭**需 host 显式开启；蜜罐静默拦截与成功返回不可区分、不返回反馈 ID、多态宿主只认 morph 别名
- `Feedback::$feedback_type_txt` —— 分类展示名经 `FeedbackTypeResolver` 解析。分类是 host 私有的 varchar key、没有 enums 块，codegen 产不出这个访问器；目录里查不到的 key 原样回显而不返空白（历史分类被撤下时，显示 `LEGACY_X` 好过显示空白）
- `Feedback::$feedback_last_speaker_side_txt` —— 派生缓存列的展示名（与 `feedback_speaker_side` 同枚举但属另一列，生成器不为它产 _txt）。两个访问器经业务区的 `EXTRA_APPENDS` 并入 `$appends`，不改生成区，`moo:free --force` 重生成不会冲掉
- `feedbackable_title` 支持由调用方经 `$attrs` 显式给出，优先于宿主的 `feedbackTitle()`：宿主未必是 host 自家模型（如从 `moo-product` 的产品发起采购咨询），host 加不了 `Feedbackable` trait，也不该为一个标题去继承别人的包模型
- 中英词条 `lang/{zh-CN,en}/{db,model,validation}.php`
- 测试 67 项（骨架 / 机制层 / 反垃圾 / 管理面路由面与表头 / 前台提交）

### 管理面的两处刻意偏离 codegen 默认

首个 host 落地时暴露、已在本版修正，记下来免得下次重生成又被打回默认：

- **列表裁成受理视图**：codegen 原样输出的表头是 20 列全字段倾倒，含 `feedback_root_id` / `feedback_parent_id`（列表只出顶楼行，这两列恒为 null）与访客 IP 等环境采集，却唯独没查 `feedback_content` —— 受理人员每条都得点进去才知道是什么事。现列表只留分类 / 提交人 / 内容预览 / 状态 / 最后发言方与时间
- **行内动作去掉编辑笔**：scaffold 的 `Optional` 默认无条件给 `edit`，但本包刻意没有 update / edit 路由，那支笔点下去必然 404。改为 `handle`（受理）—— 看话题串、回复、置位状态一处入口。host 前端须渲染 `#option_handle` 插槽

### 待办

- 匿名访客回访令牌（表结构已支持双向多轮，缺令牌机制）
- **管理面模块名写死在 `@module_name`，host 覆盖不了** —— ACL 与菜单一律显示「意见反馈」，但同一套骨架在 xing-ke 的语境是「咨询」。要么开一个 config 覆盖点，要么承认这是包名语义的一部分
- 存量迁移目前由各 host 自己写命令（如 tcaweb-v2 的 `app:migrate-connections`），包不提供通用骨架。再来一两个 host 后再看值不值得上收
