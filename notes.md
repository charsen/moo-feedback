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

---

### 脱敏要扫历史，不是只扫工作树

- **日期**：2026-08-10
- **症状**：把 notes.md 里写死的宿主项目名与它的内部迁移状态脱敏、单独提一个 commit «xx 脱敏» 之后，`git grep` 工作树已经零命中，但 `git log -p --all | grep` 仍能翻出原文——脱敏 commit 只是又写了一遍，旧内容原封不动躺在被它修正的那个 commit 里。开源仓推出去就等于连历史一起公开。
- **根因**：把「改文件」当成了「去掉信息」。git 的每次修改都是追加，不是覆盖。
- **解法**：推之前用**两条**命令验收，缺一不可：`git grep -inE "<敏感词>"`（工作树）+ `git log -p --all --reflog | grep -inE "<敏感词>"`（全历史含悬挂对象）。仓没推过时改写零代价：`git checkout -b scrub <首次引入的 commit>` → `git checkout <脱敏 commit> -- <文件>` → `--amend` → cherry-pick 后续 commit（脱敏那条会变空，直接丢弃）。改写完必须验 `git diff <旧 HEAD> <新 HEAD> --stat` 为空（证明树完全一致，只动了历史），再 `git reflog expire --expire=now --all && git gc --prune=now` 把旧对象删净。**教训**：敏感信息在第一次 commit 前就不该落盘；已经落了就别只写脱敏 commit。

---

### 没有 gh CLI 也能设 GitHub Actions secrets：用 PHP 的 sodium

- **日期**：2026-08-10
- **症状**：新开公开镜像仓要配 `MIRROR_GITHUB_TOKEN` / `GITEE_TOKEN` 两个 secret，机器上没装 `gh`，Python 也没有 `pynacl`；`cryptography` 有 X25519 但没有 XSalsa20-Poly1305，凑不出 GitHub 要的 sealed box。
- **根因**：GitHub secrets API 要求值用仓库公钥做 libsodium `crypto_box_seal`，纯手写不现实。
- **解法**：PHP 自带 sodium 扩展，一行就够——`GET /repos/{o}/{r}/actions/secrets/public-key` 拿 `key`（base64）与 `key_id`，然后 `php -r 'echo base64_encode(sodium_crypto_box_seal(getenv("SEC"), base64_decode(getenv("PK"))));'`，再 `PUT .../actions/secrets/{NAME}` 带 `{encrypted_value, key_id}`，201 即成功。明文只经环境变量传递，不进命令行参数（`ps` 可见）也不进日志。配完 `workflow_dispatch` 触发一次镜像流水线做验收，别等 cron。

---

### 永久删除顶楼反馈必须同步清理整条话题串

- **日期**：2026-08-12
- **症状**：后台永久删除顶楼反馈接口返回 200，但其回复子行仍留在 `moo_feedbacks`，形成无法从正常列表访问的孤儿数据。
- **根因**：通用 `forceDestroyAction()` 只对当前顶楼模型调用 `forceDelete()`；表内自引用没有数据库外键级联，模型也未补话题串清理逻辑。
- **解法**：在 `Feedback` 模型手写区覆盖 `forceDelete()`；永久删除顶楼行时，在同一数据库事务内逐条永久删除 `thread()->withTrashed()` 中的全部发言，再删除顶楼行。软删除与恢复继续只作用于顶楼行，以保留可恢复的话题串。
