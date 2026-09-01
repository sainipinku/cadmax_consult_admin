<?php

namespace App\Models;

use App\Enums\TaskCommentKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class TaskComment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasUuids;

    protected $fillable = [
        'uuid',
        'reply_note_id',
        'task_id',
        'project_id',
        'commented_by',
        'comment',
        'kind',
        'from_status',
        'to_status',
        'latitude',
        'longitude',
        'battery_pct',
        'meta',
        'reply_to_id',
    ];

    protected $casts = [
        'kind' => TaskCommentKind::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'battery_pct' => 'integer',
        'meta' => 'array',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Construction\Project::class, 'project_id');
    }

    public function commenter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'commented_by');
    }

    public function superAdminCommenter(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'commented_by');
    }

    public function commenterDynamic(): Member|SuperAdmin|Admin|null
    {
        if ($this->commented_by === null) {
            return null;
        }
        if (class_exists(Admin::class) && Admin::query()->where('id', (int) $this->commented_by)->exists()) {
            return Admin::query()->find((int) $this->commented_by);
        }
        if (SuperAdmin::query()->where('id', (int) $this->commented_by)->exists()) {
            return SuperAdmin::query()->find((int) $this->commented_by);
        }
        return Member::query()->find((int) $this->commented_by);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id')->with(['commenter', 'replies']);
    }

    public function legacyReplies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_note_id')->with(['commenter', 'legacyReplies']);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function legacyParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_note_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ConstructionDocument::class, 'documentable');
    }

    public static function buildForTransition(
        Task $task,
        Member|SuperAdmin|Admin $actor,
        ?string $from,
        string $to,
        ?string $note = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $meta = null,
    ): self {
        $self = new self();
        $self->task_id = $task->id;
        $self->project_id = $task->project_id;
        $self->kind = TaskCommentKind::STATUS_NOTE->value;
        $self->from_status = $from;
        $self->to_status = $to;
        $self->comment = $note ?? sprintf(
            'Status changed from %s to %s by %s.',
            (string) ($from ?? 'unknown'),
            $to,
            $actor->name ?? ($actor->email ?? 'system')
        );
        $self->latitude = $latitude;
        $self->longitude = $longitude;
        $self->battery_pct = $meta['battery_pct'] ?? null;
        $self->meta = $meta;
        if ($actor instanceof Member) {
            $self->commented_by = $actor->id;
        } else {
            $self->commented_by = $actor->id;
        }
        return $self;
    }
}
