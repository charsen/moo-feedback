<?php declare(strict_types=1);

namespace Mooeen\Feedback\Tests;

/**
 * 前台入口开启态的 TestCase。
 *
 * 路由在 provider boot 时一次性注册，测试里 config() 改完再 refreshApplication() 会把改动一起丢掉 ——
 * 故开关必须在**建应用之前**置好，即此处的 defineEnvironment。
 *
 * 中间件置空：throttle / api 组是 host 侧的事（Laravel 自己的能力），包测试只验包自己的逻辑。
 */
abstract class PublicTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('moo-feedback.public.enabled', true);
        $app['config']->set('moo-feedback.public.middleware', []);
    }
}
