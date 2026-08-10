<?php declare(strict_types=1);
/*
 * 受理侧回复 —— 受理人在反馈上发言后派发。
 *
 * host 监听典型用途:通知提交人「你的反馈有新回复」。匿名提交(顶楼行 feedback_submitter_id 为 null)
 * 无站内触达通道,host 可改用顶楼行的 feedback_email / feedback_phone 外发。
 */

namespace Mooeen\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mooeen\Feedback\Models\Feedback;

class FeedbackReplied
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param Feedback $root    所属顶楼行(承载分类 / 状态 / 联系方式)
     * @param Feedback $message 本次新增的回复行
     */
    public function __construct(public Feedback $root, public Feedback $message) {}
}
