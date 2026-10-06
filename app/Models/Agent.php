<?php

namespace App\Models;

use App\Enums\AgentStatus;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['slug', 'name', 'room_id', 'role_id', 'combo_name', 'base_url', 'status', 'token_budget', 'config'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => AgentStatus::Active,
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'config' => 'array',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function authoredArtifacts(): HasMany
    {
        return $this->hasMany(Artifact::class, 'author_agent_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AgentStatus::Active);
    }
}
