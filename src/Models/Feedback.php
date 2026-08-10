<?php declare(strict_types=1);
/*
 * @Author: Charsen <https://github.com/charsen>
 * @Date: 2026-08-10 16:58
 * @LastEditors: Charsen <https://github.com/charsen>
 * @LastEditTime: 2026-08-10 16:58
 * @Description: Feedback Model
 */

namespace Mooeen\Feedback\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Mooeen\Feedback\Contracts\FeedbackTypeResolver;
use Mooeen\Feedback\Events\FeedbackAppended;
use Mooeen\Feedback\Events\FeedbackReplied;
use Mooeen\Feedback\Events\FeedbackStatusChanged;
use Mooeen\Feedback\Events\FeedbackSubmitted;
use Mooeen\Feedback\Exceptions\InvalidFeedbackType;
use Mooeen\Feedback\Models\Enums\FeedbackSpeakerSide;
use Mooeen\Feedback\Models\Enums\FeedbackStatus;
use Mooeen\Feedback\Models\Filters\FeedbackFilter;
use Mooeen\Feedback\Models\Traits\FeedbackTrait;
use Mooeen\Feedback\Support\AntiSpamGuard;
use Mooeen\Feedback\Support\EnvironmentCapture;
use Mooeen\Feedback\Support\SecretRedactor;
use Mooeen\Scaffold\Concerns\GetSerializeDate;
use Mooeen\Scaffold\Concerns\GetUpdatedAtHumanTime;
use Mooeen\Scaffold\Concerns\Optional;
use Mooeen\Scaffold\Concerns\UsingSnowFlakePrimaryKey;

/**
 * Feedback Model
 *
 * @property int         $id                         ID
 * @property int         $feedback_root_id           顶楼反馈ID
 * @property int         $feedback_parent_id         父行ID
 * @property string      $feedbackable_type          多态模型
 * @property int         $feedbackable_id            多态ID
 * @property string      $feedbackable_title         反馈对象标题快照
 * @property string      $feedback_type              分类
 * @property int         $feedback_status            受理状态
 * @property string      $feedback_content           内容
 * @property int         $feedback_submitter_id      发言人ID
 * @property int         $feedback_speaker_side      发言侧
 * @property string      $feedback_contact_name      联系人
 * @property string      $feedback_organization      企业机构
 * @property string      $feedback_phone             联系电话
 * @property string      $feedback_email             邮箱
 * @property string      $feedback_ip                IP
 * @property string      $feedback_device            设备
 * @property string      $feedback_platform          操作系统
 * @property string      $feedback_browser           浏览器
 * @property string      $feedback_page_url          来源页面
 * @property int         $feedback_last_speaker_side 最后发言侧
 * @property Carbon|null $feedback_last_replied_at   最后发言于
 * @property Carbon|null $deleted_at                 删除于
 * @property Carbon|null $created_at                 创建于
 * @property Carbon|null $updated_at                 更新于
 *
 * @method \Illuminate\Database\Eloquent\Builder select(array $fields)
 * @method \Illuminate\Database\Eloquent\Builder query()
 */
class Feedback extends Model
{
    use FeedbackTrait;
    use Filterable;
    use GetSerializeDate;
    use GetUpdatedAtHumanTime;
    use Optional;
    use SoftDeletes;
    use UsingSnowFlakePrimaryKey;

    /**
     * 表格名称
     *
     * @var string
     */
    protected $table = 'moo_feedbacks';

    /**
     * 指定字段默认值
     *
     * @var array
     */
    protected $attributes = [
        'feedback_status'       => 10,
        'feedback_speaker_side' => 1,
    ];

