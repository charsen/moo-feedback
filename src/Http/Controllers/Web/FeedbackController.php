<?php declare(strict_types=1);
/*
 * 前台反馈提交入口 —— 访客 / 登录用户填表提交。
 *
 * 默认不挂载,host 需在 config('moo-feedback.public.enabled') 显式开启:这是匿名可写的公开接口。
 *
 * 响应刻意**不返回反馈 ID**,且成功与「蜜罐静默拦截」返回完全相同的结果 ——
 * 二者必须对脚本作者不可区分,否则等于告诉他「换个字段名再来」。
 */

namespace Mooeen\Feedback\Http\Controllers\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
use Mooeen\Feedback\Exceptions\InvalidFeedbackType;
use Mooeen\Feedback\Exceptions\SpamRejected;
use Mooeen\Feedback\Http\Requests\Feedback\SubmitRequest;
use Mooeen\Feedback\Models\Feedback;

/**
 * @package_name {zh-CN: Web 接口 | en: Web}
 * @module_name {zh-CN: 反馈与咨询 | en: Feedback}
 * @controller_name {zh-CN: 反馈与咨询 | en: Feedback}
 */
class FeedbackController extends Controller
{
    /**
     * 提交反馈与咨询
     *
     * 校验并受理访客提交的反馈或咨询。
     */
    public function store(SubmitRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $honeypot  = (string) config('moo-feedback.anti_spam.honeypot.field', 'nickname_confirm');

        try {
            $target = $this->resolveTarget($validated['target'] ?? null, $validated['target_id'] ?? null);
        } catch (InvalidFeedbackType $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $attrs = array_diff_key($validated, array_flip(['target', 'target_id']));
        // 蜜罐值从原始请求取:它不在 validated() 里（见 SubmitRequest 注释），但 AntiSpamGuard 要看
        $attrs[$honeypot] = $request->input($honeypot, '');

        try {
            Feedback::submit($attrs, $target);
        } catch (SpamRejected $e) {
            if ($e->silent) {
                return $this->accepted();       // 蜜罐命中：与成功不可区分
            }

            return response()->json(
                ['message' => $e->getMessage(), 'reason' => $e->reason],
                $e->reason === 'throttle' ? 429 : 422,
            );
        } catch (InvalidFeedbackType $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->accepted();
    }

    /**
     * 反馈与咨询表单元信息
     *
     * 返回表单渲染所需的分类目录与蜜罐字段名。
     * 前端不知道该渲染哪个隐藏字段，蜜罐就形同虚设 —— 故字段名必须可获取。
     * 这不削弱防护：蜜罐挡的是无差别灌注的通用脚本，不是针对性攻击。
     */
    public function meta(): JsonResponse
    {
        $types = [];
        foreach (app(FeedbackTypeResolver::class)->types() as $key => $def) {
            $types[] = [
                'key'             => $key,
                'label'           => __($def['label'] ?? $key),
                'requires_target' => (bool) ($def['requires_target'] ?? false),
                'sort'            => (int) ($def['sort'] ?? 0),
            ];
        }
        usort($types, static fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return response()->json([
            'types'            => $types,
            'honeypot_field'   => config('moo-feedback.anti_spam.honeypot.field'),
            'content_min'      => (int) config('moo-feedback.anti_spam.content.min', 6),
            'content_max'      => (int) config('moo-feedback.anti_spam.content.max', 4000),
            'required_contact' => array_values((array) config('moo-feedback.public.required_contact', [])),
        ]);
    }

    /**
     * 把前端给的 morph 别名解析成宿主模型。
     *
     * 只认 host 在 Relation::morphMap() 里注册过的别名 —— **不接受**前端直传模型 FQN，
     * 那等于让客户端指定要实例化哪个类。
     */
    private function resolveTarget(?string $alias, int|string|null $id): ?Model
    {
        if ($alias === null || $id === null) {
            return null;
        }

        $class = Relation::getMorphedModel($alias);
        if ($class === null || ! is_subclass_of($class, Model::class)) {
            throw InvalidFeedbackType::unknown($alias, array_keys(Relation::morphMap()));
        }

        return $class::query()->find($id);
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['submitted' => true], 201);
    }
}
