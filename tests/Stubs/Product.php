<?php declare(strict_types=1);

namespace Mooeen\Feedback\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Mooeen\Feedback\Models\Concerns\Feedbackable;

/** 宿主对象替身：业务模型 use Feedbackable 即可被反馈关联。 */
class Product extends Model
{
    use Feedbackable;

    protected $table = 'test_products';

    protected $fillable = ['title'];
}
