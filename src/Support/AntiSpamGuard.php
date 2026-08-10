<?php declare(strict_types=1);
/*
 * 反垃圾 —— 提交限流 / 蜜罐 / 内容长度。
 *
 * 匿名提交入口不做这个,上线即被灌 —— 盘点过的几套既有实现里,这一项**全部缺位**,
 * 所以它属于「包该提供、各项目都不会单独去写」的东西。
 *
 * 验证码刻意不集成:包不绑定具体服务商,host 自行在提交入口前置。
 */

namespace Mooeen\Feedback\Support;

use Mooeen\Feedback\Exceptions\SpamRejected;
use Mooeen\Feedback\Models\Feedback;

class AntiSpamGuard
{
    /**
     * @param array<string, mixed> $input 原始提交数据(含蜜罐字段)
     *
     * @throws SpamRejected 蜜罐命中 / 超频 / 长度越界
     */
    public static function check(array $input, ?string $ip = null, ?string $email = null): void
    {
        self::honeypot($input);
        self::length((string) ($input['feedback_content'] ?? ''));
        self::throttle($ip, $email);
    }

    /**
     * 蜜罐:表单里放一个视觉隐藏的字段,人不会填、脚本会填。
     *
     * 命中时**静默丢弃**(调用方应返回成功) —— 明着报错等于告诉脚本作者「换个字段名再来」。
     */
    private static function honeypot(array $input): void
    {
        if (! config('moo-feedback.anti_spam.honeypot.enabled', true)) {
            return;
        }

        $field = (string) config('moo-feedback.anti_spam.honeypot.field', 'nickname_confirm');

        if (($input[$field] ?? '') !== '') {
            throw SpamRejected::honeypot();
        }
    }

    /** 过短多为灌水,过长多为粘贴攻击。 */
    private static function length(string $content): void
    {
        $len = mb_strlen(trim($content));
        $min = (int) config('moo-feedback.anti_spam.content.min', 6);
        $max = (int) config('moo-feedback.anti_spam.content.max', 4000);

        if ($len < $min || $len > $max) {
            throw SpamRejected::length($len, $min, $max);
        }
    }

    /**
     * 同 IP / 同邮箱在时间窗内的提交次数上限。只统计**顶楼行** —— 话题串里的追加发言不该被限流。
     */
    private static function throttle(?string $ip, ?string $email): void
    {
        if (! config('moo-feedback.anti_spam.throttle.enabled', true)) {
            return;
        }

        $since = now()->subHours((int) config('moo-feedback.anti_spam.throttle.window_hours', 1));

        foreach ([['feedback_ip', $ip, 'max_per_ip'], ['feedback_email', $email, 'max_per_mail']] as [$column, $value, $key]) {
            if ($value === null || $value === '') {
                continue;
            }

            $max = (int) config("moo-feedback.anti_spam.throttle.{$key}", 5);
            $hit = Feedback::query()->roots()
                ->where($column, $value)
                ->where('created_at', '>=', $since)
                ->count();

            if ($hit >= $max) {
                throw SpamRejected::throttled($column, $max);
            }
        }
    }
}
