<?php declare(strict_types=1);
/*
 * SecretRedactor —— 把用户自由文本里常见的【凭证】模式打码。
 *
 * 反馈内容是用户手打的，实践中常被粘进异常消息、请求 URL、配置片段，里面夹带 JWT / Bearer /
 * password= 这类凭证。这些文本会显示在管理面、进入通知渠道、被复制给 AI 助手分析 —— 一旦随手转发即可被滥用。
 * 本类在读侧（模型访问器）统一打码，不改库中原值，因此对**存量脏数据**同样生效。
 *
 * 刻意【不】打码手机号 / 身份证等 PII 数字：那是 host 自有业务数据，且常作为查询值出现在 URL 路径 /
 * SQL 字面量里 —— 打码后受理人员无法照搬复现问题。本层只处理「泄露即可被滥用」的凭证类；
 * PII 的可见性由「谁能访问该后台」的访问控制兜底，不靠字符串打码。
 */

namespace Mooeen\Feedback\Support;

class SecretRedactor
{
    /** 键名本身即敏感的数组键（递归脱敏时整值打码，不做模式匹配）。 */
    private const SENSITIVE_ARRAY_KEY = '/(?:^|[_-])(?:password|passwd|pwd|secret|token|api[_-]?key|access[_-]?token|refresh[_-]?token|id[_-]?token|client[_-]?secret|authorization|proxy[_-]?authorization|cookie|set[_-]?cookie|x[_-]?auth[_-]?token|x[_-]?api[_-]?key|x[_-]?access[_-]?token)(?:$|[_-])/i';

    /** key = value / key: value 形式里，键名敏感则打码其值（保留键名，便于判断泄露了什么）。 */
    private const SENSITIVE_KEY_VALUE = '/\b(password|passwd|pwd|secret|token|api[_-]?key|access[_-]?token|refresh[_-]?token|id[_-]?token|client[_-]?secret|authorization|proxy[_-]?authorization|cookie|set[_-]?cookie|x[_-]?auth[_-]?token|x[_-]?api[_-]?key|x[_-]?access[_-]?token)\b(\s*["\']?\s*[:=]\s*["\']?)([^\s"\',&;]+)/i';

    public static function scrub(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return $v;
        }

        // JWT（三段 base64url）：eyJ....xxx.yyy
        $v = preg_replace('/eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/', '***JWT***', $v);

        // Bearer <token>
        $v = preg_replace('/\bBearer\s+[A-Za-z0-9._\-]+/i', 'Bearer ***', (string) $v);

        // key = value / key: value 形式的敏感字段
        return preg_replace(self::SENSITIVE_KEY_VALUE, '$1$2***', (string) $v);
    }

    /**
     * 递归脱敏数组里的所有字符串值（键名保留）。用于结构化附带信息（环境快照 / 上下文 JSON）
     * —— 里面的 URL / message 可能带 token，展示 / 通知 / 复制前统一打码。
     */
    public static function scrubArray(mixed $v): mixed
    {
        if (is_string($v)) {
            return self::scrub($v);
        }

        if (is_array($v)) {
            $out = [];
            foreach ($v as $key => $value) {
                $out[$key] = is_string($key) && preg_match(self::SENSITIVE_ARRAY_KEY, $key) === 1
                    ? '***'
                    : self::scrubArray($value);
            }

            return $out;
        }

        return $v;
    }
}
