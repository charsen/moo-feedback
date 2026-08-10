<?php declare(strict_types=1);
/*
 * 反垃圾三件套：蜜罐 / 长度 / 限流。匿名提交入口不做这个，上线即被灌。
 */

use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
use Mooeen\Feedback\Exceptions\SpamRejected;
use Mooeen\Feedback\Models\Feedback;
use Mooeen\Feedback\Tests\Stubs\TestFeedbackTypes;

beforeEach(function () {
    app()->bind(FeedbackTypeResolver::class, TestFeedbackTypes::class);
});

function submitSpam(array $overrides = []): Feedback
{
    return Feedback::submit(array_merge([
        'feedback_type'    => 'SUPPORT',
        'feedback_content' => '这是一条长度合规的正常反馈内容',
    ], $overrides));
}

it('蜜罐字段被填写即拦截，且标记为静默 —— 明着报错等于教脚本作者换字段名', function () {
    $field = config('moo-feedback.anti_spam.honeypot.field');

    try {
        submitSpam([$field => 'bot filled this']);
        $this->fail('应当抛出 SpamRejected');
    } catch (SpamRejected $e) {
        expect($e->reason)->toBe('honeypot')
            ->and($e->silent)->toBeTrue();
    }

    expect(Feedback::query()->count())->toBe(0);
});

it('蜜罐字段本身不落库', function () {
    $field = config('moo-feedback.anti_spam.honeypot.field');
    $fb    = submitSpam([$field => '']);

    expect($fb->getAttributes())->not->toHaveKey($field);
});

it('内容过短或过长都拒收，且不是静默拦截', function (string $content) {
    try {
        submitSpam(['feedback_content' => $content]);
        $this->fail('应当抛出 SpamRejected');
    } catch (SpamRejected $e) {
        expect($e->reason)->toBe('length')->and($e->silent)->toBeFalse();
    }
})->with([
    '过短' => '太短',
    '过长' => str_repeat('灌', 4001),
]);

it('同邮箱在时间窗内超过上限即限流', function () {
    config(['moo-feedback.anti_spam.throttle.max_per_mail' => 2]);

    submitSpam(['feedback_email' => 'spam@example.com']);
    submitSpam(['feedback_email' => 'spam@example.com']);

    try {
        submitSpam(['feedback_email' => 'spam@example.com']);
        $this->fail('应当抛出 SpamRejected');
    } catch (SpamRejected $e) {
        expect($e->reason)->toBe('throttle');
    }

    expect(Feedback::query()->roots()->count())->toBe(2);
});

it('限流只数顶楼行 —— 话题串里的追加发言不该被算进配额', function () {
    config(['moo-feedback.anti_spam.throttle.max_per_mail' => 2]);

    $fb = submitSpam(['feedback_email' => 'user@example.com']);
    foreach (range(1, 5) as $i) {
        $fb->appendMessage("补充 {$i}", \Mooeen\Feedback\Models\Enums\FeedbackSpeakerSide::SUBMITTER);
    }

    // 追加了 5 条发言后，配额仍只用掉 1 条
    submitSpam(['feedback_email' => 'user@example.com']);

    expect(Feedback::query()->roots()->count())->toBe(2);
});

it('限流可整体关闭', function () {
    config([
        'moo-feedback.anti_spam.throttle.enabled'      => false,
        'moo-feedback.anti_spam.throttle.max_per_mail' => 1,
    ]);

    submitSpam(['feedback_email' => 'many@example.com']);
    submitSpam(['feedback_email' => 'many@example.com']);
    submitSpam(['feedback_email' => 'many@example.com']);

    expect(Feedback::query()->roots()->count())->toBe(3);
});

it('环境采集可关闭 —— host 有合规要求不得留存访客 IP 时', function () {
    config(['moo-feedback.capture.enabled' => false]);

    $fb = submitSpam();

    expect($fb->feedback_ip)->toBeNull()
        ->and($fb->feedback_device)->toBeNull();
});
