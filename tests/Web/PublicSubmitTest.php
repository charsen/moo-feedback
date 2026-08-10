<?php declare(strict_types=1);
/*
 * 前台提交入口：蜜罐静默、限流 429、必填项由 config 驱动、多态宿主只认 morph 别名。
 *
 * 「默认不挂载」那条断言在 AdminTest —— 它要的是**默认 TestCase**（开关关闭），
 * 而本文件整体跑在 PublicTestCase（开关开启）下。
 */

use Illuminate\Database\Eloquent\Relations\Relation;
use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
use Mooeen\Feedback\Models\Feedback;
use Mooeen\Feedback\Tests\Stubs\Product;
use Mooeen\Feedback\Tests\Stubs\TestFeedbackTypes;

beforeEach(function () {
    app()->bind(FeedbackTypeResolver::class, TestFeedbackTypes::class);
});

function payload(array $overrides = []): array
{
    return array_merge([
        'feedback_type'         => 'SUPPORT',
        'feedback_content'      => '页面加载很慢，希望能优化一下',
        'feedback_contact_name' => '张三',
        'feedback_email'        => 'zhangsan@example.com',
    ], $overrides);
}

it('提交成功，但不返回反馈 ID', function () {
    $this->postJson('/api/feedbacks', payload())
        ->assertCreated()
        ->assertExactJson(['submitted' => true]);

    expect(Feedback::query()->roots()->count())->toBe(1);
});

it('蜜罐命中：响应与成功完全一致，但一行都不落库', function () {
    $field = config('moo-feedback.anti_spam.honeypot.field');

    $this->postJson('/api/feedbacks', payload([$field => 'bot']))
        ->assertCreated()
        ->assertExactJson(['submitted' => true]);

    expect(Feedback::query()->count())->toBe(0);
});

it('超频返回 429', function () {
    config(['moo-feedback.anti_spam.throttle.max_per_mail' => 1]);

    $this->postJson('/api/feedbacks', payload(['feedback_email' => 'a@example.com']))->assertCreated();
    $this->postJson('/api/feedbacks', payload(['feedback_email' => 'a@example.com']))
        ->assertStatus(429)
        ->assertJsonPath('reason', 'throttle');
});

it('分类不在 host 目录里返回 422', function () {
    $this->postJson('/api/feedbacks', payload(['feedback_type' => 'NOPE']))->assertStatus(422);
});

it('必填联系方式由 config 决定', function () {
    config(['moo-feedback.public.required_contact' => ['feedback_organization']]);

    $this->postJson('/api/feedbacks', payload())->assertStatus(422);
    $this->postJson('/api/feedbacks', payload(['feedback_organization' => '某某机构']))->assertCreated();
});

it('内容过短被校验层挡下', function () {
    $this->postJson('/api/feedbacks', payload(['feedback_content' => '短']))->assertStatus(422);
});

it('多态宿主只认 morph 别名，不接受模型 FQN', function () {
    Relation::morphMap(['product' => Product::class]);
    $product = Product::create(['title' => '五轴加工中心']);

    $this->postJson('/api/feedbacks', payload([
        'feedback_type' => 'SALES', 'target' => 'product', 'target_id' => $product->getKey(),
    ]))->assertCreated();

    // 直传 FQN 一律拒绝 —— 否则等于让客户端指定要实例化哪个类
    $this->postJson('/api/feedbacks', payload([
        'feedback_type' => 'SALES', 'target' => Product::class, 'target_id' => $product->getKey(),
    ]))->assertStatus(422);
});

it('requires_target 分类不带宿主返回 422', function () {
    $this->postJson('/api/feedbacks', payload(['feedback_type' => 'SALES']))->assertStatus(422);
});

it('meta 给出表单渲染所需的一切', function () {
    $res = $this->getJson('/api/feedbacks/meta');

    $res->assertOk()
        ->assertJsonPath('honeypot_field', config('moo-feedback.anti_spam.honeypot.field'))
        ->assertJsonPath('types.0.key', 'SALES')
        ->assertJsonPath('types.0.requires_target', true)
        ->assertJsonPath('types.2.key', 'OTHER');

    expect($res->json('content_max'))->toBe(4000);
});
