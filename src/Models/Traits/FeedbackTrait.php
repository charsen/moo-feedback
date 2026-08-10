<?php declare(strict_types=1);

namespace Mooeen\Feedback\Models\Traits;

use Mooeen\Feedback\Models\Enums\FeedbackSpeakerSide;
use Mooeen\Feedback\Models\Enums\FeedbackStatus;

/**
 * FeedbackTrait
 *
 * - 会被生成直接覆盖，所以不要在这里写代码
 */
trait FeedbackTrait
{
    /**
     * 获取 受理状态 TXT
     */
    public function getFeedbackStatusTxtAttribute(): ?string
    {
        try {
            return FeedbackStatus::from((int) $this->feedback_status)->label();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 获取 发言侧 TXT
     */
    public function getFeedbackSpeakerSideTxtAttribute(): ?string
    {
        try {
            return FeedbackSpeakerSide::from((int) $this->feedback_speaker_side)->label();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
