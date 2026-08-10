<?php declare(strict_types=1);

namespace Mooeen\Feedback\Models\Enums;

use Mooeen\Scaffold\Concerns\EnumExtend;

/**
 * 反馈 模型的 受理状态 字段枚举
 */
enum FeedbackStatus: int
{
    use EnumExtend;

    case PENDING    = 10;
    case PROCESSING = 20;
    case RESOLVED   = 30;
    case SUSPENDED  = 40;
    case CLOSED     = 50;

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::PENDING    => __('model.feedback_status_pending'),
            self::PROCESSING => __('model.feedback_status_processing'),
            self::RESOLVED   => __('model.feedback_status_resolved'),
            self::SUSPENDED  => __('model.feedback_status_suspended'),
            self::CLOSED     => __('model.feedback_status_closed'),
        };
    }
}
