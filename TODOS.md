# TODOS

本文件集中记录当前尚未完成且可执行的项目待办。复杂方案应链接到正式 plan；完成后及时勾选或清理。

## Plan 72：公共组织契约交付

- [ ] 本地改造完成后按依赖顺序发布，核对含 OrgDirectory 的 moo-contract、System 默认接线及消费包最低版本，再同步 Host 三 profile；不得以当前旧约束直接发布/部署。兼容组合与验证记录见 sibling `某个内部 Host/plans/72-moo-contract-unification.md`，接入说明见 `moo-contract/docs/organization-upgrade.md`。

## 后台列表补充字段

- [ ] 发布包含 `admin.list_extra_fields` 的稳定版本后，由需要机构列的 Host 更新依赖并配置；在目标环境验证列表与回收站字段，同时保持未配置 Host 的默认裁剪行为。
