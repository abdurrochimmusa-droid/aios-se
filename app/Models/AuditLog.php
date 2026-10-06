<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_type', 'actor_id', 'action', 'target_type', 'target_id', 'details', 'ip'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForTarget(Builder $query, string $type, int $id): Builder
    {
        return $query->where('target_type', $type)->where('target_id', $id);
    }

    public function scopeForActor(Builder $query, string $type, int $id): Builder
    {
        return $query->where('actor_type', $type)->where('actor_id', $id);
    }
}
