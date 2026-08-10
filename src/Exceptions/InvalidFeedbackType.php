<?php declare(strict_types=1);
/*
 * 提交的分类不合法 —— 不在 host 经 FeedbackTypeResolver 声明的目录里,或该分类要求宿主却没给。
 *
 * 包不硬编码任何分类值,因此「合法」完全由 host 的声明决定;未绑定契约时目录只有 OTHER。
 */

namespace Mooeen\Feedback\Exceptions;

use InvalidArgumentException;

class InvalidFeedbackType extends InvalidArgumentException
{
    /** @param list<string> $allowed */
    public static function unknown(string $type, array $allowed): self
    {
        return new self(sprintf(
            '分类 [%s] 不在已声明的目录中（当前可用：%s）。请在 host 的 FeedbackTypeResolver 实现里补充。',
            $type,
            $allowed === [] ? '（空）' : implode(', ', $allowed),
        ));
    }

    public static function targetRequired(string $type): self
    {
        return new self("分类 [{$type}] 声明了 requires_target，提交时必须携带多态宿主对象。");
    }
}
