<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'stage', 'title', 'step', 'agent_id', 'status', 'inputs', 'output_artifact_id', 'tokens_in', 'tokens_out', 'attempts', 'depth', 'parent_id', 'error', 'revision_note', 'started_at', 'completed_at'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => TaskStatus::Queued,
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'inputs' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function output(): BelongsTo
    {
        return $this->belongsTo(Artifact::class, 'output_artifact_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [TaskStatus::Queued, TaskStatus::Running, TaskStatus::WaitingApproval]);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [TaskStatus::Done, TaskStatus::Failed, TaskStatus::Cancelled], true);
    }
}
