<?php declare(strict_types=1);
/*
 * 分类目录默认实现 —— host 未 bind FeedbackTypeResolver 时兜底。
 *
 * 与家族其它 Null* 实现（返空 map）刻意不同：分类是**核心**不是装饰，返空等于谁也提交不了。
 * 因此这里给一条 OTHER，保证包开箱即可跑通（包自身测试、演示环境、host 尚未搭好胶水层的过渡期）。
 */

namespace Mooeen\Feedback\Support;

use Mooeen\Feedback\Contracts\FeedbackTypeResolver;

class NullFeedbackTypeResolver implements FeedbackTypeResolver
{
    public function types(): array
    {
        return [
            'OTHER' => ['label' => 'moo-feedback::model.feedback_type_other', 'sort' => 999],
        ];
    }
}
