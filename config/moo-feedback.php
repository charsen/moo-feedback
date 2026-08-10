<?php declare(strict_types=1);
/*
 * moo-feedback 配置 —— 意见反馈统一管理。
 *
 * ⚠ env() 仅在 config 文件内可用；`config:cache` 后业务代码统一读 config('moo-feedback.*')，不要直接 env()。
 */

return [
    // 后台路由挂载（moo 系扩展包同款；host 用非标前缀 / 多租户守卫时可覆盖）。
    'admin' => [
        'prefix'     => 'api/admin',
        'name'       => 'admin.',
        'middleware' => env('MOO_FEEDBACK_ADMIN_MIDDLEWARE', 'admin'),
    ],

    // 提交环境采集（仅顶楼行）。用途有二：受理时复现问题，以及作为反垃圾限流的维度。
    // host 若有合规要求不得留存访客 IP，整体关掉即可，不影响其余能力。
    'capture' => [
        'enabled'  => env('MOO_FEEDBACK_CAPTURE', true),
        'ip'       => true,
        'device'   => true,
        'platform' => true,
        'browser'  => true,
        'page_url' => true,
    ],

    // 反垃圾。匿名提交入口不做这个，上线即被灌。验证码刻意不集成 —— 包不绑定具体服务商，
    // host 自行在提交入口前置。
    'anti_spam' => [
        // 同一维度在时间窗内的提交次数上限
        'throttle' => [
            'enabled'      => true,
            'window_hours' => 1,
            'max_per_ip'   => 5,
            'max_per_mail' => 3,
        ],

        // 蜜罐：表单里放一个视觉隐藏的字段，人不会填、脚本会填。有值即静默丢弃（返回成功，不落库）。
        'honeypot' => [
            'enabled' => true,
            'field'   => 'nickname_confirm',
        ],

        // 内容长度上下限（字符数）：过短多为灌水，过长多为粘贴攻击。
        'content' => [
            'min' => 6,
            'max' => 4000,
        ],
    ],

    // 文本脱敏：读侧对反馈内容统一打码凭证类模式（JWT / Bearer / password= …）。
    // 不改库中原值，因此对存量数据同样生效。详见 Support\SecretRedactor。
    'redact_secrets' => true,
];
