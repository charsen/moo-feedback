# Changelog

本项目遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

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
- 中英词条 `lang/{zh-CN,en}/{db,model,validation}.php`
- 测试 61 项（骨架 / 机制层 / 反垃圾 / 管理面路由面 / 前台提交）

### Changed

- ServiceProvider 挂上管理面路由组（此前为 M1 待办占位）

### 待办

- 匿名访客回访令牌（表结构已支持双向多轮，缺令牌机制）
- 首个 host 落地验证（M2）与存量迁移能力（M3）
