<?php declare(strict_types=1);
/*
 * 管理面：路由面收窄 + 受理动作 + 列表只出顶楼行。
 */

use Illuminate\Support\Facades\Route;
use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
use Mooeen\Feedback\Models\Enums\FeedbackSpeakerSide;
use Mooeen\Feedback\Models\Enums\FeedbackStatus;
use Mooeen\Feedback\Models\Feedback;
use Mooeen\Feedback\Tests\Stubs\TestFeedbackTypes;

beforeEach(function () {
    app()->bind(FeedbackTypeResolver::class, TestFeedbackTypes::class);
});

function routeNames(): array
{
    return collect(Route::getRoutes())->map(fn ($r) => $r->getName())->filter()->values()->all();
}

it('管理面挂上了只读 + 清理 + 受理的路由', function (string $name) {
    expect(routeNames())->toContain($name);
})->with([
    'admin.feedbacks.index',
    'admin.feedbacks.show',
    'admin.feedbacks.trashed',
    'admin.feedbacks.destroyBatch',
    'admin.feedbacks.forceDestroy',
    'admin.feedbacks.restore',
    'admin.feedbacks.reply',
    'admin.feedbacks.transition',
]);

it('写入口不在管理面 —— store/update/create/edit 一律没有路由', function (string $name) {
    expect(routeNames())->not->toContain($name);
})->with([
    'admin.feedbacks.store',
    'admin.feedbacks.update',
    'admin.feedbacks.create',
    'admin.feedbacks.edit',
]);

it('前台提交入口默认不挂载 —— 匿名可写的公开接口不能是默认开启', function (string $name) {
    expect(routeNames())->not->toContain($name);
})->with(['feedback.store', 'feedback.meta']);

it('列表只出顶楼行，按最后发言时间倒序', function () {
    $older = Feedback::submit(['feedback_type' => 'SUPPORT', 'feedback_content' => '较早的一条反馈内容']);
    $newer = Feedback::submit(['feedback_type' => 'SUPPORT', 'feedback_content' => '较新的一条反馈内容']);

    // 给较早那条追加发言，它的最后发言时间被刷新，应排到前面
    $older->appendMessage('受理侧跟进一句', FeedbackSpeakerSide::HANDLER, '9001');

    $rows = Feedback::query()->roots()->latest('feedback_last_replied_at')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->first()->getKey())->toBe($older->getKey())
        ->and($rows->last()->getKey())->toBe($newer->getKey());
});

it('回复动作固定受理侧，发言人取当前操作人而非前端传值', function () {
    $fb = Feedback::submit(['feedback_type' => 'SUPPORT', 'feedback_content' => '这是一条正常的反馈内容']);

    $message = $fb->appendMessage('已受理，正在排查', FeedbackSpeakerSide::HANDLER, '9001');

    expect($message->speakerSide())->toBe(FeedbackSpeakerSide::HANDLER)
        ->and($message->feedback_submitter_id)->toBe('9001')
        ->and($fb->fresh()->feedback_last_speaker_side)->toBe(FeedbackSpeakerSide::HANDLER->value);
});

it('状态词条走包内 lang，中文优先', function () {
    expect(FeedbackStatus::PENDING->label())->toBe('待受理')
        ->and(FeedbackStatus::CLOSED->label())->toBe('已关闭')
        ->and(FeedbackSpeakerSide::HANDLER->label())->toBe('受理侧');
});

it('字段词条不与其它包相撞 —— 语义字段全带 feedback_ 前缀', function () {
    $db = require __DIR__ . '/../../lang/zh-CN/db.php';

    $bare = array_diff(array_keys($db), ['id', 'deleted_at', 'created_at', 'updated_at']);

    foreach ($bare as $field) {
        expect($field)->toStartWith('feedback');
    }
});
