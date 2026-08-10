<?php declare(strict_types=1);

namespace Mooeen\Feedback\Http\Requests\Feedback;

use Mooeen\Feedback\Models\Enums\FeedbackSpeakerSide;
use Mooeen\Feedback\Models\Enums\FeedbackStatus;

trait FeedbackRequestTrait
{
    public function getTable(): string
    {
        return 'moo_feedbacks';
    }

    public function getValues(string $field): array
    {
        $values = [
            'feedback_status'       => FeedbackStatus::values(),
            'feedback_speaker_side' => FeedbackSpeakerSide::values(),
        ];

        return $values[$field] ?? [];
    }

    /**
     * 控制器生成前端表单控件时，获取 options 选项数据
     */
    public function options(string $field): array
    {
        $options = [
            'feedback_status'       => FeedbackStatus::valueLabels(),
            'feedback_speaker_side' => FeedbackSpeakerSide::valueLabels(),
        ];

        return $options[$field] ?? [];
    }
}
