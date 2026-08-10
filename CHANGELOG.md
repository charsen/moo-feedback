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

### 待办（M1）

- `scaffold/database/Feedback.yaml` 及其 codegen 产物（model / migration / controller / Request / Resource）
- 话题串写入机制、状态机、`Feedbackable` trait、四个领域事件
- 反垃圾三件套与脱敏访问器的落地实现
