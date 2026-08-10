<?php declare(strict_types=1);
/*
 * 前台提交校验 —— 规则由 config 驱动,不在包里写死各 host 的口径。
 *
 * 分类是否在目录里、requires_target 是否满足,**不在这里校验**:那是 Feedback::submit() 的职责,
 * 放两处会漂移。这里只管「格式对不对」,不管「业务上允不允许」。
 *
 * 蜜罐字段刻意**不列入规则**:它不该出现在 validated() 里(否则会被当业务字段往下传),
 * 控制器从原始请求单独取值喂给 AntiSpamGuard。
 */

namespace Mooeen\Feedback\Http\Requests\Feedback;

use Mooeen\Scaffold\Foundation\FormRequest;

class SubmitRequest extends FormRequest
{
    use FeedbackRequestTrait;

    public function authorize(): bool
    {
        return true;    // 公开入口:任何人可提交,门槛由反垃圾与 throttle 中间件把守
    }

    public function rules(): array
    {
        $min = (int) config('moo-feedback.anti_spam.content.min', 6);
        $max = (int) config('moo-feedback.anti_spam.content.max', 4000);

        $rules = [
            'feedback_type'         => ['required', 'string', 'max:32'],
            'feedback_content'      => ['required', 'string', "min:{$min}", "max:{$max}"],
            'feedback_contact_name' => ['nullable', 'string', 'max:100'],
            'feedback_organization' => ['nullable', 'string', 'max:200'],
            'feedback_phone'        => ['nullable', 'string', 'max:30'],
            'feedback_email'        => ['nullable', 'email', 'max:200'],
            'feedback_page_url'     => ['nullable', 'string', 'max:512'],
        ];

        if (config('moo-feedback.public.allow_target', true)) {
            // 只收 morph 别名 + ID,别名到类的映射由 host 的 Relation::morphMap() 决定
            $rules['target']    = ['nullable', 'string', 'max:64'];
            $rules['target_id'] = ['nullable', 'numeric', 'min:0', 'required_with:target'];
        }

        foreach ((array) config('moo-feedback.public.required_contact', []) as $field) {
            if (isset($rules[$field])) {
                $rules[$field][0] = 'required';
            }
        }

        return $rules;
    }

    public function formLayout(): array
    {
        return [
            ['feedback_type'],
            ['feedback_content'],
            ['feedback_contact_name'],
            ['feedback_organization'],
            ['feedback_phone'],
            ['feedback_email'],
        ];
    }
}
