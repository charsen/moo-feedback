<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 测试用宿主对象替身表 —— 验证多态关联与标题快照。 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 192);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_products');
    }
};
