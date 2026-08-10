<?php declare(strict_types=1);
/*
 * SubmitterResolver —— 反馈发言人「姓名」解析契约（姓名类 resolver，非身份类）。
 *
 * 把 submitter_id 批量解析成「读时展示的姓名」，供管理面把 submitter_id_txt 注入响应（不落库）。
 *
 * 一个契约覆盖双方：提交人与受理人**同住 submitter_id 一列**（靠 speaker_side 区分提交侧 / 受理侧），
 * 因此不需要两个 resolver。匿名访客的 submitter_id 为 null，不参与解析，其称谓回落顶楼行的 contact_name。
 *
 * ⚠ 两类 resolver 分清：
 *   - 「姓名」resolver = 本契约：id → 姓名，读时批量，展示用；host 在自己的 provider（App\Moo\Feedback）
 *     里 bind 自己的人员实现。
 *   - 「身份」resolver = scaffold 共享 OperatorResolver：当前是谁 → id，写入受理方发言时取操作人；本包不自造。
 *
 * 契约约束：
 *   - 读时调用（控制器 index/show），批量一次解析当页全部 id，防列表 N+1。
 *   - 不落库、不快照 —— 人员姓名可变，取当前姓名即可（宿主对象标题才快照进 feedbackable_title）。
 *   - 走容器绑定，禁 config 闭包（config:cache 序列化闭包会炸生产）。
 *   - 包默认 Support\NullSubmitterResolver 返空 map（零 moo-system）；姓名只是展示装饰，
 *     未绑定不影响反馈能力本身。
 */

namespace Mooeen\Feedback\Contracts;

interface SubmitterResolver
{
    /**
     * 批量解析 submitter_id → 姓名。返回 [id => 姓名] map；解析不到的 id 可缺省（读侧 ?? null 兜底）。
     *
     * @param array<int|string> $ids
     *
     * @return array<int|string, ?string>
     */
    public function resolveNames(array $ids): array;
}
