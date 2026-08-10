<?php declare(strict_types=1);

namespace Mooeen\Feedback\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mooeen\Feedback\MooeenFeedbackServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * 只注册本包 Provider —— 零 moo-system（分类目录与发言人姓名均走契约，默认实现在包内）。
     * scaffold.snowflake 单例在 defineEnvironment shim，不加载重的 ScaffoldProvider。
     */
    protected function getPackageProviders($app): array
    {
        return [
            MooeenFeedbackServiceProvider::class,
        ];
    }

    /**
     * 迁移：本包 moo_feedbacks 由 Provider loadMigrationsFrom 自动跑；这里补测试用宿主对象替身表。
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }

    /**
     * host 端契约的最低替身 —— 仅为「包能 boot 起来」：
     *   - scaffold.snowflake 单例（生产由 auto-discover 的 ScaffoldProvider 绑）
     *   - scaffold 共享 OperatorResolver（当前是谁 → id，默认 GuardOperatorResolver）
     *   - admin 中间件组（路由 wrap 不报错）
     *   - sqlite + 中文优先
     *
     * TODO(M1)：admin 路由落地后补 Route::iResource 宏 shim（host 公共契约，按控制器公开方法反射注册）。
     */
    protected function defineEnvironment($app): void
    {
        // 雪花单例上移 scaffold（scaffold.snowflake，生产由 auto-discover 的 ScaffoldProvider 绑）。
        // 包测试不加载重的 ScaffoldProvider，在此按原 registerSnowflake 同款 shim。
        $app->singleton('scaffold.snowflake', function ($app) {
            return (new \Godruoyi\Snowflake\Snowflake(1, 1))
                ->setStartTimeStamp(strtotime('2021-10-10') * 1000)
                ->setSequenceResolver(new \Godruoyi\Snowflake\LaravelSequenceResolver($app['cache']->store()));
        });

        // 操作人身份契约（scaffold 共享 OperatorResolver）：生产由 ScaffoldProvider 默认绑 GuardOperatorResolver；
        // 包测试不加载 ScaffoldProvider，在此同款 shim（未登录 auth()->id() 返 null）。
        $app->bind(
            \Mooeen\Scaffold\Contracts\OperatorResolver::class,
            \Mooeen\Scaffold\Support\GuardOperatorResolver::class,
        );

        $app['router']->middlewareGroup('admin', []);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // 中文优先（生产同款）：_txt 经 MergingLoader 走包内 lang，默认解析中文词条。
        $app['config']->set('app.locale', 'zh-CN');
    }
}
