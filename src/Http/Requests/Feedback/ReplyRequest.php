<?php declare(strict_types=1);
/*
 * 受理侧回复 —— 管理面往话题串里追加一条受理方发言。
 *
 * 只收内容:发言侧固定为受理侧,发言人取 scaffold 共享 OperatorResolver(当前是谁),
 * 都不由前端传 —— 让前端决定「我是受理侧」等于把身份判定交给客户端。
 */

namespace Mooeen\Feedback\Http\Requests\Feedback;

use Mooeen\Scaffold\Foundation\FormRequest;

class ReplyRequest extends FormRequest
{
    use FeedbackRequestTrait;

    public function rules(): array
    {
        return [
            'feedback_content' => [
                'required', 'string',
                'min:' . (int) config('moo-feedback.anti_spam.content.min', 6),
                'max:' . (int) config('moo-feedback.anti_spam.content.max', 4000),
            ],
        ];
    }

    public function formLayout(): array
    {
        return [
            ['feedback_content'],
        ];
    }
}
