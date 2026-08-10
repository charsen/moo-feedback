<?php declare(strict_types=1);
/*
 * 受理状态流转 —— 管理面手动置位。
 *
 * 状态取值封闭在包内(不开放 host 扩展),故直接按枚举校验;首版不做跃迁合法性守门,
 * 过度约束会卡住受理人员的实际操作(见 docs/overview.md §6)。
 */

namespace Mooeen\Feedback\Http\Requests\Feedback;

use Mooeen\Scaffold\Foundation\FormRequest;

class TransitionRequest extends FormRequest
{
    use FeedbackRequestTrait;

    public function rules(): array
    {
        return [
            'feedback_status' => ['required', 'integer', $this->getInEnums($this->getValues('feedback_status'))],
        ];
    }

    public function formLayout(): array
    {
        return [
            ['feedback_status'],
        ];
    }
}
