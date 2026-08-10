<?php declare(strict_types=1);
/*
 * 意见反馈扩展包 ServiceProvider。
 *
 *   register() — 合并 config（host 不发布也能读 config('moo-feedback.*')）+ 注册两个契约默认实现 + 词条深合并
 *   boot()     — 加载迁移 + 发布 config + 加载语言目录
 *
 * 分类目录走 host 绑定的 FeedbackTypeResolver 契约声明；发言人姓名走 SubmitterResolver 契约读时批量解析。
 * 二者包内均有默认实现，未绑定时包可独立跑通。
 *
 * 雪花单例（scaffold.snowflake，ScaffoldProvider 绑定）/ 操作人身份（scaffold 共享 OperatorResolver）/
 * Filter 基类 / MergingLoader 均由 moo-scaffold 提供；本包不自持副本，也不依赖 moo-system 或 host App\*。
 */

namespace Mooeen\Feedback;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;
use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
use Mooeen\Feedback\Contracts\SubmitterResolver;
use Mooeen\Feedback\Support\NullFeedbackTypeResolver;
use Mooeen\Feedback\Support\NullSubmitterResolver;
use Mooeen\Scaffold\Translation\MergingLoader;

class MooeenFeedbackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/moo-feedback.php', 'moo-feedback');

        // 雪花主键单例由 ScaffoldProvider 绑定为 scaffold.snowflake；操作人身份（当前是谁，写入受理方发言时取）
        // 用 scaffold 共享 Mooeen\Scaffold\Contracts\OperatorResolver（默认 auth()->id()）。二者本包均不自持。

        // 分类目录：默认 NullFeedbackTypeResolver 只给一条 OTHER（分类是核心不是装饰，返空则无法提交）；
        // host 在自己的 provider（App\Moo\Feedback）里 bind 业务实现覆盖。
        $this->app->bind(FeedbackTypeResolver::class, NullFeedbackTypeResolver::class);

        // 发言人姓名解析：默认 NullSubmitterResolver（返空、零 moo-system）；host 同上覆盖。
        $this->app->bind(SubmitterResolver::class, NullSubmitterResolver::class);

        // 包内 lang/（db/model/validation 的反馈字段词条）与 host 同名文件深合并（host 优先）。
        // 本包用 scaffold 共享 MergingLoader；host 无需拷 yaml / 跑 moo:i18n 即有翻译。
        $this->app->extend('translation.loader', function (Loader $inner) {
            return new MergingLoader($inner, __DIR__ . '/../lang');
        });
    }

    public function boot(): void
    {
        // 运行期文案统一用 moo-feedback 显式命名空间取用，避免业务代码依赖默认 group。
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'moo-feedback');

        // 反馈表（物理名 moo_feedbacks，单表自引用话题串）
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // host 显式发布配置：php artisan vendor:publish --tag=moo-feedback-config
        $this->publishes([
            __DIR__ . '/../config/moo-feedback.php' => config_path('moo-feedback.php'),
        ], 'moo-feedback-config');

        // TODO(M1)：管理面路由组挂载。routes/admin.php 与 Admin 控制器同属 codegen 产物，
        // 随 scaffold/database/Feedback.yaml 生成后在此按 config('moo-feedback.admin') 挂上。
    }
}
