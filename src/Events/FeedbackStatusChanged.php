<?php declare(strict_types=1);
/*
 * 受理状态变更 —— 携带前后值,便于 host 做审计 / 统计 / 条件通知。
 *
 * 触发来源有二:受理人手动置位,以及包内唯一的自动规则
 * (已完结 / 已关闭后提交侧再发言 → 自动退回待受理)。$automatic 区分二者。
 */

namespace Mooeen\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mooeen\Feedback\Models\Enums\FeedbackStatus;
use Mooeen\Feedback\Models\Feedback;

class FeedbackStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param Feedback       $feedback  顶楼行(状态只在顶楼行有意义)
     * @param FeedbackStatus $from      变更前状态
     * @param FeedbackStatus $to        变更后状态
     * @param bool           $automatic true = 包内自动规则触发;false = 受理人手动置位
     */
    public function __construct(
        public Feedback $feedback,
        public FeedbackStatus $from,
        public FeedbackStatus $to,
        public bool $automatic = false,
    ) {}
}
