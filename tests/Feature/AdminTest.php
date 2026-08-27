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

it('管理列表按 host 分类与状态筛选', function () {
    $matching = Feedback::submit([
        'feedback_type'    => 'SUPPORT',
        'feedback_content' => '匹配的反馈内容',
    ]);
    Feedback::submit([
        'feedback_type'    => 'OTHER',
        'feedback_content' => '另一条反馈内容',
    ]);

    $response = $this->getJson('/api/admin/feedbacks?page=1&page_limit=15&feedback_type=SUPPORT&feedback_status=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $matching->id);
    $widgets = collect($response->json('form_widgets'))->flatten(1)->keyBy('field');

    expect($widgets)->toHaveKeys(['feedback_type', 'feedback_status'])
        ->and($widgets['feedback_type']['options'])->not->toBeEmpty();

    $this->getJson('/api/admin/feedbacks?page=1&page_limit=15&feedback_type=UNKNOWN')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('feedback_type');
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

it('分类 _txt 经 host 目录解析（feedback_type 无 enums，codegen 产不出这个访问器）', function () {
    $fb = Feedback::submit(['feedback_type' => 'SUPPORT', 'feedback_content' => '这是一条正常的反馈内容']);

    expect($fb->feedback_type_txt)->toBe('技术支持')
        ->and($fb->toArray())->toHaveKey('feedback_type_txt');
});

it('目录里查不到的分类原样回显，不返空白', function () {
    // 历史分类被 host 从目录撤下的情形：显示 LEGACY_X 也远好过显示空白
    $fb = Feedback::submit(['feedback_type' => 'SUPPORT', 'feedback_content' => '这是一条正常的反馈内容']);
    $fb->forceFill(['feedback_type' => 'LEGACY_X'])->save();

    expect($fb->fresh()->feedback_type_txt)->toBe('LEGACY_X');
});

it('最后发言方 _txt 有值，且子行上为 null', function () {
    $fb    = Feedback::submit(['feedback_type' => 'SUPPORT', 'feedback_content' => '这是一条正常的反馈内容']);
    $child = $fb->appendMessage('已受理', FeedbackSpeakerSide::HANDLER, '9001');

    expect($fb->fresh()->feedback_last_speaker_side_txt)->toBe('受理侧')
        ->and($child->feedback_last_speaker_side_txt)->toBeNull(); // 派生缓存只在顶楼行
});

it('行内动作里没有编辑笔 —— 包没有 update 路由，摆一支必然 404 的笔是错的', function () {
    $fb = Feedback::submit(['feedback_type' => 'SUPPORT', 'feedback_content' => '这是一条正常的反馈内容']);

    $types = collect($fb->options)->pluck('type')->all();

    expect($types)->toBe(['handle', 'destroy']);

    $fb->delete();
    expect(collect($fb->fresh()->options)->pluck('type')->all())->toBe(['restore', 'force-destroy']);
});

it('列表表头是裁剪过的受理视图，不是全字段倾倒', function () {
    $columns = (new ReflectionClass(\Mooeen\Feedback\Http\Controllers\Admin\FeedbackController::class))
        ->getMethod('getListColumns');
    $columns->setAccessible(true);

    $keys = collect($columns->invoke(app(\Mooeen\Feedback\Http\Controllers\Admin\FeedbackController::class))
        ->toArray(request()))->pluck('field')->all();

    // 内容预览必须在：列表不给内容，受理人员每条都得点进去才知道是什么事
    expect($keys)->toContain('feedback_content')
        ->and($keys)->toContain('feedback_type_txt')
        ->and($keys)->toContain('feedback_status_txt')
        // 话题串结构字段与环境采集不进列表：前者对受理人员无意义（列表本就只出顶楼行），
        // 后者含访客 IP 这类数据，不该在列表页无差别铺开
        ->and($keys)->not->toContain('feedback_root_id')
        ->and($keys)->not->toContain('feedback_parent_id')
        ->and($keys)->not->toContain('feedback_ip')
        ->and($keys)->not->toContain('feedback_phone');
});
