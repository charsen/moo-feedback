<?php declare(strict_types=1);

namespace Mooeen\Feedback\Models\Enums;

use Mooeen\Scaffold\Concerns\EnumExtend;

/**
 * 反馈 模型的 发言侧 字段枚举
 */
enum FeedbackSpeakerSide: int
{
    use EnumExtend;

    case SUBMITTER = 1;
    case HANDLER   = 2;

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::SUBMITTER => __('model.feedback_speaker_side_submitter'),
            self::HANDLER   => __('model.feedback_speaker_side_handler'),
        };
    }
}
