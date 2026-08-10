<?php declare(strict_types=1);
/*
 * @Author: Charsen <https://github.com/charsen>
 * @Date: 2026-08-10 16:58
 * @LastEditors: Charsen <https://github.com/charsen>
 * @LastEditTime: 2026-08-10 16:58
 * @Description: IndexRequest
 */

namespace Mooeen\Feedback\Http\Requests\Feedback;

use Mooeen\Scaffold\Foundation\FormRequest;

class IndexRequest extends FormRequest
{
    use FeedbackRequestTrait;

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'feedback_root_id'      => ['nullable', 'numeric'],
            'feedback_parent_id'    => ['nullable', 'numeric'],
            'feedback_status'       => ['nullable', 'integer', $this->getInEnums($this->getValues('feedback_status'))],
            'feedback_submitter_id' => ['nullable', 'numeric'],
            'feedback_speaker_side' => ['nullable', 'integer', $this->getInEnums($this->getValues('feedback_speaker_side'))],
            'feedback_ip'           => ['nullable', 'string', 'max:64'],
            'page'                  => ['required', 'integer', 'min:1'],
            'page_limit'            => ['required', 'integer', 'min:1'],
        ];
    }
}
