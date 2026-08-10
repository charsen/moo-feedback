<?php declare(strict_types=1);
/*
 * 提交环境采集 —— IP / 设备 / 操作系统 / 浏览器 / 来源页面。
 *
 * 用途有二:受理时复现问题,以及作为反垃圾限流的维度。整体与逐项均可经 config 关闭,
 * host 若有合规要求不得留存访客 IP,关掉即可,不影响其余能力。
 *
 * 刻意不引入 UA 解析依赖:包只做「够用的」粗粒度切分(移动/桌面、常见系统与浏览器),
 * host 想要精确解析可自行在提交前塞进 attrs 覆盖 —— 为一个反馈包拖进一整个 UA 库不值当。
 */

namespace Mooeen\Feedback\Support;

use Illuminate\Http\Request;

class EnvironmentCapture
{
    /**
     * @return array<string, string|null> 只含 config 允许采集的项;整体关闭时返回空数组
     */
    public static function capture(?Request $request = null): array
    {
        if (! config('moo-feedback.capture.enabled', true)) {
            return [];
        }

        $request ??= request();
        if (! $request instanceof Request) {
            return [];
        }

        $ua  = (string) $request->userAgent();
        $out = [
            'feedback_ip'       => $request->ip(),
            'feedback_device'   => self::device($ua),
            'feedback_platform' => self::match($ua, [
                'Windows' => '/Windows NT/i', 'macOS' => '/Mac OS X|Macintosh/i', 'iOS' => '/iPhone|iPad/i',
                'Android' => '/Android/i', 'Linux' => '/Linux/i',
            ]),
            'feedback_browser' => self::match($ua, [
                'Edge'   => '/Edg\//i', 'Chrome' => '/Chrome|CriOS/i', 'Firefox' => '/Firefox|FxiOS/i',
                'Safari' => '/Safari/i', 'IE' => '/MSIE|Trident/i',
            ]),
            'feedback_page_url' => $request->headers->get('referer'),
        ];

        foreach (['ip', 'device', 'platform', 'browser', 'page_url'] as $item) {
            if (! config("moo-feedback.capture.{$item}", true)) {
                unset($out["feedback_{$item}"]);
            }
        }

        return array_filter($out, static fn ($v) => $v !== null && $v !== '');
    }

    private static function device(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet/i', $ua)           => 'Tablet',
            (bool) preg_match('/Mobile|iPhone|Android/i', $ua) => 'Mobile',
            $ua === ''                                         => 'Unknown',
            default                                            => 'Desktop',
        };
    }

    /** @param array<string, string> $patterns 标签 => 正则,按序首个命中即返回 */
    private static function match(string $ua, array $patterns): ?string
    {
        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $label;
            }
        }

        return null;
    }
}
