<?php declare(strict_types=1);
/*
 * @Author: Charsen <https://github.com/charsen>
 * @Date: 2026-08-10 16:58
 * @LastEditors: Charsen <https://github.com/charsen>
 * @LastEditTime: 2026-08-10 16:58
 * @Description: FeedbackController's Trait
 */

namespace Mooeen\Feedback\Http\Controllers\Admin\Traits;

use Mooeen\Scaffold\Foundation\FormRequest;
use Mooeen\Scaffold\Foundation\FormWidgetCollection;
use Mooeen\Scaffold\Foundation\TableColumnsCollection;

trait FeedbackTrait
{
    /**
     * 列表的查询字段
     *
     * 相对生成物做了取舍：列表页只查「列表要用的」，环境采集（ip / device / platform / browser /
     * page_url）与联系方式细项（organization / phone）都只在详情页有意义，列表不查也不显示 ——
     * 一屏二十列没人看得下去，且访客 IP、手机号这类数据不该在列表页无差别铺开。
     *
     * feedback_content 反而必须查：反馈列表不给内容预览，受理人员每条都得点进去才知道是什么事。
     */
    private function getListFields(string $action = 'index'): array
    {
        $fields = [
            'id',
            'feedbackable_type', 'feedbackable_id', 'feedbackable_title',
            'feedback_type', 'feedback_status', 'feedback_content',
            'feedback_submitter_id',
            'feedback_contact_name', 'feedback_email',
            'feedback_last_speaker_side', 'feedback_last_replied_at',
            'created_at',
        ];

        if ($action === 'index') {
            $append = ['updated_at'];
        } else {
            $append = ['deleted_at'];
        }

        return [...$fields, ...$append];
    }

    /**
     * 列表的表头
     *
     * feedback_root_id / feedback_parent_id 是话题串的结构字段，对受理人员没有意义（列表本就只出
     * 顶楼行，这两列恒为 null），一律不进表头。
     *
     * feedback_contact_name 给 slot：提交人一格里同时要显示姓名与邮箱，各 host 的排版口径不同，
     * 交给前端插槽而不是在包里拼字符串。
     */
    private function getListColumns(string $action = 'index'): TableColumnsCollection
    {
        $columns = [
            'feedback_type_txt'              => ['width' => 120],
            'feedback_contact_name'          => ['type' => 'slot', 'width' => 180],
            'feedback_content'               => ['type' => 'slot'],
            'feedback_status_txt'            => ['width' => 100],
            'feedback_last_speaker_side_txt' => ['width' => 110],
            'feedback_last_replied_at'       => ['width' => 165],
        ];

        return TableColumnsCollection::makeColumns($columns, $action);
    }

    /**
     * 列表的表单控件
     */
    private function getListFormWidgets(FormRequest $request, string $action = 'index', array $override = []): FormWidgetCollection
    {
        $base = [];

        return FormWidgetCollection::makeSearch($request, $base, override: $override);
    }

    /**
     * Create|Edit 的表单控件
     */
    private function getFormWidgets(FormRequest $request, string $method, array $override = []): FormWidgetCollection
    {
        $base = [];

        return FormWidgetCollection::makeForm($request, $base, $method === 'create', $override);
    }
}
