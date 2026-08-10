<?php declare(strict_types=1);
/*
 * @Author: Charsen <https://github.com/charsen>
 * @Date: 2026-08-10 16:58
 * @LastEditors: Charsen <https://github.com/charsen>
 * @LastEditTime: 2026-08-10 17:20
 * @Description: 反馈控制器
 */

namespace Mooeen\Feedback\Http\Controllers\Admin;

use Mooeen\Feedback\Contracts\SubmitterResolver;
use Mooeen\Feedback\Http\Controllers\Admin\Traits\FeedbackTrait;
use Mooeen\Feedback\Http\Controllers\Admin\Traits\HandlesResourceActions;
use Mooeen\Feedback\Http\Requests\Feedback\DestroyBatchRequest;
use Mooeen\Feedback\Http\Requests\Feedback\IndexRequest;
use Mooeen\Feedback\Http\Requests\Feedback\ReplyRequest;
use Mooeen\Feedback\Http\Requests\Feedback\TransitionRequest;
use Mooeen\Feedback\Models\Enums\FeedbackSpeakerSide;
use Mooeen\Feedback\Models\Enums\FeedbackStatus;
use Mooeen\Feedback\Models\Feedback;
use Mooeen\Scaffold\Contracts\OperatorResolver;
use Mooeen\Scaffold\Foundation\BaseResource;
use Mooeen\Scaffold\Foundation\BaseResourceCollection;
use Mooeen\Scaffold\Foundation\ColumnsCollection;
use Mooeen\Scaffold\Foundation\Controller;

/**
 * ACL
 *
 * @package_name {zh-CN: 后台管理 | en: Admin}
 * @module_name {zh-CN: 意见反馈 | en: Feedback}
 * @controller_name {zh-CN: 反馈管理 | en: Management Feedback}
 */
class FeedbackController extends Controller
{
    use FeedbackTrait;
    use HandlesResourceActions;

    protected Feedback $model;

    public function __construct(Feedback $model)
    {
        $this->model = $model;
    }

    /**
     * 执行 action 前先验证权限
     */
    public function boot(): void
    {
        $this->checkAuthorization();
    }

    // ===== 手写业务区（首次生成后自持；moo:free 非 --force 重生成不覆盖本类）=====
    //
    // 相对生成物做了三处裁剪与两处增补：
    //   - 砍掉 store / create / update / edit：反馈的写入口永远在业务侧（Feedback::submit()），
    //     管理面不手工造反馈；状态也不该经通用 update 改，否则绕过状态机与事件。
    //   - index / trashed 一律加 roots()：列表是「反馈」不是「发言」，子行必须挡在外面。
    //   - 增补 reply / transition 两个受理动作，分别委托到 appendMessage() / transitionTo()。

    /**
     * 反馈列表
     *
     * @acl {zh-CN: 反馈列表, en: Feedback List, desc: }
     */
    public function index(IndexRequest $request): BaseResourceCollection
    {
        $validated = $request->validated();

        $result = $this->model->select($this->getListFields())
            ->roots()
            ->filter($validated)
            ->latest('feedback_last_replied_at')
            ->paginate(($validated['page_limit'] ?? null));
        $result->append(['options']);

        $this->injectSubmitterNames($result->getCollection()->all());

        return BaseResource::collection($result)
            ->additional([
                'columns'      => $this->getListColumns(),
                'form_widgets' => $this->getListFormWidgets($request),
            ]);
    }

    /**
     * 反馈回收站
     *
     * @acl {zh-CN: 反馈回收站, en: Feedback Trashed, desc: }
     */
    public function trashed(IndexRequest $request): BaseResourceCollection
    {
        $validated = $request->validated();

        $result = $this->model->select($this->getListFields('trashed'))
            ->roots()
            ->filter($validated)
            ->latest('deleted_at')
            ->onlyTrashed()
            ->paginate(($validated['page_limit'] ?? null));
        $result->append(['options']);

        return BaseResource::collection($result)
            ->trashed()
            ->additional([
                'columns'      => $this->getListColumns('trashed'),
                'form_widgets' => $this->getListFormWidgets($request, 'trashed'),
            ]);
    }

