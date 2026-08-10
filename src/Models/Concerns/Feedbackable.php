<?php declare(strict_types=1);
/*
 * Feedbackable —— 挂在业务模型上,使其可被反馈关联。
 *
 * 只有 requires_target 类分类才需要宿主(例如「从某个产品发起的咨询」);纯留言 / 建议类
 * 不挂任何宿主,顶楼行的 feedbackable_* 三列留空即可。因此本 trait 是**可选**能力,
 * 不 use 也能用包。
 */

namespace Mooeen\Feedback\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mooeen\Feedback\Models\Feedback;

trait Feedbackable
{
    /**
     * 挂在本对象上的反馈(**只取顶楼行** —— 子行是发言,不该出现在对象的反馈列表里)。
     */
    public function feedbacks(): MorphMany
    {
        return $this->morphMany(Feedback::class, 'feedbackable', 'feedbackable_type', 'feedbackable_id', 'id')
            ->roots()
            ->latest('id');
    }

    /**
     * 反馈时刻的对象标题,**写时快照**进 feedbackable_title(对象改名不回溯)。
     *
     * 快照的意义:管理面列表免 morph eager-load —— 否则一页 20 条反馈指向 5 种不同模型,
     * 光解标题就要 5 次额外查询。宿主模型可覆写本方法指定用哪个字段当标题。
     */
    public function feedbackTitle(): ?string
    {
        foreach (['title', 'name', 'subject'] as $guess) {
            if (! empty($this->{$guess})) {
                return (string) $this->{$guess};
            }
        }

        return null;
    }

    /**
     * 对本对象发起一条反馈。等价于 Feedback::submit($attrs, $this),写入口保持单一真值源。
     *
     * @param array<string, mixed> $attrs
     */
    public function receiveFeedback(array $attrs): Feedback
    {
        return Feedback::submit($attrs, $this);
    }
}
