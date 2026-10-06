<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'stage', 'artifact_id', 'status', 'requested_by', 'decided_by', 'note', 'decided_at'])]
class Approval extends Model
{
    protected $attributes = [
        'status' => ApprovalStatus::Pending,
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending);
    }
}
