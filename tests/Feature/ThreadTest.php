<?php declare(strict_types=1);
/*
 * 机制层：话题串写入、顶楼守门、状态机、分类校验。
 */

use Illuminate\Support\Facades\Event;
use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
use Mooeen\Feedback\Events\FeedbackAppended;
use Mooeen\Feedback\Events\FeedbackReplied;
use Mooeen\Feedback\Events\FeedbackStatusChanged;
use Mooeen\Feedback\Events\FeedbackSubmitted;
use Mooeen\Feedback\Exceptions\InvalidFeedbackType;
use Mooeen\Feedback\Models\Enums\FeedbackSpeakerSide;
use Mooeen\Feedback\Models\Enums\FeedbackStatus;
use Mooeen\Feedback\Models\Feedback;
use Mooeen\Feedback\Tests\Stubs\Product;
use Mooeen\Feedback\Tests\Stubs\TestFeedbackTypes;

beforeEach(function () {
    app()->bind(FeedbackTypeResolver::class, TestFeedbackTypes::class);
});

function submitOne(array $overrides = []): Feedback
{
    return Feedback::submit(array_merge([
        'feedback_type'         => 'SUPPORT',
        'feedback_content'      => '页面加载很慢，希望优化一下',
        'feedback_contact_name' => '张三',
        'feedback_email'        => 'zhangsan@example.com',
    ], $overrides));
}

it('提交生成顶楼行：root/parent 为空，状态待受理，发言侧为提交侧', function () {
    $fb = submitOne();

    expect($fb->isRoot())->toBeTrue()
        ->and($fb->feedback_root_id)->toBeNull()
        ->and($fb->feedback_parent_id)->toBeNull()
        ->and($fb->status())->toBe(FeedbackStatus::PENDING)
        ->and($fb->speakerSide())->toBe(FeedbackSpeakerSide::SUBMITTER)
        ->and($fb->feedback_last_replied_at)->not->toBeNull();
});

it('分类不在 host 声明的目录里就拒收', function () {
    submitOne(['feedback_type' => 'NOT_DECLARED']);
})->throws(InvalidFeedbackType::class);

it('声明了 requires_target 的分类，不带宿主就拒收', function () {
    submitOne(['feedback_type' => 'SALES']);
})->throws(InvalidFeedbackType::class);

it('带宿主提交：多态三列写入，标题写时快照', function () {
    $product = Product::create(['title' => '五轴加工中心']);

    $fb = $product->receiveFeedback([
        'feedback_type'    => 'SALES',
        'feedback_content' => '想咨询这台设备的报价与交期',
    ]);

    expect($fb->feedbackable_id)->toBe((string) $product->getKey())
        ->and($fb->feedbackable_title)->toBe('五轴加工中心');

    // 快照不回溯：对象改名后，历史反馈仍显示提交当时的标题
    $product->update(['title' => '五轴加工中心（已停产）']);

    expect($fb->fresh()->feedbackable_title)->toBe('五轴加工中心');
});

it('宿主的 feedbacks 关系只取顶楼行 —— 子行是发言，不该混进对象的反馈列表', function () {
    $product = Product::create(['title' => '设备 A']);
    $fb      = $product->receiveFeedback(['feedback_type' => 'SALES', 'feedback_content' => '请问有现货吗']);

    $fb->appendMessage('有的，已私信报价', FeedbackSpeakerSide::HANDLER, '9001');

    expect($product->feedbacks()->count())->toBe(1)
        ->and($fb->thread()->count())->toBe(1);
});

it('追加发言：子行挂上 root/parent，顶楼派生缓存刷新', function () {
    $fb = submitOne();

    $reply = $fb->appendMessage('收到，我们排查一下', FeedbackSpeakerSide::HANDLER, '9001');
    $fb->refresh();

    expect($reply->feedback_root_id)->toBe((string) $fb->getKey())
        ->and($reply->feedback_parent_id)->toBe((string) $fb->getKey())
        ->and($reply->isRoot())->toBeFalse()
        ->and($fb->feedback_last_speaker_side)->toBe(FeedbackSpeakerSide::HANDLER->value);
});