    /**
     * 查看反馈（含整条话题串）
     *
     * @acl {zh-CN: 查看反馈, en: Show Feedback, desc: }
     */
    public function show(int|string $id): BaseResource
    {
        $result = $this->model->withTrashed()->findOrFail($id);
        $result->append(['options']);

        // 话题串一发查询取整串（顶楼行 id 即全部发言的 feedback_root_id），不做递归
        $thread = $result->thread()->get();
        $this->injectSubmitterNames([$result, ...$thread->all()]);

        $columns = [
            'id', 'feedback_type', 'feedback_status', 'feedback_content',
            'feedbackable_type', 'feedbackable_id', 'feedbackable_title',
            'feedback_submitter_id', 'feedback_speaker_side',
            'feedback_contact_name', 'feedback_organization', 'feedback_phone', 'feedback_email',
            'feedback_ip', 'feedback_device', 'feedback_platform', 'feedback_browser', 'feedback_page_url',
            'feedback_last_speaker_side', 'feedback_last_replied_at',
            'deleted_at', 'created_at', 'updated_at',
        ];

        return BaseResource::make($result)->additional([
            'columns'  => ColumnsCollection::make($columns),
            'thread'   => $thread,
            'statuses' => FeedbackStatus::valueLabels(),
        ]);
    }

    /**
     * 回复反馈（受理侧发言）
     *
     * @acl {zh-CN: 回复反馈, en: Reply Feedback, desc: }
     */
    public function reply(ReplyRequest $request, int|string $id): BaseResource
    {
        $validated = $request->validated();
        $feedback  = $this->model->findOrFail($id);

        // 发言侧固定受理侧、发言人取当前操作人：都不由前端传，否则等于把身份判定交给客户端
        $message = $feedback->appendMessage(
            $validated['feedback_content'],
            FeedbackSpeakerSide::HANDLER,
            app(OperatorResolver::class)->id(),
        );

        return BaseResource::make($message);
    }

    /**
     * 变更受理状态
     *
     * @acl {zh-CN: 变更受理状态, en: Transition Feedback Status, desc: }
     */
    public function transition(TransitionRequest $request, int|string $id): BaseResource
    {
        $validated = $request->validated();
        $feedback  = $this->model->findOrFail($id);

        $result = $feedback->transitionTo(FeedbackStatus::from((int) $validated['feedback_status']));

        return BaseResource::make($result);
    }

    /**
     * 删除反馈
     *
     * @acl {zh-CN: 删除反馈, en: Destroy Feedback, desc: }
     */
    public function destroyBatch(DestroyBatchRequest $request): BaseResource
    {
        return $this->destroyBatchAction($request);
    }

    /**
     * 永久删除反馈
     *
     * @acl {zh-CN: 永久删除反馈, en: Destroy Forever Feedback, desc: }
     */
    public function forceDestroy(int|string $id): BaseResource
    {
        return $this->forceDestroyAction($id);
    }

    /**
     * 恢复反馈
     */
    public function restore(DestroyBatchRequest $request): BaseResource
    {
        return $this->restoreAction($request);
    }

    /**
     * 批量注入发言人姓名（读时解析，不落库）。
     *
     * 一次解析当页 / 整串的全部 id，防列表 N+1；host 未绑定 SubmitterResolver 时返空 map，
     * 各行的 _txt 为 null，不影响其余字段。匿名访客 submitter 为 null，不参与解析。
     *
     * @param list<Feedback> $rows
     */
    private function injectSubmitterNames(array $rows): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (Feedback $r) => $r->feedback_submitter_id, $rows),
        )));

        $names = $ids === [] ? [] : app(SubmitterResolver::class)->resolveNames($ids);

        foreach ($rows as $row) {
            $row->setAttribute('feedback_submitter_id_txt', $names[$row->feedback_submitter_id] ?? null);
        }
    }
}
