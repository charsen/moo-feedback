<?php declare(strict_types=1);
/*
 * 提交被反垃圾拦截。
 *
 * $silent 区分两类拦截,调用方据此决定响应:
 *   - 蜜罐命中 → silent = true,应**返回成功**。明着报错等于告诉脚本作者「换个字段名再来」。
 *   - 超频 / 长度越界 → silent = false,应如实告知提交人。
 */

namespace Mooeen\Feedback\Exceptions;

use RuntimeException;

class SpamRejected extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason, public readonly bool $silent = false)
    {
        parent::__construct($message);
    }

    public static function honeypot(): self
    {
        return new self('蜜罐字段被填写，判定为自动化提交', 'honeypot', silent: true);
    }

    public static function length(int $len, int $min, int $max): self
    {
        return new self("内容长度 {$len} 不在 {$min} ~ {$max} 之间", 'length');
    }

    public static function throttled(string $column, int $max): self
    {
        return new self("同一 {$column} 在时间窗内的提交已达上限 {$max}", 'throttle');
    }
}
