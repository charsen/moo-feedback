<?php declare(strict_types=1);
/*
 * FeedbackTypeResolver —— 反馈「分类目录」声明契约。
 *
 * 各 host 的分类取值集合彼此零交集（销售咨询 / 技术支持 / 问题反馈 / 功能建议…全是业务私有），
 * 包不可能预知，因此**不硬编码任何分类值**：由 host 在自己的胶水层（App\Moo\Feedback）实现本契约并 bind。
 *
 * ⚠ 与 moo 家族其它 resolver 分清：
 *   - 「姓名」resolver（如本包 SubmitterResolver）：id → 姓名，读时批量，展示用。
 *   - 「身份」resolver（scaffold 共享 OperatorResolver）：当前是谁 → id，写入时取操作人；本包不自造。
 *   - 「目录」resolver = 本契约：声明一份可选值清单，不解析任何 id。形状与前两者不同，命名沿用家族后缀。
 *
 * 契约约束：
 *   - 走容器绑定，禁 config 闭包（config:cache 序列化闭包会炸生产）。容器绑定另有一个好处：
 *     host 可从自己的库表动态读分类（让运营在后台自行增减），静态 config 数组做不到。
 *   - 包默认 Support\NullFeedbackTypeResolver 只返 OTHER，保证未绑定时包仍可独立跑通（测试 / 演示）。
 */

namespace Mooeen\Feedback\Contracts;

interface FeedbackTypeResolver
{
    /**
     * 声明本 host 的分类目录。
     *
     * 返回 [分类 key => 定义] map。key 落库进 feedback_type（varchar(32)，自解释，不用整型号段）。
     *
     * 定义各项：
     *   - label            必填，展示名。可直接给翻译键，读侧统一过 __()。
     *   - requires_target  选填，默认 false。为 true 时该分类必须携带多态宿主
     *                      （feedbackable_type / feedbackable_id），由包统一校验，
     *                      不必每个 host 各写一遍 if。
     *   - sort             选填，展示排序，小者靠前。
     *
     * @return array<string, array{label: string, requires_target?: bool, sort?: int}>
     */
    public function types(): array;
}
