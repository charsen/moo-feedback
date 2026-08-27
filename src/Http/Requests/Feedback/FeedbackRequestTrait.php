<?php declare(strict_types=1);

namespace Mooeen\Feedback\Http\Requests\Feedback;

use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
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
            'feedback_type'         => array_keys(app(FeedbackTypeResolver::class)->types()),
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
        $types = collect(app(FeedbackTypeResolver::class)->types())
            ->mapWithKeys(static fn (array $definition, string $key): array => [
                $key => __((string) $definition['label']),
            ])
            ->all();
        $options = [
            'feedback_type'         => $types,
            'feedback_status'       => FeedbackStatus::valueLabels(),
            'feedback_speaker_side' => FeedbackSpeakerSide::valueLabels(),
        ];

        return $options[$field] ?? [];
    }
}
