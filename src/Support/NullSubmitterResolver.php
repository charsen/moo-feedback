<?php declare(strict_types=1);
/*
 * 发言人姓名解析默认实现 —— host 未 bind SubmitterResolver 时兜底，返空 map（零 moo-system）。
 *
 * 姓名只是读时展示装饰：解析不到时管理面 submitter_id_txt 返 null，反馈的提交 / 受理 / 流转能力不受影响。
 */

namespace Mooeen\Feedback\Support;

use Mooeen\Feedback\Contracts\SubmitterResolver;

class NullSubmitterResolver implements SubmitterResolver
{
    public function resolveNames(array $ids): array
    {
        return [];
    }
}
