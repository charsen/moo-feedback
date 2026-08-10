<?php declare(strict_types=1);

namespace Mooeen\Feedback\Tests\Stubs;

use Mooeen\Feedback\Contracts\FeedbackTypeResolver;

/** host 胶水层替身：声明本 host 的分类目录，其中 SALES 要求携带宿主。 */
class TestFeedbackTypes implements FeedbackTypeResolver
{
    public function types(): array
    {
        return [
            'SALES'   => ['label' => '销售咨询', 'requires_target' => true, 'sort' => 1],
            'SUPPORT' => ['label' => '技术支持', 'sort' => 2],
            'OTHER'   => ['label' => '其他', 'sort' => 9],
        ];
    }
}