    /**
     * 属性转换
     *
     * @var array
     */
    protected $casts = [
        'id'                       => 'string',
        'feedback_root_id'         => 'string',
        'feedback_parent_id'       => 'string',
        'feedbackable_id'          => 'string',
        'feedback_submitter_id'    => 'string',
        'feedback_last_replied_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 可以被批量赋值的属性
     *
     * @var array
     */
    protected $fillable = ['feedback_root_id', 'feedback_parent_id', 'feedbackable_type', 'feedbackable_id', 'feedbackable_title', 'feedback_type', 'feedback_status', 'feedback_content', 'feedback_submitter_id', 'feedback_speaker_side', 'feedback_contact_name', 'feedback_organization', 'feedback_phone', 'feedback_email', 'feedback_ip', 'feedback_device', 'feedback_platform', 'feedback_browser', 'feedback_page_url', 'feedback_last_speaker_side', 'feedback_last_replied_at'];

    /**
     * 数组中的属性会被隐藏
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * 追加到模型数组表单的访问器
     *
     * @var array
     */
    protected $appends = ['feedback_status_txt', 'feedback_speaker_side_txt'];

    /**
     * 指定 Filter
     */
    public function modelFilter(): string
    {
        return FeedbackFilter::class;
    }

    // ===== 手写业务区（首次生成后自持；moo:free 非 --force 重生成不覆盖本类）=====

    /**
     * 提交一条反馈（写入口单一真值源；Feedbackable::receiveFeedback() 亦委托到此）。
     *
     * 顺序刻意如此：先校验分类（便宜、无副作用）→ 再过反垃圾（要查库）→ 最后才落行。
     *
     * $attrs 里 feedback_content 必填，联系方式 / feedback_submitter_id 按提交人类型二选一：
     * 登录用户给 submitter_id，匿名访客给 feedback_contact_name / _phone / _email 等快照。
     * 环境采集自动完成，$attrs 里显式给的同名值优先（host 有更精确的 UA 解析时可覆盖）。
     *
     * @param array<string, mixed> $attrs
     *
     * @throws \Mooeen\Feedback\Exceptions\InvalidFeedbackType
     * @throws \Mooeen\Feedback\Exceptions\SpamRejected
     */
    public static function submit(array $attrs, ?EloquentModel $target = null): self
    {
        $type = (string) ($attrs['feedback_type'] ?? '');
        self::assertTypeAllowed($type, $target);

        AntiSpamGuard::check(
            $attrs,
            $attrs['feedback_ip']    ?? request()?->ip(),
            $attrs['feedback_email'] ?? null,
        );

        $row = self::create(array_merge(
            EnvironmentCapture::capture(),
            Arr::except($attrs, [config('moo-feedback.anti_spam.honeypot.field', 'nickname_confirm')]),
            [
                'feedback_root_id'      => null,
                'feedback_parent_id'    => null,
                'feedback_speaker_side' => FeedbackSpeakerSide::SUBMITTER->value,
                'feedback_status'       => FeedbackStatus::PENDING->value,
            ],
            $target === null ? [] : [
                'feedbackable_type'  => $target->getMorphClass(),
                'feedbackable_id'    => $target->getKey(),
                'feedbackable_title' => method_exists($target, 'feedbackTitle') ? $target->feedbackTitle() : null,
            ],
        ));

        $row->forceFill([
            'feedback_last_speaker_side' => FeedbackSpeakerSide::SUBMITTER->value,
            'feedback_last_replied_at'   => now(),
        ])->save();

        event(new FeedbackSubmitted($row));

        return $row;
    }

    /**
     * 分类必须在 host 声明的目录里；声明了 requires_target 的分类必须携带宿主。
     *
     * 包不硬编码任何分类值，「合法」完全由 host 的 FeedbackTypeResolver 决定 —— 未绑定契约时
     * 目录只有 OTHER，包仍可独立跑通。
     */
    protected static function assertTypeAllowed(string $type, ?EloquentModel $target): void
    {
        $catalog = app(FeedbackTypeResolver::class)->types();

        if (! isset($catalog[$type])) {
            throw InvalidFeedbackType::unknown($type, array_keys($catalog));
        }

        if (($catalog[$type]['requires_target'] ?? false) && $target === null) {
            throw InvalidFeedbackType::targetRequired($type);
        }
    }

    /**
     * 只在顶楼行有意义的业务字段。子行（feedback_root_id 非空）写入时由 booted() 强制抹平 ——
     * 这是「约定不是数据库约束」的守门处：单表自引用换来了结构简单，代价就是得在模型层挡住
     * 「往回复行里塞状态 / 联系方式」这类写法，否则迟早有人这么干。
     *
     * feedback_status / feedback_speaker_side 不在此列：二者非空且有默认值，子行上
     * status 是无意义填充（统一压回 PENDING），speaker_side 则是子行的核心字段。
     *
     * @var list<string>
     */
    public const ROOT_ONLY_FIELDS = [
        'feedbackable_type', 'feedbackable_id', 'feedbackable_title',
        'feedback_type',
        'feedback_contact_name', 'feedback_organization', 'feedback_phone', 'feedback_email',
        'feedback_ip', 'feedback_device', 'feedback_platform', 'feedback_browser', 'feedback_page_url',
        'feedback_last_speaker_side', 'feedback_last_replied_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            if ($row->feedback_root_id === null) {
                return;     // 顶楼行：业务字段本就归它承载
            }

            foreach (self::ROOT_ONLY_FIELDS as $field) {
                $row->setAttribute($field, null);
            }
            // 非空列压回默认值：子行的状态是填充位，不参与任何判断（真值在顶楼行）
            $row->setAttribute('feedback_status', FeedbackStatus::PENDING->value);
        });
    }

    /**
     * 被反馈的多态业务对象（任何 use Feedbackable 的模型）。仅顶楼行有值。
     */
    public function feedbackable(): MorphTo
    {
        return $this->morphTo('feedbackable', 'feedbackable_type', 'feedbackable_id', 'id');
    }

    /**
     * 直接父行。带 withTrashed —— 父行软删后，话题串的层级关系仍要能还原。
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'feedback_parent_id', 'id')->withTrashed();
    }

    /**
     * 直接子行（回复本条的那一层）。
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'feedback_parent_id', 'id');
    }

    /**
     * 整条话题串（本顶楼下的全部发言，按时间正序）。顶楼行 id 即该串所有发言的 feedback_root_id，
     * 一发查询取整串在内存组树，不做递归查询。
     */
    public function thread(): HasMany
    {
        return $this->hasMany(self::class, 'feedback_root_id', 'id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * 顶楼行（一条反馈）。列表、统计、筛选一律基于本作用域 —— 子行是发言，不该出现在反馈列表里。
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('feedback_root_id');
    }

    public function isRoot(): bool
    {
        return $this->feedback_root_id === null;
    }

    /**
     * 取所属顶楼行；自身即顶楼时返回自身。
     */
    public function rootRow(): self
    {
        return $this->isRoot() ? $this : (self::withTrashed()->find($this->feedback_root_id) ?? $this);
    }

    public function status(): FeedbackStatus
    {
        return FeedbackStatus::from((int) $this->feedback_status);
    }

    public function speakerSide(): FeedbackSpeakerSide
    {
        return FeedbackSpeakerSide::from((int) $this->feedback_speaker_side);
    }

    /**
     * 话题串追加一条发言。
     *
     * 落在**顶楼行**上调用（子行上调用则自动上溯到顶楼），$parent 传 null 即挂在顶楼下。
     * 三件事一并完成：写子行 → 刷新顶楼的派生缓存（最后发言方 / 最后发言时间）→ 派事件。
     *
     * 状态只有一条自动规则：已完结 / 已关闭后**提交侧**再发言 → 自动退回待受理。
     * 受理侧发言**不**自动改状态 —— 「回了一句」不等于「处理完了」，那是受理人手动置位的事
     * （这正是把人工状态与最后发言方拆开的理由，见 docs/overview.md §6）。
     */
    public function appendMessage(
        string $content,
        FeedbackSpeakerSide $side,
        int|string|null $submitterId = null,
        ?self $parent = null,
    ): self {
        $root = $this->rootRow();
        $parent ??= $root;

        $message = self::create([
            'feedback_root_id'      => $root->getKey(),
            'feedback_parent_id'    => $parent->getKey(),
            'feedback_content'      => $content,
            'feedback_speaker_side' => $side->value,
            'feedback_submitter_id' => $submitterId,
        ]);

        $root->forceFill([
            'feedback_last_speaker_side' => $side->value,
            'feedback_last_replied_at'   => now(),
        ])->save();

        if ($side === FeedbackSpeakerSide::SUBMITTER
            && in_array($root->status(), [FeedbackStatus::RESOLVED, FeedbackStatus::CLOSED], true)) {
            $root->transitionTo(FeedbackStatus::PENDING, automatic: true);
        }

        $root->unsetRelation('thread');

        event($side === FeedbackSpeakerSide::HANDLER
            ? new FeedbackReplied($root, $message)
            : new FeedbackAppended($root, $message));

        return $message;
    }

    /**
     * 受理状态流转。只作用于顶楼行；同值为 no-op（不派空事件）。
     *
     * 首版不做跃迁合法性守门：过度约束会卡住受理人员的实际操作，先让状态可自由置位，
     * 真出现乱流转再按实际情况收紧。
     */
    public function transitionTo(FeedbackStatus $to, bool $automatic = false): self
    {
        $root = $this->rootRow();
        $from = $root->status();

        if ($from === $to) {
            return $root;
        }

        $root->forceFill(['feedback_status' => $to->value])->save();

        event(new FeedbackStatusChanged($root, $from, $to, $automatic));

        return $root;
    }

    /**
     * 内容读时脱敏：打码 JWT / Bearer / password= 等凭证类模式。
     *
     * 打码发生在**读侧**，不改库中原值 —— 因此对存量脏数据同样生效，且 host 若要导出原文
     * 仍可经 getRawOriginal() 拿到。可经 config('moo-feedback.redact_secrets') 整体关闭。
     */
    public function getFeedbackContentAttribute(): ?string
    {
        $raw = $this->attributes['feedback_content'] ?? null;

        return config('moo-feedback.redact_secrets', true) ? SecretRedactor::scrub($raw) : $raw;
    }
}
