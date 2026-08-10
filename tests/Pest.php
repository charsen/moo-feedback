<?php declare(strict_types=1);

use Mooeen\Feedback\Tests\PublicTestCase;
use Mooeen\Feedback\Tests\TestCase;

// Feature：包默认形态（前台入口关闭）
uses(TestCase::class)->in('Feature');

// Web：前台入口开启态 —— 开关须在建应用前置好，故用独立 TestCase，
// 也就必须放在独立目录（Pest 不允许同一目录绑两个 TestCase）。
uses(PublicTestCase::class)->in('Web');
