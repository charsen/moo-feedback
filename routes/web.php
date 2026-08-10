<?php declare(strict_types=1);
/*
 * 意见反馈前台路由 —— 由 MooeenFeedbackServiceProvider 在
 * config('moo-feedback.public.enabled') 为真时挂载(默认关闭)。
 *
 * 这是**匿名可写**的公开接口,故:
 *   - 默认不挂载,host 必须显式开启;
 *   - 中间件默认带 throttle,与包内 anti_spam 的业务限流互补(前者挡刷接口,后者限真实提交量);
 *   - store 不返回反馈 ID,且与蜜罐静默拦截返回完全相同的响应。
 */

Route::post('feedbacks', [\Mooeen\Feedback\Http\Controllers\Web\FeedbackController::class, 'store'])
    ->name('store');

// 表单渲染元信息:分类目录、蜜罐字段名、内容长度上下限、必填联系方式
Route::get('feedbacks/meta', [\Mooeen\Feedback\Http\Controllers\Web\FeedbackController::class, 'meta'])
    ->name('meta');
