<?php declare(strict_types=1);
/*
 * @Author: Charsen
 * @Date: 2026-08-10 17:46
 * @LastEditors: Charsen
 * @LastEditTime: 2026-08-11 10:24
 * @Description:
 */

use Illuminate\Support\Facades\Route;
use Mooeen\Feedback\Http\Controllers\Admin\FeedbackController;

/*
 * 意见反馈后台路由 —— 由 MooeenFeedbackServiceProvider 挂到 host 的 admin 组
 * （默认 prefix=api/admin、name=admin.，可经 config('moo-feedback.admin') 覆盖）。
 * 控制器用 host 注册的 Route::iResource 宏（moo 系 host 公共契约，按控制器公开方法反射注册）。
 *
 * 资源路由由 `moo:free admin Feedback` 在下方锚点处生成，勿手写。
 * FeedbackController 已裁掉 store / update / create / edit —— 反馈的写入口永远在业务侧
 * （Feedback::submit()），管理面只做受理：列表 / 详情 / 回复 / 状态流转 / 清理。
 * iResource 按公开方法反射注册，裁掉的方法自然不会有路由。
 *
 * reply / transition 不在 iResource 的约定动作表内，故显式声明于锚点下方。
 */

// FeedbackController
Route::iResource('feedbacks', FeedbackController::class);

// 受理动作：话题串追加受理侧发言 / 手动置位状态
Route::post('feedbacks/{id}/reply', [FeedbackController::class, 'reply'])
    ->name('feedbacks.reply');
Route::patch('feedbacks/{id}/transition', [FeedbackController::class, 'transition'])
    ->name('feedbacks.transition');

// :insert_code_here:do_not_delete
