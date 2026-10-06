<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'idea', 'room_id', 'status', 'repo_path', 'token_budget', 'delegation', 'stages', 'owner_id'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ProjectStatus::Draft,
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'delegation' => 'array',
            'stages' => 'array',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Running);
    }
}
