<?php declare(strict_types=1);
/*
 * @Author: Charsen <https://github.com/charsen>
 * @Date: 2026-08-10 16:58
 * @LastEditors: Charsen <https://github.com/charsen>
 * @LastEditTime: 2026-08-10 16:58
 * @Description: FeedbackFilter
 */

namespace Mooeen\Feedback\Models\Filters;

use Mooeen\Scaffold\Foundation\BaseFilter;

class FeedbackFilter extends BaseFilter
{
    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];

    public function feedback_root_id($int)
    {
        $int = is_array($int) ? $int : [$int];

        return $this->whereIn('feedback_root_id', $int);
    }

    public function feedback_parent_id($int)
    {
        $int = is_array($int) ? $int : [$int];

        return $this->whereIn('feedback_parent_id', $int);
    }

    public function feedbackable_type($str)
    {
        return $this->where('feedbackable_type', 'LIKE', "%{$str}%");
    }

    public function feedbackable_id($int)
    {
        $int = is_array($int) ? $int : [$int];

        return $this->whereIn('feedbackable_id', $int);
    }

    public function feedbackable_title($str)
    {
        return $this->where('feedbackable_title', 'LIKE', "%{$str}%");
    }

    public function feedback_type($str)
    {
        return $this->where('feedback_type', $str);
    }

    public function feedback_content($str)
    {
        return $this->where('feedback_content', 'LIKE', "%{$str}%");
    }

    public function feedback_submitter_id($int)
    {
        $int = is_array($int) ? $int : [$int];

        return $this->whereIn('feedback_submitter_id', $int);
    }

    public function feedback_contact_name($str)
    {
        return $this->where('feedback_contact_name', 'LIKE', "%{$str}%");
    }

    public function feedback_organization($str)
    {
        return $this->where('feedback_organization', 'LIKE', "%{$str}%");
    }

    public function feedback_phone($str)
    {
        return $this->where('feedback_phone', 'LIKE', "%{$str}%");
    }

    public function feedback_email($str)
    {
        return $this->where('feedback_email', 'LIKE', "%{$str}%");
    }

    public function feedback_ip($str)
    {
        return $this->where('feedback_ip', 'LIKE', "%{$str}%");
    }

    public function feedback_device($str)
    {
        return $this->where('feedback_device', 'LIKE', "%{$str}%");
    }

    public function feedback_platform($str)
    {
        return $this->where('feedback_platform', 'LIKE', "%{$str}%");
    }

    public function feedback_browser($str)
    {
        return $this->where('feedback_browser', 'LIKE', "%{$str}%");
    }

    public function feedback_page_url($str)
    {
        return $this->where('feedback_page_url', 'LIKE', "%{$str}%");
    }

    public function feedback_last_speaker_side($int)
    {
        $int = is_array($int) ? $int : [$int];

        return $this->whereIn('feedback_last_speaker_side', $int);
    }

    public function feedback_last_replied_at($date)
    {
        return $this->whereDate('feedback_last_replied_at', $date);
    }

    public function deleted_at($date)
    {
        return $this->whereDate('deleted_at', $date);
    }

    public function created_at($date)
    {
        return $this->whereDate('created_at', $date);
    }

    public function updated_at($date)
    {
        return $this->whereDate('updated_at', $date);
    }

    public function feedback_status($int)
    {
        $int = is_array($int) ? $int : [$int];

        return $this->whereIn('feedback_status', $int);
    }

    public function feedback_speaker_side($int)
    {
        $int = is_array($int) ? $int : [$int];

        return $this->whereIn('feedback_speaker_side', $int);
    }
}
