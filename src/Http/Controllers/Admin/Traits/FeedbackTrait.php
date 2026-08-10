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
     */
    private function getListFields(string $action = 'index'): array
    {
        $fields = ['id', 'feedback_root_id', 'feedback_parent_id', 'feedbackable_type', 'feedbackable_id', 'feedbackable_title', 'feedback_type', 'feedback_status', 'feedback_submitter_id', 'feedback_speaker_side', 'feedback_contact_name', 'feedback_organization', 'feedback_phone', 'feedback_email', 'feedback_ip', 'feedback_device', 'feedback_platform', 'feedback_browser', 'feedback_page_url', 'feedback_last_speaker_side', 'feedback_last_replied_at', 'created_at'];

        if ($action === 'index') {
            $append = ['updated_at'];
        } else {
            $append = ['deleted_at'];
        }

        return [...$fields, ...$append];
    }

    /**
     * 列表的表头
     */
    private function getListColumns(string $action = 'index'): TableColumnsCollection
    {
        $columns = [
            'feedback_root_id',
            'feedback_parent_id',
            'feedbackable_type',
            'feedbackable_id',
            'feedbackable_title',
            'feedback_type',
            'feedback_status_txt',
            'feedback_submitter_id',
            'feedback_speaker_side_txt',
            'feedback_contact_name',
            'feedback_organization',
            'feedback_phone',
            'feedback_email',
            'feedback_ip',
            'feedback_device',
            'feedback_platform',
            'feedback_browser',
            'feedback_page_url',
            'feedback_last_speaker_side',
            'feedback_last_replied_at',
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
