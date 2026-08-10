<?php declare(strict_types=1);
/*
 * @Author:      Charsen <https://github.com/charsen>
 * @Date:        2026-08-10 16:58:39
 * @Description: Create 反馈 (moo_feedbacks)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moo_feedbacks', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('ID');
            $table->bigInteger('feedback_root_id')->unsigned()->nullable()->comment('顶楼反馈ID');
            $table->bigInteger('feedback_parent_id')->unsigned()->nullable()->comment('父行ID');
            $table->string('feedbackable_type', 128)->nullable()->comment('多态模型');
            $table->bigInteger('feedbackable_id')->unsigned()->nullable()->comment('多态ID');
            $table->string('feedbackable_title', 192)->nullable()->comment('反馈对象标题快照');
            $table->string('feedback_type', 32)->nullable()->comment('分类');
            $table->tinyInteger('feedback_status')->unsigned()->default(10)->comment('受理状态');
            $table->text('feedback_content')->comment('内容');
            $table->bigInteger('feedback_submitter_id')->unsigned()->nullable()->comment('发言人ID');
            $table->tinyInteger('feedback_speaker_side')->unsigned()->default(1)->comment('发言侧');
            $table->string('feedback_contact_name', 100)->nullable()->comment('联系人');
            $table->string('feedback_organization', 200)->nullable()->comment('企业机构');
            $table->string('feedback_phone', 30)->nullable()->comment('联系电话');
            $table->string('feedback_email', 200)->nullable()->comment('邮箱');
            $table->string('feedback_ip', 64)->nullable()->comment('IP');
            $table->string('feedback_device', 64)->nullable()->comment('设备');
            $table->string('feedback_platform', 64)->nullable()->comment('操作系统');
            $table->string('feedback_browser', 64)->nullable()->comment('浏览器');
            $table->string('feedback_page_url', 512)->nullable()->comment('来源页面');
            $table->tinyInteger('feedback_last_speaker_side')->unsigned()->nullable()->comment('最后发言侧');
            $table->timestamp('feedback_last_replied_at')->nullable()->comment('最后发言于');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['feedbackable_type', 'feedbackable_id'], 'feedbackable');
            $table->index('feedback_root_id', 'feedback_root_id');
            $table->index('feedback_parent_id', 'feedback_parent_id');
            $table->index('feedback_submitter_id', 'feedback_submitter_id');
            $table->index('feedback_status', 'feedback_status');
            $table->index('feedback_ip', 'feedback_ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moo_feedbacks');
    }
};
