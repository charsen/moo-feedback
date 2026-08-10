<?php declare(strict_types=1);
/*
 * 提交侧追加发言 —— 提交人在既有反馈上补充信息后派发。
 *
 * host 监听典型用途:提醒受理人「有新补充」。注意匿名访客首版无回访通道
 * (无登录态,需带令牌的回访链接,见 docs/overview.md §10),故本事件当前主要由
 * 登录用户场景触发。
 */

namespace Mooeen\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mooeen\Feedback\Models\Feedback;

class FeedbackAppended
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param Feedback $root    所属顶楼行(承载分类 / 状态 / 联系方式)
     * @param Feedback $message 本次新增的发言行
     */
    public function __construct(public Feedback $root, public Feedback $message) {}
}
