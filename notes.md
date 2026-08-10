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

### yaml 里的 `desc: '{1: xx}'` 是生成物，不是输入

- **日期**：2026-08-10
- **症状**：照着 `storage/scaffold/<table>.php` 缓存里看到的 `'desc' => '{10: 待受理, 20: 处理中}'` 往 schema yaml 的字段上写，跑 `moo:fresh` 后缓存里的 `enums` 块是空数组，`src/Models/Enums/` 一个文件都不生成，模型 `$appends` 里声明的 `_txt` 访问器因此全是死的。
- **根因**：把生成物当成了输入。`desc` 那串 `{值: 标签}` 是生成器**从 enums 块反向写出来**的展示用文本。
- **解法**：枚举的真正输入是表级的独立 `enums:` 块，形状 `<field>: { <case_key>: [ value, label_en, label_zh ] }`；`case_key` 决定 PHP 枚举的 case 名（`pending` → `PENDING`）。带枚举的字段本身不要手写 `desc`。

---

### 迁移 diff 是跟 `.snapshots/` 比的，不是跟数据库比

- **日期**：2026-08-10
- **症状**：改完 schema，删掉包里已生成的迁移文件、并 drop 掉数据库里的表，重跑 `moo:free` 仍然报「migration 阶段：无变更，跳过」，拿不到重新生成的完整 create 迁移。
- **根因**：`SchemaDiffService` 比对的基准是 `scaffold/database/.snapshots/<Schema>.yaml`（上次生成时的 schema 快照），与数据库现状和迁移文件都无关。
- **解法**：要重出完整 create 迁移，得把 `.snapshots/` 一并删掉。包发布前想要「一条干净的 create 迁移」而不是 create + 若干 diff，就用这个办法重置。

---

### 宿主里 host 与扩展包重复定义同名表会让 `moo:fresh` 整条挂掉

- **日期**：2026-08-10
- **症状**：借某个 moo host 当 codegen 宿主时，`moo:fresh` 抛 `表名跨 schema 重复：[xxx] 已在 [host] 源定义，又出现于 [某包]`，整条生成管线不可用——本包的 yaml 明明没问题也跑不了。
- **根因**：宿主自身的模块包化迁移做了一半：host 的 `scaffold/database/*.yaml` 没删，与包里的同名 schema 重复定义了若干张表。工具对「表名全局唯一」是 fail-fast，不做 last-wins 兜底。
- **解法**：临时把 host 侧那份 yaml 挪走再跑生成（包里的 schema 通常是超集，ACL / i18n 不会丢条目），生成完放回。根治要由宿主项目自己删掉 host 侧的重复 yaml。**教训**：借别人的项目当 codegen 宿主前，先跑一次 `moo:fresh` 确认它的管线是通的。

