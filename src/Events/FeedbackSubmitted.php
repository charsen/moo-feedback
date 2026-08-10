<?php declare(strict_types=1);
/*
 * 反馈已提交 —— 顶楼行创建后派发。
 *
 * 包只派事件、不投递通知:host 监听后自行决定渠道(站内信 / 邮件 / 短信)。
 * 这样包对任何通知设施零依赖,也不会因为某个 host 的通知实现绑死其他 host。
 */

namespace Mooeen\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mooeen\Feedback\Models\Feedback;

class FeedbackSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Feedback $feedback) {}
}
