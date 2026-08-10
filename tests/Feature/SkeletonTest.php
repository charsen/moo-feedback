<?php declare(strict_types=1);
/*
 * 骨架冒烟：包能独立 boot（零 host 绑定），两个契约的默认实现就位，脱敏按约定工作。
 */

use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
use Mooeen\Feedback\Contracts\SubmitterResolver;
use Mooeen\Feedback\Support\NullFeedbackTypeResolver;
use Mooeen\Feedback\Support\NullSubmitterResolver;
use Mooeen\Feedback\Support\SecretRedactor;

it('未绑定契约时包仍可独立跑通', function () {
    expect(app(FeedbackTypeResolver::class))->toBeInstanceOf(NullFeedbackTypeResolver::class)
        ->and(app(SubmitterResolver::class))->toBeInstanceOf(NullSubmitterResolver::class);
});

it('分类默认实现给出 OTHER —— 分类是核心，返空则无法提交', function () {
    $types = app(FeedbackTypeResolver::class)->types();

    expect($types)->toHaveKey('OTHER')
        ->and($types['OTHER'])->toHaveKey('label');
});

it('姓名默认实现返空 map —— 姓名只是展示装饰', function () {
    expect(app(SubmitterResolver::class)->resolveNames([1, 2, 3]))->toBe([]);
});

it('config 已合并，host 不发布也能读', function () {
    expect(config('moo-feedback.anti_spam.honeypot.enabled'))->toBeTrue()
        ->and(config('moo-feedback.admin.prefix'))->toBe('api/admin');
});

it('打码凭证类模式', function (string $raw, string $expected) {
    expect(SecretRedactor::scrub($raw))->toBe($expected);
})->with([
    'JWT'        => ['报错了 eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.abc-_123 一直转', '报错了 ***JWT*** 一直转'],
    'Bearer'     => ['头里带 Bearer sk_live_abc.123-xyz 就 401', '头里带 Bearer *** 就 401'],
    'key=value'  => ['连不上：password=hunter2 试过了', '连不上：password=*** 试过了'],
    'key: value' => ['配置写的 api_key: "abc123def" 对吗', '配置写的 api_key: "***" 对吗'],
]);

it('刻意不打码 PII 数字 —— 打码后受理人无法照搬复现', function () {
    $raw = '我手机号 13800138000 查不到订单，链接 /orders?uid=440101199001011234';

    expect(SecretRedactor::scrub($raw))->toBe($raw);
});

it('空值原样返回', function () {
    expect(SecretRedactor::scrub(null))->toBeNull()
        ->and(SecretRedactor::scrub(''))->toBe('');
});

it('递归脱敏数组，敏感键名整值打码', function () {
    $out = SecretRedactor::scrubArray([
        'page'    => 'https://example.com/x?token=abc123',
        'headers' => ['authorization' => 'Bearer zzz', 'x-trace-id' => 'keep-me'],
    ]);

    expect($out['headers']['authorization'])->toBe('***')
        ->and($out['headers']['x-trace-id'])->toBe('keep-me')
        ->and($out['page'])->toBe('https://example.com/x?token=***');
});
