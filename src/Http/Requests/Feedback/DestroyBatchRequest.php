<?php declare(strict_types=1);
/*
 * @Author: Charsen <https://github.com/charsen>
 * @Date: 2026-08-10 16:58
 * @LastEditors: Charsen <https://github.com/charsen>
 * @LastEditTime: 2026-08-10 16:58
 * @Description: DestroyBatchRequest
 */

namespace Mooeen\Feedback\Http\Requests\Feedback;

use Mooeen\Scaffold\Foundation\FormRequest;
use Mooeen\Scaffold\Rules\NumericArray;

class DestroyBatchRequest extends FormRequest
{
    use FeedbackRequestTrait;

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', new NumericArray],
        ];
    }
}