it('子行禁写业务字段 —— 单表自引用的守门处', function () {
    $fb = submitOne();

    $reply = $fb->appendMessage('内部备注', FeedbackSpeakerSide::HANDLER, '9001');

    // 即便强行塞，saving 钩子也会抹平
    $reply->forceFill([
        'feedback_type'         => 'SALES',
        'feedback_contact_name' => '李四',
        'feedback_ip'           => '10.0.0.1',
    ])->save();

    $reply->refresh();

    expect($reply->feedback_type)->toBeNull()
        ->and($reply->feedback_contact_name)->toBeNull()
        ->and($reply->feedback_ip)->toBeNull();
});

it('受理侧回复不自动改状态 —— 回了一句不等于处理完了', function () {
    $fb = submitOne();
    $fb->transitionTo(FeedbackStatus::PROCESSING);

    $fb->appendMessage('还在排查', FeedbackSpeakerSide::HANDLER, '9001');

    expect($fb->fresh()->status())->toBe(FeedbackStatus::PROCESSING);
});

it('已完结后提交侧再发言 → 自动退回待受理（包内唯一自动规则）', function (FeedbackStatus $closedLike) {
    $fb = submitOne();
    $fb->transitionTo($closedLike);

    $fb->appendMessage('问题又出现了', FeedbackSpeakerSide::SUBMITTER);

    expect($fb->fresh()->status())->toBe(FeedbackStatus::PENDING);
})->with([
    '已完结' => FeedbackStatus::RESOLVED,
    '已关闭' => FeedbackStatus::CLOSED,
]);

it('处理中状态下提交侧发言不触发退回 —— 只有终态才退', function () {
    $fb = submitOne();
    $fb->transitionTo(FeedbackStatus::PROCESSING);

    $fb->appendMessage('补充一点信息', FeedbackSpeakerSide::SUBMITTER);

    expect($fb->fresh()->status())->toBe(FeedbackStatus::PROCESSING);
});

it('状态同值流转是 no-op，不派空事件', function () {
    Event::fake([FeedbackStatusChanged::class]);

    submitOne()->transitionTo(FeedbackStatus::PENDING);

    Event::assertNotDispatched(FeedbackStatusChanged::class);
});

it('四个领域事件各就各位', function () {
    Event::fake([FeedbackSubmitted::class, FeedbackAppended::class, FeedbackReplied::class, FeedbackStatusChanged::class]);

    $fb = submitOne();
    $fb->appendMessage('补充', FeedbackSpeakerSide::SUBMITTER);
    $fb->appendMessage('回复', FeedbackSpeakerSide::HANDLER, '9001');
    $fb->transitionTo(FeedbackStatus::RESOLVED);

    Event::assertDispatched(FeedbackSubmitted::class);
    Event::assertDispatched(FeedbackAppended::class);
    Event::assertDispatched(FeedbackReplied::class);
    Event::assertDispatched(FeedbackStatusChanged::class);
});

it('roots 作用域只出顶楼行', function () {
    $fb = submitOne();
    $fb->appendMessage('一', FeedbackSpeakerSide::HANDLER, '9001');
    $fb->appendMessage('二', FeedbackSpeakerSide::SUBMITTER);

    expect(Feedback::query()->roots()->count())->toBe(1)
        ->and(Feedback::query()->count())->toBe(3)
        ->and($fb->thread()->count())->toBe(2);
});

it('内容读时打码凭证，库中原值不变', function () {
    $fb = submitOne(['feedback_content' => '登录报错，我的 token=abc123secret 贴给你们']);

    expect($fb->fresh()->feedback_content)->toContain('token=***')
        ->and($fb->fresh()->getRawOriginal('feedback_content'))->toContain('token=abc123secret');
});
