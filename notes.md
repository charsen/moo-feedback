# notes.md — 项目踩坑随手记

本文件是 `moo-feedback` 的长期踩坑记录，用于保存已经验证、以后仍可能复用的项目经验。开始排障、升级、部署或较大改动前先通读一遍。

## 记录规则

- 每条记录必须包含**症状、根因、解法、日期**。
- 只记录经过代码、测试、浏览器或实际环境验证的结论。
- 不记录临时进度、未经验证的猜测、任务流水账或通用开发知识。
- 新坑修复并验证后及时追加；如果实现机制改变，应同步修订或注明旧条目已失效。

---

## 条目模板

### 一句话概括这个坑

- **日期**：YYYY-MM-DD
- **症状**：
- **根因**：
- **解法**：

---

### 公开包不能用 SSH vcs 源引依赖

- **日期**：2026-08-10
- **症状**：家族内的私有包在 `composer.json` 里用 `"repositories": { "type": "vcs", "url": "git@gitee.com:..." }` 引 `charsen/moo-scaffold`。照抄到公开包会同时炸两处：GitHub Actions 没有 Gitee SSH key，`composer install` 直接失败；外部用户 `composer require` 也装不上。
- **根因**：SSH 源要求凭据，公开分发场景不具备。
- **解法**：先确认依赖是否已上 Packagist（`curl -s -o /dev/null -w "%{http_code}" https://repo.packagist.org/p2/<vendor>/<pkg>.json`）。`charsen/moo-scaffold` 与 `charsen/moo-monitor-laravel` 均为 200（MIT，已发布），因此**不需要 `repositories` 块**，直接走 Packagist 解析。

---

### `composer audit --locked` 不需要 lock 入库

- **日期**：2026-08-10
- **症状**：`.gitignore` 忽略了 `composer.lock`，但 CI 里跑 `composer audit --locked`，看着像必然失败。
- **根因**：误以为 `--locked` 要求 lock 文件已提交。
- **解法**：CI 中前置的 `composer install` 会在工作区生成 `composer.lock`，`audit --locked` 读的是这份生成物。库包按惯例不提交 lock，与该 CI 步骤并不冲突，无需改动。
