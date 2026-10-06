<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'room_id', 'type', 'title', 'version', 'path', 'body', 'author_agent_id', 'author_user_id', 'meta'])]
class Artifact extends Model
{
    protected $attributes = [
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function authorAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'author_agent_id');
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(ArtifactLink::class, 'from_artifact_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(ArtifactLink::class, 'to_artifact_id');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
