<?php declare(strict_types=1);
/*
 * @Author: Charsen <https://github.com/charsen>
 * @Date: 2026-08-10 16:58
 * @LastEditors: Charsen <https://github.com/charsen>
 * @LastEditTime: 2026-08-10 16:58
 * @Description: 反馈 资源
 */

namespace Mooeen\Feedback\Http\Resources;

use Illuminate\Http\Request;
use Mooeen\Scaffold\Foundation\BaseResource;

class FeedbackResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = collect([
            'id'                         => $this->id,
            'feedback_root_id'           => $this->feedback_root_id,
            'feedback_parent_id'         => $this->feedback_parent_id,
            'feedbackable_type'          => $this->whenHas('feedbackable_type'),
            'feedbackable_id'            => $this->whenHas('feedbackable_id'),
            'feedbackable_title'         => $this->whenHas('feedbackable_title'),
            'feedback_type'              => $this->whenHas('feedback_type'),
            'feedback_status'            => $this->feedback_status,
            'feedback_status_txt'        => $this->whenAppended('feedback_status_txt'),
            'feedback_content'           => $this->whenHas('feedback_content'),
            'feedback_submitter_id'      => $this->feedback_submitter_id,
            'feedback_speaker_side'      => $this->whenHas('feedback_speaker_side'),
            'feedback_speaker_side_txt'  => $this->whenAppended('feedback_speaker_side_txt'),
            'feedback_contact_name'      => $this->whenHas('feedback_contact_name'),
            'feedback_organization'      => $this->whenHas('feedback_organization'),
            'feedback_phone'             => $this->whenHas('feedback_phone'),
            'feedback_email'             => $this->whenHas('feedback_email'),
            'feedback_ip'                => $this->feedback_ip,
            'feedback_device'            => $this->whenHas('feedback_device'),
            'feedback_platform'          => $this->whenHas('feedback_platform'),
            'feedback_browser'           => $this->whenHas('feedback_browser'),
            'feedback_page_url'          => $this->whenHas('feedback_page_url'),
            'feedback_last_speaker_side' => $this->whenHas('feedback_last_speaker_side'),
            'feedback_last_replied_at'   => $this->whenDate('feedback_last_replied_at'),
            'deleted_at'                 => $this->whenTrashed($this->deleted_at),
            'created_at'                 => $this->whenDate('created_at'),
            'updated_at'                 => $this->whenHas('updated_at'),
            'options'                    => $this->whenAppended('options'),
        ]);

        return $this->filterFields($data);
    }
}
